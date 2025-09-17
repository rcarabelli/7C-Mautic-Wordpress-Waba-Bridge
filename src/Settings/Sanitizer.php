<?php
namespace SevenC\MWB\Settings;

class Sanitizer {
    public static function sanitize(array $input): array {
        $out = [];

        // --- Campos simples (sin validaci車n especial) ---
        // *OJO*: Dejo fuera los que necesitan validaci車n dedicada m芍s abajo.
        $simple = [
            Keys::MODE,
            Keys::SIGNATURE_HEADER,
            Keys::DEFAULT_LANGUAGE,
            Keys::INGEST_ROUTE,
            Keys::INGEST_SECRET,
            Keys::BSP_TOKEN,
            Keys::BSP_AUTH_HEADER,
            Keys::META_TOKEN
        ];
        foreach ($simple as $k) {
            $out[$k] = isset($input[$k]) ? trim((string) $input[$k]) : '';
        }

        // --- Validaciones espec赤ficas ---

        // Modo
        if (!in_array($out[Keys::MODE], ['bsp', 'meta'], true)) {
            $out[Keys::MODE] = 'bsp';
        }

        // Header de firma
        if (!in_array($out[Keys::SIGNATURE_HEADER], ['X-Signature','X-Hub-Signature'], true)) {
            $out[Keys::SIGNATURE_HEADER] = 'X-Signature';
        }

        // Normalizar INGEST_ROUTE: aceptar URL completa pero guardar solo la ruta relativa
        if (!empty($out[Keys::INGEST_ROUTE])) {
            $v = trim((string) $out[Keys::INGEST_ROUTE]);
        
            // Si viene con http(s), extrae solo el path
            if (stripos($v, 'http://') === 0 || stripos($v, 'https://') === 0) {
                $path = parse_url($v, PHP_URL_PATH);
                $v = $path ?: $v;
            }
        
            // Asegurar que empiece con "/"
            if ($v !== '' && $v[0] !== '/') {
                $v = '/' . $v;
            }
        
            // Quitar espacios
            $v = preg_replace('~\s+~', '', $v);
        
            $out[Keys::INGEST_ROUTE] = $v;
        }


        // --- BSP (endpoint como URL v芍lida) ---
        if (!empty($input[Keys::BSP_ENDPOINT])) {
            $url = esc_url_raw(trim((string)$input[Keys::BSP_ENDPOINT]));
            if (!empty($url)) {
                $out[Keys::BSP_ENDPOINT] = $url;
            } else {
                $out[Keys::BSP_ENDPOINT] = '';
                add_settings_error(Keys::OPTION, 'bsp_endpoint', 'NAPI Endpoint URL inv芍lido. Se ignor車.');
            }
        } else {
            $out[Keys::BSP_ENDPOINT] = '';
        }

        // Headers extra (JSON)
        if (!empty($input[Keys::BSP_EXTRA_HEADERS])) {
            $decoded = json_decode($input[Keys::BSP_EXTRA_HEADERS], true);
            if (is_array($decoded)) {
                // Re-encode ordenado/can車nico
                $out[Keys::BSP_EXTRA_HEADERS] = wp_json_encode($decoded);
            } else {
                $out[Keys::BSP_EXTRA_HEADERS] = '';
                add_settings_error(Keys::OPTION, 'bsp_extra_headers', 'Headers extra (JSON) inv芍lido. Se ignor車.');
            }
        } else {
            $out[Keys::BSP_EXTRA_HEADERS] = '';
        }

        // --- Meta Cloud API ---

        // Phone Number ID: d赤gitos o vac赤o
        if (!empty($input[Keys::META_PHONE_ID])) {
            $pn = trim((string)$input[Keys::META_PHONE_ID]);
            if ($pn !== '' && ctype_digit($pn)) {
                $out[Keys::META_PHONE_ID] = $pn;
            } else {
                $out[Keys::META_PHONE_ID] = '';
                add_settings_error(Keys::OPTION, 'meta_phone_id', 'Phone Number ID debe contener solo d赤gitos. Se vaci車 el campo.');
            }
        } else {
            $out[Keys::META_PHONE_ID] = '';
        }

        // WABA ID: d赤gitos o vac赤o (opcional, pero 迆til para administrar plantillas/n迆meros)
        if (!empty($input[Keys::META_WABA_ID])) {
            $wid = trim((string)$input[Keys::META_WABA_ID]);
            if ($wid !== '' && ctype_digit($wid)) {
                $out[Keys::META_WABA_ID] = $wid;
            } else {
                $out[Keys::META_WABA_ID] = '';
                add_settings_error(Keys::OPTION, 'meta_waba_id', 'WABA ID debe contener solo d赤gitos. Se vaci車 el campo.');
            }
        } else {
            $out[Keys::META_WABA_ID] = '';
        }

        // API Version: formato tipo v23.0 (permitimos vNN o vNN.N)
        if (!empty($input[Keys::META_API_VERSION])) {
            $ver = trim((string)$input[Keys::META_API_VERSION]);
            if (preg_match('~^v\d+(?:\.\d+)?$~', $ver)) {
                $out[Keys::META_API_VERSION] = $ver;
            } else {
                // Si llega algo raro, forzamos valor por defecto
                $out[Keys::META_API_VERSION] = 'v23.0';
                add_settings_error(Keys::OPTION, 'meta_api_version', 'API Version inv芍lida. Se ajust車 a v23.0.');
            }
        } else {
            $out[Keys::META_API_VERSION] = 'v23.0';
        }

        return $out;
    }
}
