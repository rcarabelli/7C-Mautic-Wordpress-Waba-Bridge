<?php
namespace SevenC\MWB\REST;

use WP_REST_Request;
use WP_REST_Response;
use SevenC\MWB\Settings\Keys;

class Ingest_Controller {

    public static function register(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('7c-mwb/v1', '/ingest', [
                'methods'             => 'POST',
                'permission_callback' => '__return_true', // seguridad la maneja verify_auth()
                'callback'            => [__CLASS__, 'handle'],
            ]);
        });
    }

    public static function handle(WP_REST_Request $req): WP_REST_Response
    {
        $raw = $req->get_body();
    
        // ✅ Autenticación (token fijo o HMAC)
        self::verify_auth($req, $raw);
    
        $ok = true; 
        $errors = [];
    
        // 1) JSON base
        $data = json_decode($raw, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $ok = false; 
            $errors[] = 'invalid_json';
        }
    
        // 2) UNWRAP: aceptar body/data/payload como string JSON o array
        if ($ok && is_array($data)) {
            $candidates = ['body', 'data', 'payload'];
            foreach ($candidates as $k) {
                if (!array_key_exists($k, $data)) continue;
    
                $inner = $data[$k];
    
                // Si viene urlencoded, decodificar primero
                if (is_string($inner) && preg_match('~^%7B.*%7D$~i', $inner)) {
                    $inner = urldecode($inner);
                }
    
                if (is_string($inner)) {
                    $maybe = json_decode($inner, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($maybe)) {
                        $data = $maybe; // reemplaza por el JSON real
                        break;
                    }
                } elseif (is_array($inner)) {
                    $data = $inner; // ya vino como objeto
                    break;
                }
            }
        }
    
        // 2.1) Verificación post-unwrap
        if ($ok && !is_array($data)) {
            $ok = false;
            $errors[] = 'invalid_payload_after_unwrap';
        }
    
        // 3) NORMALIZACIÓN (modo simple → template)
        if ($ok) {
            // Siempre queremos "waba" + "template"
            $data['mode'] = 'waba';
            $data['type'] = 'template';
    
            if (empty($data['template'])) {
                $tpl = self::build_template_from_simple($data);
                if (!$tpl) {
                    $ok = false;
                    $errors[] = 'template_build_failed';
                } else {
                    $data['template'] = $tpl;
                }
            }
        }
    
        // 4) Saneado/validación de 'to'
        if ($ok) {
            if (empty($data['to'])) {
                $ok = false; 
                $errors[] = 'missing_to';
            } else {
                $data['to'] = preg_replace('~\s+~', '', (string)$data['to']); // quita espacios
            }
        }
    
        // 🔎 DEBUG: ver cómo quedó normalizado ANTES de despachar
        if ($ok && function_exists('error_log')) {
            $toLog = [
                'mode' => $data['mode'] ?? '',
                'type' => $data['type'] ?? '',
                'to'   => $data['to']   ?? '',
                'name' => $data['name'] ?? ($data['template']['name'] ?? ''),
                'lang' => $data['lang'] ?? ($data['template']['language']['code'] ?? ''),
            ];
            error_log('[7c-mwb ingest normalized] ' . wp_json_encode($toLog, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        }
    
        // 5) Despacho a WABA (una sola vez)
        $dispatch = null;
        if ($ok && ($data['mode'] ?? '') === 'waba') {
            $dispatch = self::send_waba($data);
            if (isset($dispatch['ok']) && !$dispatch['ok']) {
                $ok = false;
                $errors[] = 'dispatch_error';
                if (!empty($dispatch['error'])) {
                    $errors[] = $dispatch['error'];
                }
            }
        }
    
        // 6) Log final (opcional; evita datos sensibles)
        if (function_exists('error_log')) {
            $opt         = get_option(Keys::OPTION, []);
            $header_name = !empty($opt[Keys::SIGNATURE_HEADER]) ? (string)$opt[Keys::SIGNATURE_HEADER] : 'X-Signature';
            error_log('[7c-mwb ingest] ok=' . ($ok?'1':'0') . ' header=' . $header_name . ' mode=' . ($data['mode'] ?? '') . ' raw=' . $raw);
        }
    
        return new WP_REST_Response([
            'ok'       => $ok,
            'errors'   => $errors,
            'echo'     => $ok ? $data : null,
            'dispatch' => $dispatch,
        ], 200);
    }


    /**
     * Verifica autenticación del request:
     * - Modo 'token': compara header fijo (p.ej. X-Api-Key) con INGEST_STATIC_TOKEN
     * - Modo 'hmac' : valida HMAC base64 del body con INGEST_SECRET
     */
    private static function verify_auth(WP_REST_Request $req, string $raw_body): void
    {
        $opt  = get_option(Keys::OPTION, []);
        $mode = $opt[Keys::INGEST_AUTH_MODE] ?? 'hmac';
        $hdr  = $opt[Keys::SIGNATURE_HEADER] ?? 'X-Signature';

        // En WP, get_header() es case-insensitive
        $provided = $req->get_header($hdr);

        if ($mode === 'token') {
            $token = (string)($opt[Keys::INGEST_STATIC_TOKEN] ?? '');
            if ($token === '' || $provided === '' || !hash_equals($token, $provided)) {
                wp_send_json_error(['error' => 'Unauthorized (token)'], 401);
            }
            return;
        }

        // --- HMAC (por defecto) ---
        $secret = (string)($opt[Keys::INGEST_SECRET] ?? '');

        // Fallbacks de nombre de header comunes (por compatibilidad)
        if ($provided === '') {
            $provided = $req->get_header('X-7C-HMAC')
                     ?:  $req->get_header('X-Hub-Signature')
                     ?:  $req->get_header('X-Signature');
        }

        if ($secret === '' || $provided === '') {
            wp_send_json_error(['error' => 'Unauthorized (hmac: missing)'], 401);
        }

        $calc = base64_encode(hash_hmac('sha256', $raw_body, $secret, true));
        if (!hash_equals($calc, (string)$provided)) {
            wp_send_json_error(['error' => 'Unauthorized (hmac: invalid)'], 401);
        }
    }

    private static function send_waba(array $data): array
    {
        // Lee config Meta (guardada en tu Settings UI)
        $opt     = get_option(Keys::OPTION, []);
        $token   = $opt[Keys::META_TOKEN] ?? '';
        $phoneId = $opt[Keys::META_PHONE_ID] ?? '';
        $version = $opt[Keys::META_API_VERSION] ?? 'v23.0';

        if (!$token || !$phoneId) {
            return ['ok' => false, 'error' => 'missing_meta_credentials'];
        }

        $url = "https://graph.facebook.com/{$version}/{$phoneId}/messages";

        // Construir payload según text o template
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => (string)($data['to'] ?? ''),
        ];

        if (($data['type'] ?? '') === 'template' && !empty($data['template'])) {
            $payload['type']     = 'template';
            $payload['template'] = $data['template'];
        } else {
            // default text
            $payload['type'] = 'text';
            $payload['text'] = ['body' => (string)($data['message'] ?? '')];
        }

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'timeout' => 20,
        ];

        $resp = wp_remote_post($url, $args);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'error' => $resp->get_error_message(), 'request' => $payload];
        }

        $body_raw = wp_remote_retrieve_body($resp);
        $code     = wp_remote_retrieve_response_code($resp);

        $json = json_decode($body_raw, true);
        return [
            'ok'       => $code >= 200 && $code < 300,
            'status'   => $code,
            'request'  => $payload,
            'response' => $json ?: $body_raw,
        ];
    }

    private static function build_template_from_simple(array $data): ?array
    {
        // 1) name / lang obligatorios
        $name = $data['name'] ?? $data['template_name'] ?? null;
        $lang = $data['lang'] ?? $data['language'] ?? $data['language_code'] ?? null;
        if (!$name || !$lang) return null;
    
        // 2) Vars de BODY
        $vars = $data['vars'] ?? $data['template_variables'] ?? [];
        if (is_string($vars)) {
            $vars = array_map('trim', explode(',', $vars));
        }
        if (!is_array($vars)) $vars = [];
    
        $components = [];
    
        // 3) HEADER (opcional)
        // Solo admite uno: text | image | video | document
        if (!empty($data['header']) && is_array($data['header'])) {
            $h = $data['header'];
    
            // Prioridad simple: media > text (puedes invertirla si prefieres)
            if (!empty($h['image']) && is_array($h['image'])) {
                $p = ['type' => 'image', 'image' => []];
                if (!empty($h['image']['id']))   $p['image']['id']   = (string)$h['image']['id'];
                if (!empty($h['image']['link'])) $p['image']['link'] = (string)$h['image']['link'];
                if (!empty($p['image'])) {
                    $components[] = ['type' => 'header', 'parameters' => [$p]];
                }
            } elseif (!empty($h['video']) && is_array($h['video'])) {
                $p = ['type' => 'video', 'video' => []];
                if (!empty($h['video']['id']))   $p['video']['id']   = (string)$h['video']['id'];
                if (!empty($h['video']['link'])) $p['video']['link'] = (string)$h['video']['link'];
                if (!empty($p['video'])) {
                    $components[] = ['type' => 'header', 'parameters' => [$p]];
                }
            } elseif (!empty($h['document']) && is_array($h['document'])) {
                $p = ['type' => 'document', 'document' => []];
                if (!empty($h['document']['id']))   $p['document']['id']   = (string)$h['document']['id'];
                if (!empty($h['document']['link'])) $p['document']['link'] = (string)$h['document']['link'];
                if (!empty($p['document'])) {
                    $components[] = ['type' => 'header', 'parameters' => [$p]];
                }
            } elseif (isset($h['text']) && $h['text'] !== '') {
                // Header TEXT (con o sin {{}}). Si tiene {{1}} etc., el valor va como parámetro de texto
                $components[] = [
                    'type'       => 'header',
                    'parameters' => [
                        ['type' => 'text', 'text' => (string)$h['text']]
                    ]
                ];
            }
        }
    
        // 4) BODY
        $bodyParams = [];
        foreach ($vars as $v) {
            $bodyParams[] = ['type' => 'text', 'text' => (string)$v];
        }
        $bodyComponent = ['type' => 'body'];
        if (!empty($bodyParams)) {
            $bodyComponent['parameters'] = $bodyParams;
        }
        $components[] = $bodyComponent;
    
        // 5) BUTTONS (opcionales, típicamente URL con variable)
        if (!empty($data['buttons']) && is_array($data['buttons'])) {
            foreach ($data['buttons'] as $btn) {
                if (!is_array($btn)) continue;
                $subType = isset($btn['sub_type']) ? (string)$btn['sub_type'] : '';
                $index   = isset($btn['index']) ? (string)$btn['index'] : '0';
                $text    = isset($btn['text']) ? (string)$btn['text'] : '';
    
                // Soportamos solo URL dinámico con parámetro (type text)
                if ($subType === 'url' && $text !== '') {
                    $components[] = [
                        'type'     => 'button',
                        'sub_type' => 'url',
                        'index'    => $index,
                        'parameters' => [
                            ['type' => 'text', 'text' => $text]
                        ],
                    ];
                }
            }
        }
    
        return [
            'name'       => (string)$name,
            'language'   => ['code' => (string)$lang],
            'components' => $components,
        ];
    }

}
