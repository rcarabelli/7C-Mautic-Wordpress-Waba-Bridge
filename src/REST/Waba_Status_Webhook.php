<?php
// src/REST/Waba_Status_Webhook.php
namespace SevenC\MWB\REST;

use WP_REST_Request;
use WP_REST_Response;

class Waba_Status_Webhook {
    public static function register(): void {
        add_action('rest_api_init', function(){
            register_rest_route('7c-mwb/v1', '/waba-webhook', [
                'methods'  => ['GET','POST'],
                'callback' => [__CLASS__, 'handle'],
                'permission_callback' => '__return_true',
            ]);
        });
    }
    public static function handle(WP_REST_Request $req): WP_REST_Response {
        // GET: este WP no es el callback principal, respondemos simple
        if ($req->get_method() === 'GET') {
            return new WP_REST_Response('OK', 200);
        }
    
        // POST: estados reales
        $raw = $req->get_body();
        $json = json_decode($raw, true);
    
        if (function_exists('error_log')) {
            // Log crudo (por si necesitas inspección completa)
            error_log('[WABA STATUS RAW] ' . $raw);
    
            // Log resumido (fácil de leer)
            $statuses = $json['entry'][0]['changes'][0]['value']['statuses'] ?? [];
            foreach ($statuses as $st) {
                $mid    = $st['id']            ?? '';
                $status = $st['status']        ?? '';
                $errs   = $st['errors'][0]['message'] ?? '';
                error_log(sprintf('[WABA STATUS] id=%s status=%s error=%s', $mid, $status, $errs));
            }
        }
    
        return new WP_REST_Response(['ok'=>true], 200);
    }
}