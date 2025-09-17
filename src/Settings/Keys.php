<?php
namespace SevenC\MWB\Settings;

class Keys {
    public const OPTION = 'sevenc_mwb_options';

    // Modo de autenticación del ingest: hmac | token
    public const INGEST_AUTH_MODE   = 'ingest_auth_mode';     // default: hmac

    // Header común (puede usarse para HMAC o Token)
    public const SIGNATURE_HEADER   = 'signature_header';     // ej: X-Signature o X-Api-Key

    // HMAC
    public const INGEST_SECRET      = 'ingest_shared_secret';

    // Token fijo
    public const INGEST_STATIC_TOKEN = 'ingest_static_token';

    // Ruta
    public const INGEST_ROUTE       = 'ingest_route';

    // Meta / BSP (igual que ya tienes)
    public const MODE               = 'mode';
    public const DEFAULT_LANGUAGE   = 'default_language';
    public const BSP_ENDPOINT       = 'bsp_endpoint';
    public const BSP_TOKEN          = 'bsp_token';
    public const BSP_AUTH_HEADER    = 'bsp_auth_header';
    public const BSP_EXTRA_HEADERS  = 'bsp_extra_headers';
    public const META_PHONE_ID      = 'meta_phone_id';
    public const META_TOKEN         = 'meta_token';
    public const META_API_VERSION   = 'meta_api_version';
    public const META_WABA_ID       = 'meta_waba_id';
}