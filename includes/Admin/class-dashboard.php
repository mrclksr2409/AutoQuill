<?php
namespace AutoQuill\Admin;

use AutoQuill\AI\Selector;
use AutoQuill\Core\Constants as C;
use AutoQuill\Database\SourcesRepository;
use AutoQuill\Database\TopicsRepository;
use AutoQuill\RSS\Fetcher;

class Dashboard {
    public static function boot(): void {
        add_action('wp_ajax_' . C::ACTION_FETCH, [self::class, 'handle_fetch_now']);
        add_action('wp_ajax_' . C::ACTION_RECRAWL, [self::class, 'handle_recrawl']);
        add_action('wp_ajax_' . C::ACTION_RESELECT, [self::class, 'handle_reselect']);
    }

    public static function handle_fetch_now(): void {
        check_ajax_referer(C::NONCE_SCOPE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Zugriff verweigert', 'auto-quill')], 403);
        }

        Fetcher::fetch_feeds();
        wp_send_json_success(['message' => __('Feeds aktualisiert', 'auto-quill')]);
    }

    public static function handle_recrawl(): void {
        check_ajax_referer(C::NONCE_SCOPE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Zugriff verweigert', 'auto-quill')], 403);
        }

        $source_id = (int) ($_POST['source_id'] ?? 0);

        if ($source_id > 0) {
            $source = (new SourcesRepository())->find($source_id);
            if (!$source) {
                wp_send_json_error(['message' => __('Feed nicht gefunden', 'auto-quill')], 404);
            }
            Fetcher::fetch_feed((int) $source->id, (string) $source->feed_url);
        } else {
            Fetcher::fetch_feeds();
        }

        Selector::select_top_topics();
        wp_send_json_success(['message' => __('Themen neu generiert', 'auto-quill')]);
    }

    public static function handle_reselect(): void {
        check_ajax_referer(C::NONCE_SCOPE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Zugriff verweigert', 'auto-quill')], 403);
        }

        Selector::select_top_topics();
        wp_send_json_success(['message' => __('Themen neu generiert', 'auto-quill')]);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }

        $today        = date('Y-m-d');
        $today_topics = (new TopicsRepository())->find_by_date($today);
        $sources      = (new SourcesRepository())->active();
        ?>
        <div class="wrap auto-quill-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php Notices::flush(); ?>

            <div class="auto-quill-container">
                <div class="auto-quill-panel">
                    <h2><?php esc_html_e('Heute\'s Top-Themen', 'auto-quill'); ?></h2>

                    <div class="auto-quill-recrawl-controls">
                        <label for="auto-quill-source-select" class="screen-reader-text">
                            <?php esc_html_e('RSS-Feed auswählen', 'auto-quill'); ?>
                        </label>
                        <select id="auto-quill-source-select">
                            <option value="0"><?php esc_html_e('Alle aktiven Feeds', 'auto-quill'); ?></option>
                            <?php foreach ($sources as $source): ?>
                                <option value="<?php echo (int) $source->id; ?>">
                                    <?php echo esc_html($source->title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button button-primary" id="auto-quill-recrawl-btn">
                            <?php esc_html_e('Feeds neu holen + Topics neu wählen', 'auto-quill'); ?>
                        </button>
                        <button class="button" id="auto-quill-reselect-btn">
                            <?php esc_html_e('Nur Topics neu wählen', 'auto-quill'); ?>
                        </button>
                    </div>

                    <?php if ($today_topics): ?>
                        <?php $topics = json_decode($today_topics->topics, true) ?: []; ?>
                        <div id="topics-list" class="topics-list">
                            <?php foreach ($topics as $idx => $topic): ?>
                                <div class="topic-card"
                                     data-topic-id="<?php echo (int) $today_topics->id; ?>"
                                     data-topic-index="<?php echo (int) $idx; ?>">
                                    <h3><?php echo esc_html($topic['title'] ?? ''); ?></h3>
                                    <p><strong><?php esc_html_e('Begründung:', 'auto-quill'); ?></strong>
                                       <?php echo esc_html($topic['reason'] ?? ''); ?></p>
                                    <p><?php echo esc_html(substr((string) ($topic['summary'] ?? ''), 0, 200)); ?></p>
                                    <button class="button button-primary auto-quill-select-topic"
                                            data-topic-id="<?php echo (int) $today_topics->id; ?>"
                                            data-topic-index="<?php echo (int) $idx; ?>">
                                        <?php esc_html_e('Blog-Post generieren', 'auto-quill'); ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php esc_html_e('Noch keine Themen für heute verfügbar. Topics werden täglich aktualisiert oder über die Buttons oben manuell ausgelöst.', 'auto-quill'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="auto-quill-panel">
                    <h2><?php esc_html_e('Blog-Post Vorschau', 'auto-quill'); ?></h2>
                    <div id="post-preview" class="post-preview">
                        <p><?php esc_html_e('Wählen Sie ein Thema aus, um den Blog-Post zu generieren.', 'auto-quill'); ?></p>
                    </div>
                    <button class="button button-primary" id="publish-post-btn" style="display:none;">
                        <?php esc_html_e('Post veröffentlichen', 'auto-quill'); ?>
                    </button>
                </div>
            </div>
        </div>

        <style>
            .auto-quill-wrap { padding: 20px; }
            .auto-quill-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
            .auto-quill-panel { background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 20px; }
            .auto-quill-recrawl-controls { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
            .auto-quill-recrawl-controls select { min-width: 180px; }
            .topics-list { display: flex; flex-direction: column; gap: 15px; }
            .topic-card { border-left: 4px solid #0073aa; padding: 15px; background: #f9f9f9; border-radius: 4px; }
            .topic-card h3 { margin: 0 0 10px 0; color: #0073aa; }
            .topic-card p { margin: 5px 0; }
            .post-preview { border: 1px dashed #ccc; padding: 20px; min-height: 300px; max-height: 600px; overflow-y: auto; background: #f9f9f9; }
        </style>
        <?php
    }
}
