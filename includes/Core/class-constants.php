<?php
namespace AutoQuill\Core;

class Constants {
    const OPTION_KEY     = 'auto_quill_settings';
    const SETTINGS_GROUP = 'auto_quill_settings_group';
    const DB_VERSION_KEY = 'auto_quill_db_version';
    const DB_VERSION     = '1.4';

    const TABLE_SOURCES  = 'auto_quill_sources';
    const TABLE_ARTICLES = 'auto_quill_articles';
    const TABLE_TOPICS   = 'auto_quill_topics';
    const TABLE_LOGS     = 'auto_quill_logs';

    const MENU_SLUG          = 'auto-quill';
    const SOURCES_PAGE_SLUG  = 'auto-quill-sources';
    const SETTINGS_PAGE_SLUG = 'auto-quill-settings';
    const LOGS_PAGE_SLUG     = 'auto-quill-logs';

    /**
     * The generate screen is a view of the dashboard page, not a page of its
     * own: admin.php?page=auto-quill&aq_view=generate.
     */
    const VIEW_PARAM    = 'aq_view';
    const VIEW_GENERATE = 'generate';

    const ACTION_ADD      = 'auto_quill_add_source';
    const ACTION_DELETE   = 'auto_quill_delete_source';
    const ACTION_FETCH    = 'auto_quill_fetch_now';
    const ACTION_RECRAWL  = 'auto_quill_recrawl_topics';
    const ACTION_RESELECT = 'auto_quill_reselect_topics';
    const ACTION_TEST_MAIL = 'auto_quill_test_mail';
    const ACTION_BACKUP_NOW      = 'auto_quill_backup_now';
    const ACTION_BACKUP_RESTORE  = 'auto_quill_backup_restore';
    const ACTION_BACKUP_DELETE   = 'auto_quill_backup_delete';
    const ACTION_BACKUP_DOWNLOAD = 'auto_quill_backup_download';
    const ACTION_BACKUP_IMPORT   = 'auto_quill_backup_import';

    const NONCE_SCOPE    = 'auto-quill-nonce';
    const NONCE_GENERATE = 'auto_quill_generate';
    const NONCE_TEST_MAIL = 'auto_quill_test_mail_nonce';
    const NOTICE_KEY_FMT = 'auto_quill_notice_%d';

    const CRON_FETCH  = 'auto_quill_daily_fetch';
    const CRON_SELECT = 'auto_quill_daily_select';
    const CRON_DIGEST = 'auto_quill_daily_digest';
    const CRON_BACKUP = 'auto_quill_daily_backup';

    /** Settings backups, newest first. Not autoloaded. */
    const OPTION_BACKUPS = 'auto_quill_backups';
    const BACKUP_KEEP_MAX = 100;

    /** UTC timestamp of the last sent digest. */
    const OPTION_LAST_DIGEST = 'auto_quill_last_digest';

    /** Sections a digest can contain. */
    const NOTIFY_EVENTS = ['topics', 'errors', 'posts'];

    /** How far back a digest may ever look, bounded by the log retention. */
    const DIGEST_MAX_WINDOW_DAYS = 7;

    const DEFAULT_SOURCE_LINK_TEMPLATE = 'Quelle: {source_link}';

    /** Post meta carrying the provenance of a generated post. */
    const META_ARTICLE_ID    = '_auto_quill_article_id';
    const META_SOURCE_URL    = '_auto_quill_source_url';
    const META_ARTICLE_TITLE = '_auto_quill_article_title';
    const META_FEED_NAME     = '_auto_quill_feed_name';

    /**
     * UTC timestamp, written for EVERY post AutoQuill creates.
     *
     * The provenance keys above are skipped when no source article resolves,
     * and topics.post_id / articles.post_id are both lossy (one topics row per
     * day, and articles are pruned by the retention pass), so this is the only
     * reliable marker for "AutoQuill made this post".
     */
    const META_GENERATED_AT  = '_auto_quill_generated_at';

    const DEFAULT_OPENAI_MODEL = 'gpt-4o-mini';
    const DEFAULT_CLAUDE_MODEL = 'claude-sonnet-4-6';

    /** Transient prefix for the model lists fetched from the providers. */
    const MODELS_CACHE_PREFIX = 'auto_quill_models_';
    const MODELS_CACHE_TTL    = 43200; // 12h

    const UPDATE_REPO_URL    = 'https://github.com/mrclksr2409/autoquill/';
    const UPDATE_MAIN_BRANCH = 'main';
    const UPDATE_SLUG        = 'auto-quill';

