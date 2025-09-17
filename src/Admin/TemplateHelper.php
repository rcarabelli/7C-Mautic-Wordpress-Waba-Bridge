<?php
namespace SevenC\MWB\Admin;

use SevenC\MWB\Settings\Keys;

class TemplateHelper {

    public static function register_menu(): void {
        add_submenu_page(
            'sevenc-mwb',
            'Template → Webhook',
            'Template → Webhook',
            'manage_options',
            'sevenc-mwb-helper',
            [__CLASS__, 'render']
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) wp_die('Insufficient permissions');

        $endpoint = home_url('/wp-json/7c-mwb/v1/ingest');
        $opt      = get_option(Keys::OPTION, []);

        $mode = $opt[Keys::INGEST_AUTH_MODE] ?? 'token';   // token | hmac | both
        $hdr  = $opt[Keys::SIGNATURE_HEADER] ?? 'X-Api-Key';

        // Form
        $to   = sanitize_text_field($_POST['to']   ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $lang = sanitize_text_field($_POST['lang'] ?? 'es_ES');
        $vars = trim((string)($_POST['vars'] ?? ''));
        $vars_array = preg_split('~[\r\n,]+~', $vars, -1, PREG_SPLIT_NO_EMPTY);
        
        $header_type = sanitize_text_field($_POST['header_type'] ?? 'none'); // none|text|image|video|document
        $header_text = trim((string)($_POST['header_text'] ?? ''));
        $header_media_link = trim((string)($_POST['header_media_link'] ?? ''));
        $header_media_id   = trim((string)($_POST['header_media_id'] ?? ''));
        
        $btn_index  = sanitize_text_field($_POST['btn_index'] ?? '0');
        $btn_text   = trim((string)($_POST['btn_text'] ?? ''));
        $btn_enable = isset($_POST['btn_enable']) ? true : false;
        
        $body = ['to'=>$to,'name'=>$name,'lang'=>$lang];
        if (!empty($vars_array)) $body['vars'] = array_values(array_map('trim',$vars_array));
        
        // Header opcional
        $header = [];
        switch ($header_type) {
            case 'text':
                if ($header_text !== '') $header['text'] = $header_text;
                break;
            case 'image':
                $media = [];
                if ($header_media_id !== '')   $media['id']   = $header_media_id;
                if ($header_media_link !== '') $media['link'] = $header_media_link;
                if (!empty($media)) $header['image'] = $media;
                break;
            case 'video':
                $media = [];
                if ($header_media_id !== '')   $media['id']   = $header_media_id;
                if ($header_media_link !== '') $media['link'] = $header_media_link;
                if (!empty($media)) $header['video'] = $media;
                break;
            case 'document':
                $media = [];
                if ($header_media_id !== '')   $media['id']   = $header_media_id;
                if ($header_media_link !== '') $media['link'] = $header_media_link;
                if (!empty($media)) $header['document'] = $media;
                break;
        }
        if (!empty($header)) $body['header'] = $header;
        
        // Botón opcional (URL dinámico)
        if ($btn_enable && $btn_text !== '') {
            $body['buttons'] = [[
                'sub_type' => 'url',
                'index'    => $btn_index !== '' ? $btn_index : '0',
                'text'     => $btn_text
            ]];
        }
        
        $json_body = wp_json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        // Firma de ejemplo solo para HMAC
        $exampleSig = '';
        if ($mode !== 'token') {
            $secret = $opt[Keys::INGEST_SECRET] ?? '';
            if ($secret && $json_body) {
                $exampleSig = base64_encode(hash_hmac('sha256', $json_body, $secret, true));
            }
        }
        ?>
        <div class="wrap">
          <h1>Template → Webhook (Mautic)</h1>
          <p class="description">Completa los campos y copia/pega las instrucciones para el Webhook de Mautic.</p>
        
          <form method="post">
            <table class="form-table">
              <tr><th><label for="to">To (número)</label></th>
                  <td><input id="to" name="to" value="<?php echo esc_attr($to); ?>" class="regular-text" placeholder="51999999999"></td></tr>
        
              <tr><th><label for="name">Template name</label></th>
                  <td><input id="name" name="name" value="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="hello_world"></td></tr>
        
              <tr><th><label for="lang">Language</label></th>
                  <td><input id="lang" name="lang" value="<?php echo esc_attr($lang); ?>" class="regular-text" placeholder="en_US"></td></tr>
        
              <tr><th><label for="vars">Vars</label></th>
                  <td><textarea id="vars" name="vars" rows="3" class="large-text" placeholder="Renato, #12345, mañana 9–11am"><?php echo esc_textarea($vars); ?></textarea>
                      <p class="description">Separadas por coma o una por línea; coincide número/orden con los placeholders del body.</p></td></tr>
        
              <!-- 🔽 Campos nuevos para Header -->
              <tr><th><label for="header_type">Header</label></th>
              <td>
                <select id="header_type" name="header_type">
                  <option value="none" <?php selected(($header_type??'none'),'none'); ?>>Sin header</option>
                  <option value="text" <?php selected(($header_type??''),'text'); ?>>Texto</option>
                  <option value="image" <?php selected(($header_type??''),'image'); ?>>Imagen</option>
                  <option value="video" <?php selected(($header_type??''),'video'); ?>>Video</option>
                  <option value="document" <?php selected(($header_type??''),'document'); ?>>Documento</option>
                </select>
                <p class="description">Elige el tipo de header según el template.</p>
              </td></tr>
        
              <tr><th><label for="header_text">Header text</label></th>
              <td><input id="header_text" name="header_text" class="regular-text"
                         value="<?php echo esc_attr($header_text??''); ?>" placeholder="Título o {{1}}"></td></tr>
        
              <tr><th><label for="header_media_link">Header media link</label></th>
              <td><input id="header_media_link" name="header_media_link" class="regular-text"
                         value="<?php echo esc_attr($header_media_link??''); ?>" placeholder="https://..."></td></tr>
        
              <tr><th><label for="header_media_id">Header media id</label></th>
              <td><input id="header_media_id" name="header_media_id" class="regular-text"
                         value="<?php echo esc_attr($header_media_id??''); ?>" placeholder="MEDIA_ID (opcional)"></td></tr>
        
              <!-- 🔽 Campos nuevos para Botón dinámico -->
              <tr><th>Botón URL dinámico</th>
              <td>
                <label><input type="checkbox" name="btn_enable" <?php checked(!empty($btn_enable)); ?>> Habilitar</label><br>
                <label>Index (0..n): <input name="btn_index" value="<?php echo esc_attr($btn_index??'0'); ?>" size="3"></label><br>
                <label>Texto/var (para {{1}}): <input name="btn_text" class="regular-text"
                         value="<?php echo esc_attr($btn_text??''); ?>" placeholder="CODIGO-ENCUESTA-123"></label>
                <p class="description">Solo si tu template tiene botón URL con variable.</p>
              </td></tr>
            </table>
        
            <?php submit_button('Generar instrucciones'); ?>
          </form>
        
          <?php if ($to && $name && $lang): ?>
          <hr>
          <h2>1) Mautic → Webhook Action</h2>
          <p><strong>URL:</strong> <?php echo esc_html($endpoint); ?></p>
        
          <p><strong>Headers:</strong></p>
          <pre><code>Content-Type: application/json
        <?php
        if ($mode === 'token' || $mode === 'both') {
            $token = (string)($opt[Keys::INGEST_STATIC_TOKEN] ?? '');
            echo esc_html($hdr) . ': ' . esc_html($token);
        } else {
            echo esc_html($hdr) . ': &lt;FIRMA HMAC EN BASE64&gt;';
        }
        ?></code></pre>
        
          <p><strong>Body (JSON):</strong></p>
          <pre><code><?php echo esc_html($json_body); ?></code></pre>
        
          <?php if ($mode === 'token' || $mode === 'both'): ?>
            <p class="description">En modo <strong>Token</strong> el header es fijo (plug &amp; play). Úsalo en todas tus campañas.</p>
            <!-- 🔹 cURL de prueba (TOKEN) -->
            <h2>2) cURL (prueba rápida)</h2>
            <pre><code>URL='<?php echo esc_js($endpoint); ?>'
        TOKEN='<?php echo esc_js($token ?? ''); ?>'
        BODY='<?php echo esc_js($json_body); ?>'
        
