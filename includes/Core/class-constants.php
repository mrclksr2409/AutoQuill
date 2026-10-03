<?php
namespace AutoQuill\Core;

class Constants {
    const OPTION_KEY     = 'auto_quill_settings';
    const SETTINGS_GROUP = 'auto_quill_settings_group';
    const DB_VERSION_KEY = 'auto_quill_db_version';
    const DB_VERSION     = '1.5';

    const TABLE_SOURCES  = 'auto_quill_sources';
    const TABLE_ARTICLES = 'auto_quill_articles';
    const TABLE_TOPICS   = 'auto_quill_topics';
    const TABLE_LOGS     = 'auto_quill_logs';
    const TABLE_INTERVIEWS = 'auto_quill_interviews';

    const MENU_SLUG          = 'auto-quill';
    const SOURCES_PAGE_SLUG  = 'auto-quill-sources';
    const SETTINGS_PAGE_SLUG = 'auto-quill-settings';
    const LOGS_PAGE_SLUG     = 'auto-quill-logs';
    const INTERVIEW_PAGE_SLUG = 'auto-quill-interview';

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
    const NONCE_INTERVIEW = 'auto_quill_interview';
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

    /** ID of the interview a post was written from. */
    const META_INTERVIEW_ID  = '_auto_quill_interview_id';

    /** Answers needed before an interview can be turned into a post. */
    const INTERVIEW_MIN_ANSWERS = 3;
    const INTERVIEW_ANSWER_MAX_CHARS = 4000;
    const INTERVIEW_QUESTIONS_MIN = 3;
    const INTERVIEW_QUESTIONS_MAX = 15;

    /** How the post written from an interview is framed. */
    const INTERVIEW_STYLES = ['first_person', 'editorial', 'qa'];

    /**
     * openai/claude talk their own APIs; ionos and custom are OpenAI-compatible
     * endpoints (chat/completions + models) that differ only by base URL.
     */
    const AI_PROVIDERS = ['openai', 'claude', 'ionos', 'custom'];

    const OPENAI_BASE_URL = 'https://api.openai.com/v1';
    const IONOS_BASE_URL  = 'https://openai.inference.de-txl.ionos.com/v1';

    const DEFAULT_OPENAI_MODEL = 'gpt-4o-mini';
    const DEFAULT_CLAUDE_MODEL = 'claude-sonnet-4-6';
    const DEFAULT_IONOS_MODEL  = 'meta-llama/Llama-3.3-70B-Instruct';

    /** Upper bound for the global writing style instruction. */
    const WRITING_STYLE_MAX_CHARS = 2000;

    /** Transient prefix for the model lists fetched from the providers. */
    const MODELS_CACHE_PREFIX = 'auto_quill_models_';
    const MODELS_CACHE_TTL    = 43200; // 12h

    const UPDATE_REPO_URL    = 'https://github.com/mrclksr2409/autoquill/';
    const UPDATE_MAIN_BRANCH = 'main';
    const UPDATE_BETA_BRANCH = 'beta';
    const UPDATE_SLUG        = 'auto-quill';

    /**
     * Key for the given provider, or for the active one when omitted. Each
     * provider keeps its own key, so switching providers loses nothing.
     */
    public static function ai_api_key(?string $provider = null): string {
        if (defined('AUTO_QUILL_AI_KEY') && AUTO_QUILL_AI_KEY !== '') {
            return (string) AUTO_QUILL_AI_KEY;
        }
        $settings = get_option(self::OPTION_KEY, self::defaults());
        if (!is_array($settings)) {
            return '';
        }
        $provider = $provider ?? self::ai_provider();
        return (string) ($settings[$provider . '_api_key'] ?? '');
    }

    public static function ai_provider(): string {
        $settings = get_option(self::OPTION_KEY, self::defaults());
        $provider = is_array($settings) ? (string) ($settings['ai_provider'] ?? 'openai') : 'openai';
        return in_array($provider, self::AI_PROVIDERS, true) ? $provider : 'openai';
    }

    /**
     * Base URL (without trailing slash) of an OpenAI-compatible provider;
     * empty for claude and for an unconfigured custom endpoint.
     */
    public static function ai_base_url(string $provider): string {
        switch ($provider) {
            case 'openai':
                return self::OPENAI_BASE_URL;
            case 'ionos':
                return self::IONOS_BASE_URL;
            case 'custom':
                $settings = get_option(self::OPTION_KEY, self::defaults());
                return is_array($settings) ? rtrim((string) ($settings['custom_base_url'] ?? ''), '/') : '';
            default:
                return '';
        }
    }