    public static function ai_api_key(): string {
        if (defined('AUTO_QUILL_AI_KEY') && AUTO_QUILL_AI_KEY !== '') {
            return (string) AUTO_QUILL_AI_KEY;
        }
        $settings = get_option(self::OPTION_KEY, self::defaults());
        if (!is_array($settings)) {
            return '';
        }
        return (string) ($settings['ai_api_key'] ?? '');
    }

    public static function ai_api_key_from_constant(): bool {
        return defined('AUTO_QUILL_AI_KEY') && AUTO_QUILL_AI_KEY !== '';
    }

    public static function pixabay_api_key(): string {
        if (defined('AUTO_QUILL_PIXABAY_KEY') && AUTO_QUILL_PIXABAY_KEY !== '') {
            return (string) AUTO_QUILL_PIXABAY_KEY;
        }
        $settings = get_option(self::OPTION_KEY, self::defaults());
        if (!is_array($settings)) {
            return '';
        }
        return (string) ($settings['pixabay_api_key'] ?? '');
    }

    public static function pixabay_api_key_from_constant(): bool {
        return defined('AUTO_QUILL_PIXABAY_KEY') && AUTO_QUILL_PIXABAY_KEY !== '';
    }

    public static function defaults(): array {
        return [
            'ai_provider'     => 'openai',
            'ai_api_key'      => '',
            'openai_model'    => self::DEFAULT_OPENAI_MODEL,
            'claude_model'    => self::DEFAULT_CLAUDE_MODEL,
            'pixabay_api_key' => '',
            'post_status'   => 'draft',
            'auto_publish'  => false,
            'source_link_enabled'  => true,
            'source_link_template' => self::DEFAULT_SOURCE_LINK_TEMPLATE,
            'posts_per_day' => 1,
            'rss_lookback_days' => 7,
            // Local site time (HH:MM). Selection looks at the last 24h of
            // articles, so it should run after the fetch.
            'fetch_time'  => '00:00',
            'select_time' => '01:00',
            'prompt_title'    => self::default_prompt_title(),
            'prompt_body'     => self::default_prompt_body(),
            'prompt_excerpt'  => self::default_prompt_excerpt(),
            'prompt_category' => self::default_prompt_category(),
            'debug_logging'   => false,
            'beta_mode'       => false,
            // Off by default: a plugin that starts mailing after an update is a
            // nuisance. Existing installs never receive new default keys, so
            // every read must fall back through defaults() explicitly.
            'notify_enabled' => false,
            'notify_time'    => '08:00',
            'notify_users'   => [],
            'notify_emails'  => [],
            'notify_events'  => self::NOTIFY_EVENTS,
            // On by default: unlike mail, a backup bothers nobody.
            'backup_enabled' => true,
            'backup_time'    => '03:00',
            'backup_keep'    => 7,
        ];
    }

    public static function default_prompt_title(): string {
        return "Vorgaben für das Feld \"title\":\n"
            . "- prägnant und klickstark, auf Deutsch\n"
            . "- maximal ~70 Zeichen\n"
            . "- keine Anführungszeichen, keine Emojis, kein Punkt am Ende\n"
            . "- spiegelt den Inhalt des Quelltexts wider, kein Clickbait ohne Substanz\n"
            . "- darf vom Ausgangsthema \"{topic_title}\" abweichen, wenn dadurch ein besserer Titel entsteht";
    }

    public static function default_prompt_body(): string {
        return "Vorgaben für das Feld \"content\":\n"
            . "- ausführlicher, professioneller Blog-Post, 800-1200 Wörter\n"
            . "- mit einer ansprechenden Einleitung beginnen\n"
            . "- 3-4 Hauptabschnitte mit Zwischenüberschriften (<h2>)\n"
            . "- mit einem Fazit enden\n"
            . "- HTML-Formatierung verwenden (aber ohne <html>, <body> etc.)\n"
            . "- ausschließlich Fakten aus dem obigen Quelltext verwenden und paraphrasieren (kein wörtliches Kopieren)";
    }

    public static function default_prompt_excerpt(): string {
        return "Vorgaben für das Feld \"excerpt\":\n"
            . "- kurzer, für Social Media optimierter Auszug auf Deutsch\n"
            . "- 1-2 Sätze\n"
            . "- maximal ~250 Zeichen\n"
            . "- mit einem Hook, der zum Klicken animiert";
    }

    public static function default_prompt_category(): string {
        return "Vorgaben für das Feld \"category_ids\":\n"
            . "- wähle 1 bis 3 IDs, die thematisch wirklich passen\n"
            . "- ausschließlich IDs aus der Liste der verfügbaren Kategorien ({categories_list})\n"
            . "- als Array von Integer-IDs";
    }
}
