<?php
namespace SevenC\MWB;

use SevenC\MWB\Admin\Menu;
use SevenC\MWB\Admin\TemplateHelper;
use SevenC\MWB\REST\Ingest_Controller;
use SevenC\MWB\Settings\Keys;
use SevenC\MWB\Settings\Registry;
use SevenC\MWB\REST\Waba_Status_Webhook;

class Plugin {

    public function boot(): void
    {
        /**
         * 1) Migración temprana:
         *    Normaliza INGEST_ROUTE si fue guardado como URL completa o sin slash inicial.
         *    Se ejecuta antes de registrar settings.
         */
        add_action('admin_init', function () {
            $opt = get_option(Keys::OPTION);
            if (is_array($opt) && !empty($opt[Keys::INGEST_ROUTE])) {
                $v = trim((string) $opt[Keys::INGEST_ROUTE]);
    
                // Si viene con http(s), extrae solo el path
                if (stripos($v, 'http://') === 0 || stripos($v, 'https://') === 0) {
                    $path = parse_url($v, PHP_URL_PATH);
                    if ($path) {
                        $v = '/' . ltrim($path, '/');
                        $v = preg_replace('~\s+~', '', $v); // sin espacios
                        $opt[Keys::INGEST_ROUTE] = $v;
                        update_option(Keys::OPTION, $opt);
                    }
                }
                // Si no empieza con "/", forzarlo
                elseif ($v !== '' && $v[0] !== '/') {
                    $opt[Keys::INGEST_ROUTE] = '/' . ltrim($v, '/');
                    update_option(Keys::OPTION, $opt);
                }
            }
        }, 5);
    
        /**
         * 2) Publicar REST de ingest (fuera de admin_init)
         */
        Ingest_Controller::register();
    
        /**
         * 2b) Publicar webhook de estatus WABA
         */
        Waba_Status_Webhook::register();
    
        /**
         * 3) Registrar ajustes (pantallas/settings)
         */
        add_action('admin_init', [Registry::class, 'register']);
    
        /**
         * 4) Menús de administración
         */
        add_action('admin_menu', [Menu::class, 'register']);
        add_action('admin_menu', [TemplateHelper::class, 'register_menu']); // Submenú "Template → Webhook"
    
        /**
         * 5) Assets admin + AJAX
         */
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('wp_ajax_sevenc_mwb_send_test', [$this, 'ajax_send_test']);
    }


    public function enqueue_admin($hook): void
    {
        // Carga CSS/JS solo en nuestras pantallas
        if (strpos($hook, 'sevenc-mwb') === false) return;

        wp_enqueue_style(
            'sevenc-mwb-admin',
            SEVENC_MWB_URL . 'assets/admin.css',
            [],
            SEVENC_MWB_VER
        );

        wp_enqueue_script(
            'sevenc-mwb-admin',
            SEVENC_MWB_URL . 'assets/admin.js',
            ['jquery'],
            SEVENC_MWB_VER,
            true
        );

        // Pasar nonce y ajaxurl al JS
        wp_localize_script(
            'sevenc-mwb-admin',
            'sevenc_mwb',
            [
                'nonce'   => wp_create_nonce('sevenc_mwb_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
            ]
        );
    }

    public function ajax_send_test(): void
    {
        check_ajax_referer('sevenc_mwb_nonce');

        $number = sanitize_text_field($_POST['number'] ?? '');
        if (!$number) {
            wp_send_json_error(['message' => 'Número vacío']);
        }

        $opt     = get_option(Keys::OPTION);
        $token   = $opt[Keys::META_TOKEN] ?? '';
        $phoneId = $opt[Keys::META_PHONE_ID] ?? '';
        $version = $opt[Keys::META_API_VERSION] ?? 'v23.0';

        if (!$token || !$phoneId) {
            wp_send_json_error(['message' => 'Configura primero Token y Phone Number ID']);
        }

        $url = "https://graph.facebook.com/$version/$phoneId/messages";
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $number,
            'type' => 'template',
            'template' => [
                'name' => 'hello_world',
                'language' => ['code' => 'en_US'],
            ],
        ];

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
            wp_send_json_error([
                'message' => $resp->get_error_message(),
                'debug'   => $args
            ]);
        }

        $body_raw = wp_remote_retrieve_body($resp);
        $body = json_decode($body_raw, true);

        wp_send_json_success([
            'request'      => $payload,
            'response_raw' => $body_raw,
            'response'     => $body,
        ]);
    }
}
