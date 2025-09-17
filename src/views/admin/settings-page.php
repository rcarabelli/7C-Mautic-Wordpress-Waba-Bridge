<?php
use SevenC\MWB\Settings\Keys;
use SevenC\MWB\Settings\Registry;

if (!defined('ABSPATH')) exit;

// Valor guardado (puede venir como ruta o como URL completa)
$raw_route = Registry::get(Keys::INGEST_ROUTE, '/wp-json/7c-mwb/v1/ingest');

// Si viene como URL completa, quédate solo con el path
if (stripos($raw_route, 'http://') === 0 || stripos($raw_route, 'https://') === 0) {
    $raw_route = parse_url($raw_route, PHP_URL_PATH) ?: $raw_route;
}

// Forzar que sea ruta relativa con un solo "/"
$route_path = '/' . ltrim(trim($raw_route), '/');

// Construye la URL completa de forma segura
$full_ingest_url = home_url(ltrim($route_path, '/'));
?>

<div class="wrap">
  <h1>7C Mautic–WABA Bridge — Settings</h1>
  <p>Fase 1: solo configuración. En próximas etapas agregaremos el endpoint de ingest (HMAC) y el generador de líneas para Mautic.</p>

  <!-- AYUDA RÁPIDA (qué es cada campo y de dónde sacar el dato) -->
  <div class="notice notice-info" style="padding:14px 16px;margin-top:12px;">
    <h2 style="margin:0 0 8px;">Ayuda rápida</h2>
    <ul style="margin-left:18px; list-style:disc;">
      <li><strong>Header de firma HMAC:</strong>
        Es el <em>nombre del header</em> donde Mautic enviará la <em>firma HMAC-SHA256</em> del cuerpo (body) del Webhook.
        Este plugin recalcula la firma con el <strong>Shared Secret</strong> y compara. Si coincide, acepta; si no, rechaza.
        Lo normal es usar <code>X-Signature</code>.
      </li>

      <li><strong>Ruta REST de ingest (URL del Webhook en Mautic):</strong>
        Es la <em>ruta</em> del <strong>endpoint del plugin (WordPress)</strong>. No es la URL de Mautic, ni de board.support, ni de WABA.
        Tu URL completa para pegar en Mautic es:
        <div style="margin:6px 0 0;">
          <code><?php echo esc_html($full_ingest_url); ?></code>
          <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($full_ingest_url); ?>')">Copiar</button>
        </div>
      </li>

      <li><strong>Shared Secret (HMAC):</strong>
        Es una <em>clave privada</em> que tú defines aquí y copias igual en Mautic para firmar el body (HMAC-SHA256).
        Nadie te la entrega: <em>tú la creas</em>. Recomendado 32–64+ caracteres.
        <br><em>Cómo generar (ejemplos):</em>
        <code>openssl rand -base64 48</code> (Linux/macOS) o cualquier generador aleatorio seguro.
      </li>

      <li><strong>NAPI Endpoint URL (BSP):</strong> Para Support Board usa <code>/support/include/api.php</code>. Este API recibe
      <code>POST</code> con <code>token</code> y <code>function</code>. Ejemplo completo:
      <code><?php echo esc_html( home_url('/support/include/api.php') ); ?></code></li>
    
      <li><strong>NAPI Token (BSP):</strong> Token de un usuario <em>admin</em> (Users → perfil del admin → API token).</li>
    
      <li><strong>Función (BSP):</strong> Usa <code>function=whatsapp-send-message</code> para texto libre o
      <code>function=whatsapp-send-template</code> para plantillas. Docs: <code>whatsapp-send-message</code> / <code>whatsapp-send-template</code>.</li>
    
      <li><strong>Content-Type:</strong> <code>application/x-www-form-urlencoded</code>. El token va en el <em>body</em>.
        El header <code>Authorization</code> es opcional y normalmente no se usa.</li>

      <li><strong>NAPI Token (BSP):</strong>
        Token de acceso desde tu panel de <em>board.support</em>.
        Se usa como header: <code>Authorization: Bearer &lt;token&gt;</code>.
      </li>

      <li><strong>Header de auth (BSP):</strong>
        Normalmente debe ser exactamente <code>Authorization</code>, con formato <code>Bearer &lt;token&gt;</code>.
        Solo cámbialo si la documentación de tu BSP lo indica (por ejemplo, <code>X-API-KEY</code>).
      </li>

      <li><strong>Headers extra (JSON) (BSP):</strong>
        Solo si tu BSP lo pide (ver doc de <em>board.support</em>). Debe ser <em>JSON válido</em> (clave/valor con comillas dobles).
        Ejemplos: <code>{"X-Account":"tu-cuenta"}</code>, <code>{"X-Tenant":"petexperts"}</code>.
      </li>

      <li><strong>Phone Number ID / Access Token / API Version (Meta):</strong>
        Úsalos solo si vas directo a Meta. Se obtienen en Business Manager → WhatsApp (Phone numbers) y en Graph API (token).
      </li>
    </ul>
  </div>

  <!-- FORMULARIO DE OPCIONES -->
  <form method="post" action="options.php" style="margin-top:14px;">
    <?php
      settings_fields(Keys::OPTION);
      do_settings_sections(Keys::OPTION);
      submit_button('Guardar ajustes');
    ?>
  </form>

  <!-- RESUMEN RÁPIDO -->
  <hr>
    <h2>Resumen</h2>
    
    <p><code>Modo:</code> <?php echo esc_html( Registry::get(Keys::MODE, 'bsp') ); ?></p>
    
    <p><code>Header de firma:</code> <?php echo esc_html( Registry::get(Keys::SIGNATURE_HEADER, 'X-Signature') ); ?></p>
    
    <!-- Ruta RELATIVA (sin home_url) -->
    <p><code>Ruta de ingest:</code> <code><?php echo esc_html($route_path); ?></code></p>
        
    <!-- URL COMPLETA (con home_url) -->
    <p><code>Webhook completo (para Mautic):</code> <code><?php echo esc_html($full_ingest_url); ?></code></p>
    
    <hr>
    <h2>Prueba de conexión</h2>
    <p>
      <input type="text"
             id="sevenc-mwb-test-number"
             placeholder="Ej. 009999999"
             style="width:240px;" />
      <button type="button" class="button" id="sevenc-mwb-test-btn">Enviar prueba</button>
    </p>
    <p style="font-size:12px; color:#555; margin-top:4px;">
      Ingresa el número en formato <strong>código de país + número</strong>.
      Ejemplo para Perú: <code>51999999999</code>
    </p>
    <div id="sevenc-mwb-test-result" style="margin-top:10px;font-family:monospace;"></div>


  <!-- MAPA DE ARCHIVOS -->
  <hr>
  <h2>Mapa de archivos del plugin</h2>
  <ul>
    <li><code>7c-mwb.php</code> – Bootstrap (autoload + arranque del plugin).</li>
    <li><code>src/Plugin.php</code> – Orquestador, hooks globales y enqueue de assets.</li>
    <li><code>src/Admin/Menu.php</code> – Menús en WP Admin (página Settings).</li>
    <li><code>src/Settings/Keys.php</code> – Constantes/keys de opciones.</li>
    <li><code>src/Settings/Defaults.php</code> – Valores por defecto de opciones.</li>
    <li><code>src/Settings/Sanitizer.php</code> – Sanitización/validación al guardar.</li>
    <li><code>src/Settings/Registry.php</code> – Registro de secciones/campos (Settings API).</li>
    <li><code>src/Util/Arr.php</code> – Helper de arrays.</li>
    <li><code>src/Util/View.php</code> – Helper para renderizar vistas.</li>
    <li><code>src/views/admin/settings-page.php</code> – Esta vista (UI de Settings).</li>
    <li><code>assets/admin.css</code> – CSS del admin.</li>
    <li><code>assets/admin.js</code> – JS del admin.</li>
  </ul>
</div>
