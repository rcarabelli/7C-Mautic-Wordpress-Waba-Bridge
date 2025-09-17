<?php
namespace SevenC\MWB\Setup;

use SevenC\MWB\Settings\Keys;

class Installer {
    public const OPTION_VERSION = 'sevenc_mwb_options_version';
    public const CODE_VERSION   = 2; // súbelo cuando cambies migraciones

    public static function activate(): void {
        // Garantiza defaults al activar el plugin
        self::ensure_defaults(true);
        update_option(self::OPTION_VERSION, self::CODE_VERSION);
    }

    public static function maybe_upgrade(): void {
        $stored = (int) get_option(self::OPTION_VERSION, 0);
        if ($stored < self::CODE_VERSION) {
            self::ensure_defaults(false);
            update_option(self::OPTION_VERSION, self::CODE_VERSION);
        }
    }

    private static function ensure_defaults(bool $on_activation): void {
        $opt = get_option(Keys::OPTION, []);
        if (!is_array($opt)) $opt = [];

        // 1) Header por defecto (único para ambos modos)
        if (empty($opt[Keys::SIGNATURE_HEADER])) {
            $opt[Keys::SIGNATURE_HEADER] = 'X-Api-Key'; // header fijo por defecto
        }

        // 2) Modo de auth por defecto: TOKEN (plug & play)
        if (empty($opt[Keys::INGEST_AUTH_MODE])) {
            $opt[Keys::INGEST_AUTH_MODE] = 'token'; // token | hmac | both
        }

        // 3) Static token:
        //    - Si ya existe, respetar.
        //    - Si no existe y hay shared_secret legacy, reutilizarlo como token fijo (tu "un solo secret").
        //    - Si no existe nada, generar uno seguro.
        if (empty($opt[Keys::INGEST_STATIC_TOKEN])) {
            // intentar reutilizar ingest_shared_secret si existe
            $legacySecret = $opt[Keys::INGEST_SECRET] ?? get_option('sevenc_mwb_shared_secret');
            if (!empty($legacySecret) && is_string($legacySecret)) {
                $opt[Keys::INGEST_STATIC_TOKEN] = $legacySecret;
            } else {
                $opt[Keys::INGEST_STATIC_TOKEN] = self::random_token();
            }
        }

        // 4) Ruta ingest por defecto
        if (empty($opt[Keys::INGEST_ROUTE])) {
            $opt[Keys::INGEST_ROUTE] = '/wp-json/7c-mwb/v1/ingest';
        }

        // 5) Idioma por defecto (no crítico)
        if (empty($opt[Keys::DEFAULT_LANGUAGE])) {
            $opt[Keys::DEFAULT_LANGUAGE] = 'es';
        }

        update_option(Keys::OPTION, $opt);
    }

    private static function random_token(): string {
        // 48 bytes -> ~64 base64 chars
        if (function_exists('random_bytes')) {
            return base64_encode(random_bytes(48));
        }
        return base64_encode(openssl_random_pseudo_bytes(48));
    }
}
