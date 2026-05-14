<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

class AdminMenu {
    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function register(): void {
        add_menu_page(
            __('AutoQuill', 'auto-quill'),
            __('AutoQuill', 'auto-quill'),
            'manage_options',
            C::MENU_SLUG,
            ['\AutoQuill\Admin\Dashboard', 'render'],
            'dashicons-rss',
            90
        );

        add_submenu_page(
            C::MENU_SLUG,
            __('RSS Quellen', 'auto-quill'),
            __('RSS Quellen', 'auto-quill'),
            'manage_options',
            C::SOURCES_PAGE_SLUG,
            ['\AutoQuill\Admin\SourcesController', 'render']
        );

        add_submenu_page(
            C::MENU_SLUG,
            __('Einstellungen', 'auto-quill'),
            __('Einstellungen', 'auto-quill'),
            'manage_options',
            C::SETTINGS_PAGE_SLUG,
            ['\AutoQuill\Admin\Settings', 'render']
        );

        add_submenu_page(
            C::MENU_SLUG,
            __('Logs', 'auto-quill'),
            __('Logs', 'auto-quill'),
            'manage_options',
            C::LOGS_PAGE_SLUG,
            ['\AutoQuill\Admin\LogsPage', 'render']
        );
    }

    public static function enqueue_assets(string $hook): void {
        if (strpos($hook, C::MENU_SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'auto-quill-admin',
            AUTO_QUILL_PLUGIN_URL . 'assets/admin.css',
            [],
            AUTO_QUILL_VERSION
        );

        wp_enqueue_script(
            'auto-quill-admin',
            AUTO_QUILL_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            AUTO_QUILL_VERSION,
            true
        );

        $settings = get_option(C::OPTION_KEY, C::defaults());
        if (!is_array($settings)) {
            $settings = C::defaults();
        }

        wp_enqueue_script(
            'auto-quill-debug',
            AUTO_QUILL_PLUGIN_URL . 'assets/auto-quill-debug.js',
            ['jquery'],
            AUTO_QUILL_VERSION,
            true
        );

        wp_localize_script('auto-quill-debug', 'autoQuillDebug', [
            'restUrl'      => rest_url('auto-quill/v1/logs'),
            'restNonce'    => wp_create_nonce('wp_rest'),
            'debugEnabled' => Logger::is_debug_enabled(),
            'pollInterval' => 2000,
        ]);

        wp_localize_script('auto-quill-admin', 'autoQuill', [
            'apiUrl'             => rest_url('auto-quill/v1/'),
            'nonce'              => wp_create_nonce(C::NONCE_SCOPE),
            'restNonce'          => wp_create_nonce('wp_rest'),
            'fetchAction'        => C::ACTION_FETCH,
            'publishButtonLabel' => Dashboard::publish_button_label($settings),
            'i18n' => [
                'recrawling'         => __('Wird neu gecrawlt...', 'auto-quill'),
                'recrawlInfo'        => __('Feeds werden geholt und Themen neu generiert...', 'auto-quill'),
                'recrawlError'       => __('Fehler beim Neu-Crawlen', 'auto-quill'),
                'reselecting'        => __('Themen werden neu gewählt...', 'auto-quill'),
                'reselectInfo'       => __('Themen werden neu gewählt...', 'auto-quill'),
                'reselectError'      => __('Fehler beim Neu-Wählen', 'auto-quill'),
                'generating'         => __('Blog-Post wird generiert...', 'auto-quill'),
                'generateError'      => __('Fehler beim Generieren des Posts', 'auto-quill'),
                'noContent'          => __('Keine Post-Inhalte verfügbar', 'auto-quill'),
                'saving'             => __('Wird gespeichert...', 'auto-quill'),
                'publishSuccess'     => __('Post erfolgreich erstellt!', 'auto-quill'),
                'publishError'       => __('Fehler beim Veröffentlichen', 'auto-quill'),
            ],
        ]);
    }
}
