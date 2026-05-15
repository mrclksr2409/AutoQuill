<?php
namespace AutoQuill\Core;

class Constants {
    const OPTION_KEY     = 'auto_quill_settings';
    const SETTINGS_GROUP = 'auto_quill_settings_group';
    const DB_VERSION_KEY = 'auto_quill_db_version';
    const DB_VERSION     = '1.3';

    const TABLE_SOURCES  = 'auto_quill_sources';
    const TABLE_ARTICLES = 'auto_quill_articles';
    const TABLE_TOPICS   = 'auto_quill_topics';
    const TABLE_LOGS     = 'auto_quill_logs';

    const MENU_SLUG          = 'auto-quill';
    const SOURCES_PAGE_SLUG  = 'auto-quill-sources';
    const SETTINGS_PAGE_SLUG = 'auto-quill-settings';
    const LOGS_PAGE_SLUG     = 'auto-quill-logs';

    const ACTION_ADD      = 'auto_quill_add_source';
    const ACTION_DELETE   = 'auto_quill_delete_source';
    const ACTION_FETCH    = 'auto_quill_fetch_now';
    const ACTION_RECRAWL  = 'auto_quill_recrawl_topics';
    const ACTION_RESELECT = 'auto_quill_reselect_topics';

    const NONCE_SCOPE    = 'auto-quill-nonce';
    const NOTICE_KEY_FMT = 'auto_quill_notice_%d';

    const CRON_FETCH  = 'auto_quill_daily_fetch';
    const CRON_SELECT = 'auto_quill_daily_select';

    const DEFAULT_OPENAI_MODEL = 'gpt-4o-mini';
    const DEFAULT_CLAUDE_MODEL = 'claude-sonnet-4-6';

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

    public static function defaults(): array {
        return [
            'ai_provider'   => 'openai',
            'ai_api_key'    => '',
            'openai_model'  => self::DEFAULT_OPENAI_MODEL,
            'claude_model'  => self::DEFAULT_CLAUDE_MODEL,
            'post_status'   => 'draft',
            'auto_publish'  => false,
            'posts_per_day' => 1,
            'rss_lookback_days' => 7,
            'prompt_title'    => self::default_prompt_title(),
            'prompt_body'     => self::default_prompt_body(),
            'prompt_excerpt'  => self::default_prompt_excerpt(),
            'prompt_category' => self::default_prompt_category(),
            'debug_logging'   => false,
            'beta_mode'       => false,
        ];
    }

    public static function default_prompt_title(): string {
        return "Erzeuge einen prägnanten, klickstarken Blog-Titel auf Deutsch.\n\n"
            . "Ausgangsthema (vorläufig): '{topic_title}'\n\n"
            . "{source_block}"
            . "Vorgaben:\n"
            . "- maximal ~70 Zeichen\n"
            . "- keine Anführungszeichen, keine Emojis, kein Punkt am Ende\n"
            . "- spiegelt den Inhalt des Quelltexts wider, kein Clickbait ohne Substanz\n"
            . "- darf vom vorläufigen Titel abweichen, wenn dadurch ein besserer Titel entsteht\n\n"
            . "Antworte AUSSCHLIESSLICH mit einem gültigen JSON-Objekt (kein Markdown, kein Codeblock):\n"
            . "{\n  \"title\": \"<dein Titel>\"\n}";
    }

    public static function default_prompt_body(): string {
        return "Schreibe einen ausführlichen, professionellen Blog-Post mit dem Titel: '{title}'\n\n"
            . "{source_block}"
            . "Der Post sollte:\n"
            . "- 800-1200 Wörter lang sein\n"
            . "- Mit einer ansprechenden Einleitung beginnen\n"
            . "- 3-4 Hauptabschnitte mit Zwischenüberschriften (<h2>) haben\n"
            . "- Mit einem Fazit enden\n"
            . "- HTML-Formatierung verwenden (aber ohne <html>, <body> etc.)\n"
            . "- Ausschließlich Fakten aus dem obigen Quelltext verwenden und paraphrasieren (kein wörtliches Kopieren)\n\n"
            . "Antworte AUSSCHLIESSLICH mit einem gültigen JSON-Objekt (kein Markdown, kein Codeblock):\n"
            . "{\n  \"content\": \"<HTML-Inhalt des Blog-Posts>\"\n}";
    }

    public static function default_prompt_excerpt(): string {
        return "Erstelle einen kurzen, für Social Media optimierten Auszug zum Beitrag mit dem Titel '{title}'.\n\n"
            . "Beitragstext (Auszug):\n{content_excerpt}\n\n"
            . "Vorgaben:\n"
            . "- 1-2 Sätze\n"
            . "- maximal ~250 Zeichen\n"
            . "- mit einem Hook, der zum Klicken animiert\n"
            . "- auf Deutsch\n\n"
            . "Antworte AUSSCHLIESSLICH mit einem gültigen JSON-Objekt (kein Markdown, kein Codeblock):\n"
            . "{\n  \"excerpt\": \"<Auszug>\"\n}";
    }

    public static function default_prompt_category(): string {
        return "Ordne den folgenden Blog-Beitrag den passenden Kategorien zu.\n\n"
            . "Titel: {title}\n\n"
            . "Beitragstext (Auszug):\n{content_excerpt}\n\n"
            . "Verfügbare Kategorien:\n{categories_list}\n"
            . "Vorgaben:\n"
            . "- wähle 1 bis 3 IDs, die thematisch wirklich passen\n"
            . "- ausschließlich IDs aus der obigen Liste\n\n"
            . "Antworte AUSSCHLIESSLICH mit einem gültigen JSON-Objekt (kein Markdown, kein Codeblock):\n"
            . "{\n  \"category_ids\": [<IDs>]\n}";
    }
}
