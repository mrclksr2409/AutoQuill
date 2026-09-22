<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

class AdminMenu {
    /** Hook suffix of the AutoQuill top-level page, from add_menu_page(). */
    private static ?string $dashboard_hook = null;

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register']);
        add_filter('admin_title', [self::class, 'filter_admin_title'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * The generate screen shares the dashboard's page, so the browser tab would
     * otherwise read "AutoQuill" on both.
     */
    public static function filter_admin_title($admin_title, $title) {
        if (!GeneratePage::is_requested()) {
            return $admin_title;
        }
        return sprintf(
            /* translators: %s: the site's admin title suffix */
            __('Blog-Post erstellen%s', 'auto-quill'),
            substr((string) $admin_title, strlen((string) $title))
        );
    }

    public static function register(): void {
        $hook = add_menu_page(
            __('AutoQuill', 'auto-quill'),
            __('AutoQuill', 'auto-quill'),
            'manage_options',
            C::MENU_SLUG,
            ['\AutoQuill\Admin\Dashboard', 'render'],
            'dashicons-rss',
            90
        );
        self::$dashboard_hook = is_string($hook) ? $hook : null;

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
            'i18n' => [
                'recrawling'         => __('Wird neu gecrawlt...', 'auto-quill'),
                'recrawlInfo'        => __('Feeds werden geholt und Themen neu generiert...', 'auto-quill'),
                'recrawlError'       => __('Fehler beim Neu-Crawlen', 'auto-quill'),
                'reselecting'        => __('Themen werden neu gewählt...', 'auto-quill'),
                'reselectInfo'       => __('Themen werden neu gewählt...', 'auto-quill'),
                'reselectError'      => __('Fehler beim Neu-Wählen', 'auto-quill'),
                'generating'         => __('Blog-Post wird generiert...', 'auto-quill'),
                'generateError'      => __('Fehler beim Generieren des Posts', 'auto-quill'),
                'sessionExpired'     => __('Die Sitzung ist abgelaufen. Bitte die Seite neu laden und erneut versuchen.', 'auto-quill'),
                'loadingFeed'        => __('Feed-Einträge werden geladen…', 'auto-quill'),
                'feedLoadError'      => __('Feed-Einträge konnten nicht geladen werden.', 'auto-quill'),
                /* translators: 1: current page, 2: total pages, 3: total entries */
                'feedPageInfo'       => __('Seite %1$d von %2$d (%3$d Einträge)', 'auto-quill'),
                'noContent'          => __('Keine Post-Inhalte verfügbar', 'auto-quill'),
                'saving'             => __('Wird gespeichert...', 'auto-quill'),
                'publishSuccess'     => __('Post erfolgreich erstellt!', 'auto-quill'),
                'publishError'       => __('Fehler beim Veröffentlichen', 'auto-quill'),
                'pickImage'          => __('Bild auswählen', 'auto-quill'),
                'clearImage'         => __('Bild entfernen', 'auto-quill'),
                'noImageSelected'    => __('Kein Bild ausgewählt', 'auto-quill'),
                'searchingImages'    => __('Bilder werden geladen…', 'auto-quill'),
                'noImagesFound'      => __('Keine Bilder gefunden.', 'auto-quill'),
                'imageSearchError'   => __('Bildsuche fehlgeschlagen.', 'auto-quill'),
                'enterSearchQuery'   => __('Bitte einen Suchbegriff eingeben.', 'auto-quill'),
                /* translators: 1: current page, 2: total pages */
                'imagePageInfo'      => __('Seite %1$d von %2$d', 'auto-quill'),
                'suggestingKeywords' => __('Suchbegriffe werden vorgeschlagen…', 'auto-quill'),
                'requestTimeout'     => __('Die Anfrage hat zu lange gedauert. Bitte erneut versuchen.', 'auto-quill'),
            ],
        ]);

        self::enqueue_generate_assets($hook, $settings);
    }

    /**
     * The generate screen's script, loaded only there.
     *
     * The screen lives on the dashboard page, so the hook suffix alone does not
     * identify it - the view parameter does.
     */
    private static function enqueue_generate_assets(string $hook, array $settings): void {
        if (self::$dashboard_hook === null || $hook !== self::$dashboard_hook) {
            return;
        }
        if (!GeneratePage::is_requested()) {
            return;
        }

        wp_enqueue_script(
            'auto-quill-generate',
            AUTO_QUILL_PLUGIN_URL . 'assets/generate.js',
            // admin.js declared as a dependency, so window.AutoQuill exists.
            ['jquery', 'auto-quill-admin'],
            AUTO_QUILL_VERSION,
            true
        );

        $context = GeneratePage::request_context();

        wp_localize_script('auto-quill-generate', 'autoQuillGenerate', [
            'params'    => $context['params'],
            'autostart' => $context['autostart'],
            'backUrl'   => $context['back_url'],
            // Screen-specific strings stay out of the shared autoQuill object.
            'i18n' => [
                /* translators: %d: elapsed seconds */
                'elapsedSeconds'    => __('%d s', 'auto-quill'),
                'generating'        => __('Die KI schreibt den Beitrag…', 'auto-quill'),
                'retry'             => __('Erneut versuchen', 'auto-quill'),
                'regenerate'        => __('Neu generieren', 'auto-quill'),
                'editPost'          => __('Post bearbeiten', 'auto-quill'),
                'backToList'        => __('Zurück zur Übersicht', 'auto-quill'),
                'publishRetryLabel' => Dashboard::publish_button_label($settings),
            ],
        ]);
    }
}