    /** Only a self-hosted or open endpoint may run without a key. */
    public static function ai_key_required(string $provider): bool {
        return $provider !== 'custom';
    }

    public static function ai_provider_label(string $provider): string {
        switch ($provider) {
            case 'claude':
                return 'Claude';
            case 'ionos':
                return 'IONOS';
            case 'custom':
                return __('Eigener Endpunkt', 'auto-quill');
            default:
                return 'OpenAI';
        }
    }

    /**
     * One-time move of the single legacy key (settings before 1.7) to the
     * provider it was used with.
     */
    public static function maybe_migrate_api_key(): void {
        $settings = get_option(self::OPTION_KEY);
        if (!is_array($settings) || !array_key_exists('ai_api_key', $settings)) {
            return;
        }
        $legacy   = (string) $settings['ai_api_key'];
        $provider = in_array($settings['ai_provider'] ?? '', self::AI_PROVIDERS, true) ? $settings['ai_provider'] : 'openai';
        if ($legacy !== '' && empty($settings[$provider . '_api_key'])) {
            $settings[$provider . '_api_key'] = $legacy;
        }
        unset($settings['ai_api_key']);
        update_option(self::OPTION_KEY, $settings);
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
            'openai_api_key'  => '',
            'claude_api_key'  => '',
            'ionos_api_key'   => '',
            'custom_api_key'  => '',
            'openai_model'    => self::DEFAULT_OPENAI_MODEL,
            'claude_model'    => self::DEFAULT_CLAUDE_MODEL,
            'ionos_model'     => self::DEFAULT_IONOS_MODEL,
            'custom_model'    => '',
            'custom_base_url' => '',
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
            // Empty = no style instruction; applies to title, body and excerpt.
            'writing_style'   => '',
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
            'interview_style'     => 'first_person',
            'interview_questions' => 6,
            'prompt_interview'    => self::default_prompt_interview(),
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

    public static function default_prompt_interview(): string {
        return "Du bist ein erfahrener Redakteur und führst ein Interview, aus dem später ein Blog-Beitrag entsteht.\n"
            . "- stelle immer genau EINE Frage pro Runde, kurz und offen formuliert\n"
            . "- beginne mit einer einladenden Einstiegsfrage zum Thema\n"
            . "- hake nach, wenn eine Antwort vage bleibt: frage nach konkreten Beispielen, Erfahrungen, Zahlen oder Gründen\n"
            . "- wiederhole keine Fragen und keine bereits beantworteten Aspekte\n"
            . "- decke nach und nach verschiedene Seiten des Themas ab (Hintergrund, Praxis, Probleme, Tipps, Ausblick)\n"
            . "- keine Bewertung oder Zusammenfassung der Antworten, nur die nächste Frage\n"
            . "- auf Deutsch, freundlich und neugierig";
    }

    /**
     * Writing instruction for the post built from an interview, per style.
     */
    public static function interview_style_instruction(string $style): string {
        $common = "Verwende ausschließlich Inhalte, Fakten und Meinungen aus dem Interview. Erfinde keine Erlebnisse, Zahlen, Namen oder Zitate hinzu. Glätte Sprache und Grammatik, ohne den Sinn der Antworten zu verändern.";

        switch ($style) {
            case 'editorial':
                return "Schreibe einen redaktionellen Artikel in der dritten Person über die befragte Person und ihre Sicht auf das Thema. "
                    . "Baue einige prägnante Aussagen als wörtliche Zitate ein (in Anführungszeichen, sinngemäß aus den Antworten übernommen). "
                    . "Die Fragen des Redakteurs erscheinen nicht als solche im Text.\n" . $common;
            case 'qa':
                return "Veröffentliche das Gespräch als klassisches Interview im Frage-Antwort-Format: eine kurze Einleitung, danach die Fragen als <h3> "
                    . "und die Antworten als Absätze darunter. Fasse Fragen redaktionell knapp, kürze Antworten behutsam und lass übersprungene Fragen weg. "
                    . "Ende mit einem kurzen Schlusswort.\n" . $common;
            case 'first_person':
            default:
                return "Schreibe den Beitrag in der Ich-Perspektive, in der Stimme der befragten Person, als wäre es ihr eigener Blog-Beitrag. "
                    . "Die Fragen des Redakteurs erscheinen nicht im Text; die Antworten werden zu einem zusammenhängenden, gut strukturierten Beitrag verarbeitet.\n" . $common;
        }
    }
}
