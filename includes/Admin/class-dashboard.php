<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;
use AutoQuill\Database\TopicsRepository;
use AutoQuill\RSS\Fetcher;

class Dashboard {
    public static function boot(): void {
        add_action('wp_ajax_' . C::ACTION_FETCH, [self::class, 'handle_fetch_now']);
    }

    public static function handle_fetch_now(): void {
        check_ajax_referer(C::NONCE_SCOPE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Zugriff verweigert', 'auto-quill')], 403);
        }

        Fetcher::fetch_feeds();
        wp_send_json_success(['message' => __('Feeds aktualisiert', 'auto-quill')]);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }

        $today        = date('Y-m-d');
        $today_topics = (new TopicsRepository())->find_by_date($today);
        ?>
        <div class="wrap auto-quill-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php Notices::flush(); ?>

            <div class="auto-quill-container">
                <div class="auto-quill-panel">
                    <h2><?php esc_html_e('Heute\'s Top-Themen', 'auto-quill'); ?></h2>

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
                        <p><?php esc_html_e('Noch keine Themen für heute verfügbar. Topics werden täglich aktualisiert.', 'auto-quill'); ?></p>
                        <button class="button button-primary" onclick="autoQuillFetchNow()">
                            <?php esc_html_e('Jetzt fetchen', 'auto-quill'); ?>
                        </button>
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
            .topics-list { display: flex; flex-direction: column; gap: 15px; }
            .topic-card { border-left: 4px solid #0073aa; padding: 15px; background: #f9f9f9; border-radius: 4px; }
            .topic-card h3 { margin: 0 0 10px 0; color: #0073aa; }
            .topic-card p { margin: 5px 0; }
            .post-preview { border: 1px dashed #ccc; padding: 20px; min-height: 300px; max-height: 600px; overflow-y: auto; background: #f9f9f9; }
        </style>
        <?php
    }
}
