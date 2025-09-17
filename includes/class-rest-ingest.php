<?php
namespace SevenC\MWB\REST;

use WP_REST_Request;
use WP_REST_Response;
use SevenC\MWB\Settings\Registry;
use SevenC\MWB\Settings\Keys;

class Ingest_Controller {

    public static function register(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('7c-mwb/v1', '/ingest', [
                'methods'             => 'POST',
                'permission_callback' => '__return_true', // seguridad via HMAC
                'callback'            => [__CLASS__, 'handle'],
            ]);
        });
    }

    public static function handle(WP_REST_Request $req): WP_REST_Response
    {
        // ⚠️ Siempre devolver 200 a Mautic
        $ok     = true;
        $errors = [];

        // 1) Obtener SECRET desde settings
        $secret = null;
        try {
            // Si usas Registry/Keys en tu settings:
            $secret = Registry::get(Keys::SHARED_SECRET);
        } catch (\Throwable $e) {
            // Fallback a option directa si hiciera falta:
            $secret = $secret ?: get_option('sevenc_mwb_shared_secret');
        }

        if (!$secret) {
            $ok = false;
            $errors[] = 'shared_secret_missing';
            return self::json(['ok' => $ok, 'errors' => $errors], 200);
        }

        // 2) Validar HMAC (Base64(HMAC_SHA256(raw_body, secret)))
        $raw = $req->get_body();
        $sig = $req->get_header('X-7C-HMAC');

        if (!$sig) {
            $ok = false;
            $errors[] = 'missing_signature';
        } else {
            $calc = base64_encode(hash_hmac('sha256', $raw, $secret, true));
            if (!hash_equals($calc, $sig)) {
                $ok = false;
                $errors[] = 'invalid_signature';
            }
        }

        // 3) Parsear JSON
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $ok = false;
            $errors[] = 'invalid_json';
            return self::json(['ok' => $ok, 'errors' => $errors], 200);
        }

        // 4) Extraer campos
        $mode    = $data['mode']    ?? null;   // "waba" | "bsp"
        $to      = $data['to']      ?? null;
        $type    = $data['type']    ?? null;   // "text" | "template"
        $message = $data['message'] ?? null;
        $tpl     = $data['template'] ?? null;

        // 5) Log mínimo para debugging (puedes cambiarlo por tu logger)
        if (function_exists('error_log')) {
            error_log('[7c-mwb ingest] ok=' . ($ok?'1':'0') . ' mode=' . $mode . ' to=' . $to . ' type=' . $type . ' raw=' . $raw);
        }

        // 6) Si la firma es válida, aquí despachamos (stubs por ahora)
        if ($ok) {
            try {
                if ($mode === 'waba') {
                    // TODO: implementar envío real a Graph API (vía wp_remote_post)
                    // self::send_waba($to, $type, $message, $tpl);
                } elseif ($mode === 'bsp') {
                    // TODO: implementar envío a Support Board API
                    // self::send_bsp($to, $type, $message, $tpl);
                }
            } catch (\Throwable $e) {
                $ok = false;
                $errors[] = 'dispatch_error';
                $errors[] = $e->getMessage();
            }
        }

        // 7) Siempre 200 para Mautic
        return self::json([
            'ok'      => $ok,
            'errors'  => $errors,
            'echo'    => $ok ? $data : null, // útil al probar
        ], 200);
    }

    private static function json($arr, int $code = 200): WP_REST_Response
    {
        return new WP_REST_Response($arr, $code);
    }

    /* === Stubs para implementar luego ===
    private static function send_waba(string $to, ?string $type, ?string $message, ?array $tpl): void { }
    private static function send_bsp(string $to, ?string $type, ?string $message, ?array $tpl): void { }
    */
}
