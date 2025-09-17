<?php
namespace SevenC\MWB\Util;

class View {
    public static function render(string $view, array $vars = []): void {
        // Tus vistas están bajo src/views/...
        $file = SEVENC_MWB_DIR . 'src/views/' . $view . '.php';

        // Fallback útil para depurar si la ruta está mal
        if (!is_readable($file)) {
            echo '<div class="wrap"><h1>Vista no encontrada</h1><p>No existe: <code>' . esc_html($file) . '</code></p></div>';
            return;
        }
        extract($vars);
        include $file;
    }
}
