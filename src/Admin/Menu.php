<?php
namespace SevenC\MWB\Admin;

use SevenC\MWB\Util\View;

class Menu {
    public static function register(): void {
        add_menu_page(
            '7C Mautic–WABA Bridge',
            '7C Mautic–WABA',
            'manage_options',
            'sevenc-mwb',
            [self::class, 'render_settings'],
            'dashicons-admin-generic',
            58
        );

        // Si en el futuro agregamos más submenús, los colgamos aquí.
    }

    public static function render_settings(): void {
        if (!current_user_can('manage_options')) return;
        View::render('admin/settings-page', []);
    }
}