        curl -sS -w '\nHTTP %{http_code}\n' -X POST "$URL" \
          -H 'Content-Type: application/json' \
          -H '<?php echo esc_html($hdr); ?>: '"$TOKEN" \
          --data-binary "$BODY"</code></pre>
        
          <?php else: ?>
            <p class="description">En modo <strong>HMAC</strong> la firma se calcula sobre el <em>body crudo</em> con tu <code>ingest_shared_secret</code>.</p>
            <!-- 🔹 cURL de prueba (HMAC) -->
            <h2>2) cURL (prueba rápida)</h2>
            <pre><code>SECRET='(tu shared secret HMAC)'
        BODY='<?php echo esc_js($json_body); ?>'
        SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -mac HMAC -macopt key:"$SECRET" -binary | openssl base64)
        
        curl -sS -w '\nHTTP %{http_code}\n' -X POST '<?php echo esc_url($endpoint); ?>' \
          -H 'Content-Type: application/json' \
          -H '<?php echo esc_html($hdr); ?>: '"$SIG" \
          --data-binary "$BODY"</code></pre>
            <?php if ($exampleSig): ?>
              <p><em>Firma de ejemplo para el JSON mostrado:</em> <?php echo esc_html($exampleSig); ?></p>
            <?php endif; ?>
          <?php endif; ?>
        
        <?php endif; ?>
      </div>

      <!-- 🔹 Script para ocultar/mostrar campos según header_type -->
      <script>
      document.addEventListener('DOMContentLoaded', function(){
        const type = document.getElementById('header_type');
        if (!type) return;

        const rowText  = document.getElementById('header_text')?.closest('tr');
        const rowLink  = document.getElementById('header_media_link')?.closest('tr');
        const rowId    = document.getElementById('header_media_id')?.closest('tr');

        function toggle() {
          const v = type.value;
          if (rowText) rowText.style.display = (v === 'text') ? '' : 'none';
          const media = (v === 'image' || v === 'video' || v === 'document');
          if (rowLink) rowLink.style.display = media ? '' : 'none';
          if (rowId)   rowId.style.display   = media ? '' : 'none';
        }

        type.addEventListener('change', toggle);
        toggle();
      });
      </script>
<?php
    }
}

