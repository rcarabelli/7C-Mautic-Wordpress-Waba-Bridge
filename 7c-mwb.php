<?php
/**
 * Plugin Name:       7C Mautic–WABA Bridge
 * Description:       Setup + Settings del puente entre Mautic y WABA (BSP/Meta).
 *                    Fase 1: configuración segura y endpoints REST scaffold.
 * Version:           0.1.0
 * Author:            Renato Carabelli — 7 Cats Studio Corp
 * Author URI:        https://www.7catstudio.com
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * License:           GPL-2.0-or-later
 * Text Domain:       7c-mwb
 */


if (!defined('ABSPATH')) exit;

define('SEVENC_MWB_VER', '0.1.0');
define('SEVENC_MWB_FILE', __FILE__);
define('SEVENC_MWB_DIR', plugin_dir_path(__FILE__));
define('SEVENC_MWB_URL', plugin_dir_url(__FILE__));

// Autoload PSR-4 mínimo
spl_autoload_register(function($class){
    $prefix = 'SevenC\\MWB\\';
    $base = SEVENC_MWB_DIR . 'src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file = $base . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($file)) require $file;
});

// Boot
add_action('plugins_loaded', function(){
    (new SevenC\MWB\Plugin())->boot();
    \SevenC\MWB\Setup\Installer::maybe_upgrade();
});

// Activación
register_activation_hook(__FILE__, function () {
    \SevenC\MWB\Setup\Installer::activate();
});

// Desactivación (si necesitas algo, ahora vacío)
register_deactivation_hook(__FILE__, function () {});
