<?php
namespace SevenC\MWB\Settings;

class Registry {

    public static function get(string $key, $default = '') {
        $opts = get_option(Keys::OPTION, []);
        return isset($opts[$key]) ? $opts[$key] : $default;
    }

    public static function register(): void {
        register_setting(Keys::OPTION, Keys::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [Sanitizer::class, 'sanitize'],
            'default' => Defaults::values()
        ]);

        // 1) Modo & Preferencias
        add_settings_section('mwb_sec_mode', 'Modo & Preferencias', '__return_false', Keys::OPTION);
        add_settings_field(Keys::MODE, 'Modo de envío', [__CLASS__, 'field_mode'], Keys::OPTION, 'mwb_sec_mode');
        add_settings_field(Keys::DEFAULT_LANGUAGE, 'Idioma por defecto', [__CLASS__, 'field_text'], Keys::OPTION, 'mwb_sec_mode', [
            'key'=>Keys::DEFAULT_LANGUAGE,
            'placeholder'=>'es',
            'desc'=>'Idioma base usado en mensajes si no se especifica otro.'
        ]);
        add_settings_field(Keys::SIGNATURE_HEADER, 'Header de firma HMAC', [__CLASS__, 'field_select'], Keys::OPTION, 'mwb_sec_mode', [
            'key'=>Keys::SIGNATURE_HEADER,
            'choices'=>[
                'X-Signature'=>'X-Signature',
                'X-Hub-Signature'=>'X-Hub-Signature'
            ],
            'desc'=>'Es el <strong>nombre del header</strong> donde Mautic enviará la firma HMAC-SHA256 del body. Este plugin recalcula la firma con el Shared Secret y compara.'
        ]);

        // 2) Ingest (Mautic)
        add_settings_section('mwb_sec_ingest', 'Ingest desde Mautic (firma HMAC)', function(){
            echo '<p>Configura la ruta REST y el secreto que Mautic usará para firmar el body.</p>';
        }, Keys::OPTION);
        add_settings_field(Keys::INGEST_ROUTE, 'Ruta REST de ingest', [__CLASS__, 'field_text'], Keys::OPTION, 'mwb_sec_ingest', [
            'key'        => Keys::INGEST_ROUTE,
            'placeholder'=> '/wp-json/7c-mwb/v1/ingest',
            'desc'       => 'Es la <strong>ruta del endpoint del plugin</strong>. '
                          . 'Ejemplo de URL completa: <code>' . esc_html( home_url('/wp-json/7c-mwb/v1/ingest') ) . '</code>'
        ]);

        add_settings_field(Keys::INGEST_SECRET, 'Shared Secret (HMAC)', [__CLASS__, 'field_password'], Keys::OPTION, 'mwb_sec_ingest', [
            'key'=>Keys::INGEST_SECRET,
            'placeholder'=>'*************',
            'desc'=>'Clave privada que defines tú mismo y replicas en Mautic. Se usa para firmar/verificar el body (HMAC-SHA256). Recomendada de 32–64+ caracteres. Ejemplo para generar: <code>openssl rand -base64 48</code>'
        ]);

        // 3) BSP (board.support)
        add_settings_section('mwb_sec_bsp', 'Proveedor BSP (board.support / NAPI)', function(){
            echo '<p>Configura aquí tu puente con board.support.</p>';
        }, Keys::OPTION);
        add_settings_field(Keys::BSP_ENDPOINT, 'NAPI Endpoint URL', [__CLASS__, 'field_text'], Keys::OPTION, 'mwb_sec_bsp', [
            'key'        => Keys::BSP_ENDPOINT,
            'placeholder'=> 'https://tu-dominio.com/support/include/api.php',
            'desc'       => 'URL del Web API de Support Board. Usa el archivo <code>/support/include/api.php</code>. '
                          . 'Este API recibe <code>POST</code> con <code>token</code> y <code>function</code> (p. ej., '
                          . '<code>whatsapp-send-message</code>, <code>whatsapp-send-template</code>).'
        ]);
        add_settings_field(Keys::BSP_TOKEN, 'NAPI Token', [__CLASS__, 'field_password'], Keys::OPTION, 'mwb_sec_bsp', [
            'key'=>Keys::BSP_TOKEN,
            'placeholder'=>'eyJ...',
            'desc'=>'Token de acceso desde tu panel de board.support. Se usa en el header <code>Authorization: Bearer &lt;token&gt;</code>.'
        ]);
        add_settings_field(Keys::BSP_AUTH_HEADER, 'Header de auth', [__CLASS__, 'field_text'], Keys::OPTION, 'mwb_sec_bsp', [
            'key'        => Keys::BSP_AUTH_HEADER,
            'placeholder'=> 'Authorization',
            'desc'       => 'Opcional en Support Board. Normalmente el token va en el body como <code>token</code>. '
                          . 'Déjalo vacío a menos que tu instalación requiera encabezado.'
        ]);

