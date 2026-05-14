<?php
namespace AutoQuill\Core;

class Constants {
    const OPTION_KEY     = 'auto_quill_settings';
    const SETTINGS_GROUP = 'auto_quill_settings_group';
    const DB_VERSION_KEY = 'auto_quill_db_version';
    const DB_VERSION     = '1.2';

    const TABLE_SOURCES  = 'auto_quill_sources';
    const TABLE_ARTICLES = 'auto_quill_articles';
    const TABLE_TOPICS   = 'auto_quill_topics';

    const MENU_SLUG          = 'auto-quill';
    const SOURCES_PAGE_SLUG  = 'auto-quill-sources';
    const SETTINGS_PAGE_SLUG = 'auto-quill-settings';

    const ACTION_ADD      = 'auto_quill_add_source';
    const ACTION_DELETE   = 'auto_quill_delete_source';
    const ACTION_FETCH    = 'auto_quill_fetch_now';
    const ACTION_RECRAWL  = 'auto_quill_recrawl_topics';
    const ACTION_RESELECT = 'auto_quill_reselect_topics';

    const NONCE_SCOPE    = 'auto-quill-nonce';
    const NOTICE_KEY_FMT = 'auto_quill_notice_%d';

    const CRON_FETCH  = 'auto_quill_daily_fetch';
    const CRON_SELECT = 'auto_quill_daily_select';

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
            'post_status'   => 'draft',
            'auto_publish'  => false,
            'posts_per_day' => 1,
            'rss_lookback_days' => 7,
            'prompt_body'    => self::default_prompt_body(),
            'prompt_excerpt' => self::default_prompt_excerpt(),
        ];
    }

    public static function default_prompt_body(): string {
        return "Schreibe einen ausführlichen, professionellen Blog-Post über das Thema: '{title}'\n\n"
            . "{source_block}"
            . "Der Post sollte:\n"
            . "- 800-1200 Wörter lang sein\n"
            . "- Mit einer ansprechenden Einleitung beginnen\n"
            . "- 3-4 Hauptabschnitte mit Zwischenüberschriften (<h2>) haben\n"
            . "- Mit einem Fazit enden\n"
            . "- HTML-Formatierung verwenden (aber ohne <html>, <body> etc.)\n"
            . "- Ausschließlich Fakten aus dem obigen Quelltext verwenden und paraphrasieren (kein wörtliches Kopieren)";
    }

    public static function default_prompt_excerpt(): string {
        return "Erstelle einen kurzen, für Social Media optimierten Auszug zum Thema '{title}'.\n"
            . "- 1-2 Sätze\n"
            . "- maximal ~250 Zeichen\n"
            . "- mit einem Hook, der zum Klicken animiert\n"
            . "- auf Deutsch";
    }
}
