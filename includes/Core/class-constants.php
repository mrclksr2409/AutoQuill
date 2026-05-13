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

    public static function defaults(): array {
        return [
            'ai_provider'   => 'openai',
            'ai_api_key'    => '',
            'post_status'   => 'draft',
            'auto_publish'  => false,
            'posts_per_day' => 1,
        ];
    }
}
