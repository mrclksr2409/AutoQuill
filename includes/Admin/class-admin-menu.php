<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

class AdminMenu {
    /** Hook suffix of the hidden generate page, as returned by add_submenu_page(). */
    private static ?string $generate_hook = null;

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register']);
        // Late enough to run after register(); must fire on every admin load.
        add_action('admin_menu', [self::class, 'hide_generate_page'], 999);
        add_filter('submenu_file', [self::class, 'keep_parent_highlighted']);
        add_filter('admin_title', [self::class, 'filter_admin_title'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * The generate page is reachable by URL but has no menu entry.
     *
     * remove_submenu_page() only unsets the $submenu entry; $_registered_pages
     * and $_parent_pages stay intact, so the page still routes and the AutoQuill
     * top-level menu still resolves as its parent. Registering it with a null
     * parent instead would set $_parent_pages[$slug] = null, and then the
     * top-level menu would not highlight at all.
     */
    public static function hide_generate_page(): void {
        remove_submenu_page(C::MENU_SLUG, C::GENERATE_PAGE_SLUG);
    }

    /**
     * With the submenu entry gone, $submenu_file points at a slug that is no
     * longer in $submenu and nothing gets marked current. Fall back to the
     * dashboard entry, which is where the generate page conceptually lives.
     */
    public static function keep_parent_highlighted($submenu_file) {
        if (self::is_generate_screen()) {
            return C::MENU_SLUG;
        }
        return $submenu_file;
    }

    /**
     * get_admin_page_title() walks $submenu[$parent]; with the entry removed it
     * falls back to the parent menu's title, so the browser tab would read
     * "AutoQuill". Same reason the <h1> on that page is hardcoded.
     */
    public static function filter_admin_title($admin_title, $title) {
        if (!self::is_generate_screen()) {
            return $admin_title;
        }
        return sprintf(
            /* translators: %s: site admin title suffix */
            __('Blog-Post erstellen%s', 'auto-quill'),
            substr($admin_title, strlen((string) $title))
        );
    }

    private static function is_generate_screen(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
        return isset($_GET['page']) && $_GET['page'] === C::GENERATE_PAGE_SLUG;
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

        // Registered as a real submenu so it routes and inherits the parent's
        // capability, then removed from the menu in hide_generate_page().
        $hook = add_submenu_page(
            C::MENU_SLUG,
            __('Blog-Post erstellen', 'auto-quill'),
            __('Blog-Post erstellen', 'auto-quill'),
            'manage_options',
            C::GENERATE_PAGE_SLUG,
            ['\AutoQuill\Admin\GeneratePage', 'render']
        );
        self::$generate_hook = is_string($hook) ? $hook : null;
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
     * Compared against the hook suffix add_submenu_page() returned rather than
     * guessing "auto-quill_page_auto-quill-generate": that string is derived in
     * get_plugin_page_hookname() and is not worth betting on.
     */
    private static function enqueue_generate_assets(string $hook, array $settings): void {
        if (self::$generate_hook === null || $hook !== self::$generate_hook) {
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
