<?php
namespace SevenC\MWB\Settings;

class Defaults {
    public static function values(): array {
        return [
            Keys::MODE              => 'bsp',
            Keys::SIGNATURE_HEADER  => 'X-Signature',
            Keys::DEFAULT_LANGUAGE  => 'es',

            Keys::INGEST_ROUTE      => '/wp-json/7c-mwb/v1/ingest',
            Keys::INGEST_SECRET     => '',

            Keys::BSP_ENDPOINT      => '',
            Keys::BSP_TOKEN         => '',
            Keys::BSP_AUTH_HEADER   => 'Authorization',
            Keys::BSP_EXTRA_HEADERS => '',

            Keys::META_PHONE_ID     => '',
            Keys::META_TOKEN        => '',
            Keys::META_API_VERSION  => 'v23.0',
            Keys::META_WABA_ID => '',

        ];
    }

    public static function ensure_defaults(): void {
        $opt = get_option(Keys::OPTION);
        if (!$opt || !is_array($opt)) {
            add_option(Keys::OPTION, self::values());
        } else {
            $merged = array_merge(self::values(), $opt);
            update_option(Keys::OPTION, $merged);
        }
    }
}
