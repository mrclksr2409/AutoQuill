<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

class AdminMenu {
    /** Hook suffix of the AutoQuill top-level page, from add_menu_page(). */
    private static ?string $dashboard_hook = null;

    /** Hook suffix of the interview page, from add_submenu_page(). */
    private static ?string $interview_hook = null;

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

        $interview_hook = add_submenu_page(
            C::MENU_SLUG,
            __('Interview', 'auto-quill'),
            __('Interview', 'auto-quill'),
            'manage_options',
            C::INTERVIEW_PAGE_SLUG,
            ['\AutoQuill\Admin\InterviewPage', 'render']
        );
        self::$interview_hook = is_string($interview_hook) ? $interview_hook : null;

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
                'modelsLoading'      => __('Modelle werden beim Anbieter abgerufen…', 'auto-quill'),
                /* translators: %d: number of models */
                'modelsLoaded'       => __('%d Modelle verfügbar.', 'auto-quill'),
                'modelsError'        => __('Die Modellliste konnte nicht geladen werden.', 'auto-quill'),
                'modelNotListed'     => __('(aktuell gespeichert, nicht in der Liste)', 'auto-quill'),
            ],
        ]);

        self::enqueue_generate_assets($hook, $settings);
        self::enqueue_interview_assets($hook, $settings);
    }

    /**
     * The post editor shared by the generate and the interview screen.
     */
    private static function enqueue_post_editor(): void {
        wp_enqueue_script(
            'auto-quill-post-editor',
            AUTO_QUILL_PLUGIN_URL . 'assets/post-editor.js',
            // admin.js declared as a dependency, so window.AutoQuill exists.
            ['jquery', 'auto-quill-admin'],
            AUTO_QUILL_VERSION,
            true
        );
    }

    /**
     * Strings the post editor needs on every screen that embeds it.
     */
    private static function post_editor_i18n(array $settings): array {
        return [
            /* translators: %d: elapsed seconds */
            'elapsedSeconds'    => __('%d s', 'auto-quill'),
            'retry'             => __('Erneut versuchen', 'auto-quill'),
            'editPost'          => __('Post bearbeiten', 'auto-quill'),
            'backToList'        => __('Zurück zur Übersicht', 'auto-quill'),
            'publishRetryLabel' => Dashboard::publish_button_label($settings),
        ];
    }

    private static function enqueue_interview_assets(string $hook, array $settings): void {
        if (self::$interview_hook === null || $hook !== self::$interview_hook) {
            return;
        }

        self::enqueue_post_editor();

        wp_enqueue_script(
            'auto-quill-interview',
            AUTO_QUILL_PLUGIN_URL . 'assets/interview.js',
            ['jquery', 'auto-quill-admin', 'auto-quill-post-editor'],
            AUTO_QUILL_VERSION,
            true
        );

        wp_localize_script('auto-quill-interview', 'autoQuillInterview', [
            'interviewId' => InterviewPage::requested_id(),
            'listUrl'     => InterviewPage::list_url(),
            'i18n'        => array_merge(self::post_editor_i18n($settings), [
                'backToList'      => __('Zurück zu den Interviews', 'auto-quill'),
                'thinking'        => __('Der Redakteur überlegt sich die nächste Frage…', 'auto-quill'),
                'starting'        => __('Das Interview wird vorbereitet…', 'auto-quill'),
                'writing'         => __('Die KI schreibt den Beitrag aus dem Interview…', 'auto-quill'),
                'writeError'      => __('Der Beitrag konnte nicht geschrieben werden.', 'auto-quill'),
                'questionError'   => __('Die nächste Frage konnte nicht geladen werden.', 'auto-quill'),
                'answerError'     => __('Die Antwort konnte nicht gesendet werden.', 'auto-quill'),
                'loadError'       => __('Das Interview konnte nicht geladen werden.', 'auto-quill'),
                'createError'     => __('Das Interview konnte nicht gestartet werden.', 'auto-quill'),
                'deleteError'     => __('Das Interview konnte nicht gelöscht werden.', 'auto-quill'),
                'confirmDelete'   => __('Dieses Interview wirklich löschen? Ein bereits gespeicherter Beitrag bleibt erhalten.', 'auto-quill'),
                'confirmRewrite'  => __('Der aktuelle, noch nicht gespeicherte Beitrag wird ersetzt. Fortfahren?', 'auto-quill'),
                'askAgain'        => __('Frage erneut anfordern', 'auto-quill'),
                'editor'          => __('Redakteur', 'auto-quill'),
                'you'             => __('Du', 'auto-quill'),
                'skipped'         => __('(Frage übersprungen)', 'auto-quill'),
                'enterAnswer'     => __('Bitte eine Antwort eingeben.', 'auto-quill'),
                'enoughHint'      => __('Der Redakteur hat genug Material für einen Beitrag. Du kannst weitere Fragen beantworten oder jetzt den Beitrag schreiben lassen.', 'auto-quill'),
                /* translators: 1: answers given, 2: planned number of questions */
                'progress'        => __('%1$d von ca. %2$d Fragen beantwortet', 'auto-quill'),
                /* translators: %d: answers still needed */
                'needMore'        => __('Noch %d Antwort(en), dann kann der Beitrag geschrieben werden.', 'auto-quill'),
                /* translators: 1: characters used, 2: character limit */
                'charCount'       => __('%1$d / %2$d Zeichen', 'auto-quill'),
                'placeholderPost' => __('Sobald genug Antworten da sind, auf „Beitrag schreiben" klicken.', 'auto-quill'),
                'rewrite'         => __('Beitrag neu schreiben', 'auto-quill'),
            ]),
        ]);
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

        self::enqueue_post_editor();

        wp_enqueue_script(
            'auto-quill-generate',
            AUTO_QUILL_PLUGIN_URL . 'assets/generate.js',
            ['jquery', 'auto-quill-admin', 'auto-quill-post-editor'],
            AUTO_QUILL_VERSION,
            true
        );

        $context = GeneratePage::request_context();

        wp_localize_script('auto-quill-generate', 'autoQuillGenerate', [
            'params'    => $context['params'],
            'autostart' => $context['autostart'],
            'backUrl'   => $context['back_url'],
            // Screen-specific strings stay out of the shared autoQuill object.
            'i18n' => array_merge(self::post_editor_i18n($settings), [
                'generating' => __('Die KI schreibt den Beitrag…', 'auto-quill'),
                'regenerate' => __('Neu generieren', 'auto-quill'),
            ]),
        ]);
    }
}