        add_settings_field(Keys::BSP_EXTRA_HEADERS, 'Headers extra (JSON)', [__CLASS__, 'field_textarea'], Keys::OPTION, 'mwb_sec_bsp', [
            'key'=>Keys::BSP_EXTRA_HEADERS,
            'placeholder'=>'{"X-Account":"abc"}',
            'desc'=>'Solo si tu BSP lo pide. Debe ser JSON válido. Ejemplo: <code>{"X-Account":"tu-cuenta"}</code>.'
        ]);

        // 4) Meta (Cloud API)
        add_settings_section('mwb_sec_meta', 'Meta Cloud API (directo a WABA)', function(){
            echo '<p>Si en el futuro envías directo a Meta, usa estos campos.</p>';
        }, Keys::OPTION);
        
        add_settings_field(Keys::META_WABA_ID, 'WABA ID', [__CLASS__, 'field_text'], Keys::OPTION, 'mwb_sec_meta', [
            'key'=>Keys::META_WABA_ID,
            'placeholder'=>'123456789012345',
            'desc'=>'WhatsApp Business Account ID. Opcional para envío; requerido para administrar plantillas y números vía Graph API.'
        ]);
        
        add_settings_field(Keys::META_PHONE_ID, 'Phone Number ID', [__CLASS__, 'field_text'], Keys::OPTION, 'mwb_sec_meta', [
            'key'=>Keys::META_PHONE_ID,
            'placeholder'=>'123456789012345',
            'desc'=>'Se obtiene en Business Manager → WhatsApp (Phone numbers).'
        ]);
        
        add_settings_field(Keys::META_TOKEN, 'Access Token', [__CLASS__, 'field_password'], Keys::OPTION, 'mwb_sec_meta', [
            'key'=>Keys::META_TOKEN,
            'placeholder'=>'EAAG...',
            'desc'=>'Token de acceso generado en Graph API Explorer o en tu App en Business Manager.'
        ]);
        
        add_settings_field(Keys::META_API_VERSION, 'API Version', [__CLASS__, 'field_text'], Keys::OPTION, 'mwb_sec_meta', [
            'key'=>Keys::META_API_VERSION,
            'placeholder'=>'v23.0',
            'desc'=>'Versión de la Graph API que usarás (ejemplo: v23.0).'
        ]);
    }

    // Render helpers (inputs)
    public static function field_mode(): void {
        $val = self::get(Keys::MODE, 'bsp'); ?>
        <select name="<?php echo esc_attr(Keys::OPTION); ?>[<?php echo esc_attr(Keys::MODE); ?>]">
            <option value="bsp"  <?php selected($val,'bsp');  ?>>BSP (board.support)</option>
            <option value="meta" <?php selected($val,'meta'); ?>>Meta Cloud API (directo)</option>
        </select>
        <p class="description">Selecciona si usarás board.support como puente o conexión directa a Meta.</p>
        <?php
    }

    public static function field_text(array $a): void {
        $k = esc_attr($a['key']); $val = esc_attr(self::get($k,'')); $ph = esc_attr($a['placeholder'] ?? '');
        printf('<input type="text" class="regular-text" name="%s[%s]" value="%s" placeholder="%s" />',
            esc_attr(Keys::OPTION), $k, $val, $ph
        );
        if (!empty($a['desc'])) {
            echo '<p class="description">'.$a['desc'].'</p>';
        }
    }

    public static function field_password(array $a): void {
        $k = esc_attr($a['key']); $val = esc_attr(self::get($k,'')); $ph = esc_attr($a['placeholder'] ?? '');
        printf('<input type="password" class="regular-text" name="%s[%s]" value="%s" placeholder="%s" autocomplete="new-password" />',
            esc_attr(Keys::OPTION), $k, $val, $ph
        );
        if (!empty($a['desc'])) {
            echo '<p class="description">'.$a['desc'].'</p>';
        }
    }

    public static function field_select(array $a): void {
        $k = esc_attr($a['key']); $choices = $a['choices'] ?? []; $val = self::get($k,'');
        echo '<select name="'.esc_attr(Keys::OPTION).'['.$k.']">';
        foreach($choices as $ck=>$label){
            printf('<option value="%s" %s>%s</option>',
                esc_attr($ck), selected($val,$ck,false), esc_html($label)
            );
        }
        echo '</select>';
        if (!empty($a['desc'])) {
            echo '<p class="description">'.$a['desc'].'</p>';
        }
    }

    public static function field_textarea(array $a): void {
        $k = esc_attr($a['key']); $val = esc_textarea(self::get($k,'')); $ph = esc_attr($a['placeholder'] ?? '');
        printf('<textarea class="large-text" rows="4" name="%s[%s]" placeholder="%s">%s</textarea>',
            esc_attr(Keys::OPTION), $k, $ph, $val
        );
        if (!empty($a['desc'])) {
            echo '<p class="description">'.$a['desc'].'</p>';
        }
    }
}
