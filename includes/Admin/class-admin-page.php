<?php
namespace AutoQuill\Admin;

use AutoQuill\Database;
use AutoQuill\RSS\Fetcher;
use AutoQuill\AI\Writer;

class AdminPage {
    const SETTINGS_GROUP  = 'auto_quill_settings_group';
    const SETTINGS_OPTION = 'auto_quill_settings';

    public static function register_settings() {
        register_setting(self::SETTINGS_GROUP, self::SETTINGS_OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [self::class, 'sanitize_settings'],
            'default'           => [],
        ]);
    }

    public static function sanitize_settings($input) {
        $prev = get_option(self::SETTINGS_OPTION, []);
        if (!is_array($prev)) {
            $prev = [];
        }
        if (!is_array($input)) {
            return $prev;
        }

        $clean = $prev;

        if (isset($input['ai_provider'])) {
            $clean['ai_provider'] = in_array($input['ai_provider'], ['openai', 'claude'], true)
                ? $input['ai_provider']
                : ($prev['ai_provider'] ?? 'openai');
        }

        if (isset($input['ai_api_key'])) {
            $clean['ai_api_key'] = sanitize_text_field((string) $input['ai_api_key']);
        }

        if (isset($input['post_status'])) {
            $clean['post_status'] = in_array($input['post_status'], ['draft', 'publish', 'pending'], true)
                ? $input['post_status']
                : ($prev['post_status'] ?? 'draft');
        }

        $clean['auto_publish'] = !empty($input['auto_publish']);

        add_settings_error(
            self::SETTINGS_OPTION,
            'auto_quill_settings_updated',
            __('Einstellungen gespeichert.', 'auto-quill'),
            'updated'
        );

        return $clean;
    }

    public static function handle_add_source() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Zugriff verweigert', 'auto-quill'));
        }
        check_admin_referer('auto_quill_add_source', 'auto_quill_nonce');

        $title = sanitize_text_field(wp_unslash($_POST['source_title'] ?? ''));
        $url   = esc_url_raw(wp_unslash($_POST['source_url'] ?? ''));

        $notice = ['type' => 'error', 'msg' => __('Ungültige Eingabe.', 'auto-quill')];

        if ($title !== '' && $url !== '' && wp_http_validate_url($url)) {
            $result = Database::getInstance()->insert(
                'sources',
                [
                    'title'    => $title,
                    'feed_url' => $url,
                    'is_active' => 1,
                ],
                ['%s', '%s', '%d']
            );

            if ($result) {
                $notice = ['type' => 'success', 'msg' => __('RSS-Quelle hinzugefügt.', 'auto-quill')];
            } else {
                $notice = ['type' => 'error', 'msg' => __('Speichern fehlgeschlagen.', 'auto-quill')];
            }
        }

        set_transient('auto_quill_notice_' . get_current_user_id(), $notice, 30);
        wp_safe_redirect(admin_url('admin.php?page=auto-quill-sources'));
        exit;
    }

    public static function handle_delete_source() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Zugriff verweigert', 'auto-quill'));
        }
        $id = absint($_POST['source_id'] ?? 0);
        check_admin_referer('auto_quill_delete_source_' . $id, 'auto_quill_nonce');

        $notice = ['type' => 'error', 'msg' => __('Löschen fehlgeschlagen.', 'auto-quill')];
        if ($id > 0) {
            $result = Database::getInstance()->delete('sources', ['id' => $id], ['%d']);
            if ($result) {
                $notice = ['type' => 'success', 'msg' => __('RSS-Quelle gelöscht.', 'auto-quill')];
            }
        }

        set_transient('auto_quill_notice_' . get_current_user_id(), $notice, 30);
        wp_safe_redirect(admin_url('admin.php?page=auto-quill-sources'));
        exit;
    }

    public static function handle_fetch_now() {
        check_ajax_referer('auto-quill-nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Zugriff verweigert', 'auto-quill')], 403);
        }

        \AutoQuill\RSS\Fetcher::fetch_feeds();
        wp_send_json_success(['message' => __('Feeds aktualisiert', 'auto-quill')]);
    }

    private static function render_transient_notice() {
        $key = 'auto_quill_notice_' . get_current_user_id();
        $n = get_transient($key);
        if (!$n) {
            return;
        }
        delete_transient($key);
        $type = in_array($n['type'] ?? 'info', ['success', 'error', 'warning', 'info'], true) ? $n['type'] : 'info';
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($type),
            esc_html($n['msg'] ?? '')
        );
    }

    public static function render_main_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Zugriff verweigert');
        }

        $db = Database::getInstance();
        $today = date('Y-m-d');
        $today_topics = $db->get_row('topics', ['topic_date' => $today]);

        ?>
        <div class="wrap auto-quill-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="auto-quill-container">
                <div class="auto-quill-panel">
                    <h2><?php esc_html_e('Heute\'s Top-Themen', 'auto-quill'); ?></h2>

                    <?php if ($today_topics): ?>
                        <?php
                        $topics = json_decode($today_topics->topics, true);
                        ?>
                        <div id="topics-list" class="topics-list">
                            <?php foreach ($topics as $idx => $topic): ?>
                                <div class="topic-card" data-topic-index="<?php echo $idx; ?>">
                                    <h3><?php echo esc_html($topic['title']); ?></h3>
                                    <p><strong><?php esc_html_e('Begründung:', 'auto-quill'); ?></strong> <?php echo esc_html($topic['reason']); ?></p>
                                    <p><?php echo esc_html(substr($topic['summary'], 0, 200)); ?></p>
                                    <button class="button button-primary auto-quill-select-topic" data-topic-index="<?php echo $idx; ?>">
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
            .auto-quill-wrap {
                padding: 20px;
            }

            .auto-quill-container {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-top: 20px;
            }

            .auto-quill-panel {
                background: #fff;
                border: 1px solid #ccc;
                border-radius: 4px;
                padding: 20px;
            }

            .topics-list {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .topic-card {
                border-left: 4px solid #0073aa;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
            }

            .topic-card h3 {
                margin: 0 0 10px 0;
                color: #0073aa;
            }

            .topic-card p {
                margin: 5px 0;
            }

            .post-preview {
                border: 1px dashed #ccc;
                padding: 20px;
                min-height: 300px;
                max-height: 600px;
                overflow-y: auto;
                background: #f9f9f9;
            }
        </style>
        <?php
    }

    public static function render_sources_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Zugriff verweigert');
        }

        $db = Database::getInstance();
        $sources = $db->get_results('sources');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php self::render_transient_notice(); ?>

            <div class="auto-quill-add-source">
                <h2><?php esc_html_e('Neue RSS-Quelle hinzufügen', 'auto-quill'); ?></h2>
                <form method="post" id="add-source-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="auto_quill_add_source">
                    <?php wp_nonce_field('auto_quill_add_source', 'auto_quill_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="source-title"><?php esc_html_e('Titel', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="source-title" name="source_title" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="source-url"><?php esc_html_e('RSS-Feed URL', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="source-url" name="source_url" required style="width: 100%; max-width: 500px;">
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('RSS-Quelle hinzufügen', 'auto-quill')); ?>
                </form>
            </div>

            <hr>

            <h2><?php esc_html_e('Bestehende RSS-Quellen', 'auto-quill'); ?></h2>
            <?php if (!empty($sources)): ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Titel', 'auto-quill'); ?></th>
                            <th><?php esc_html_e('Feed URL', 'auto-quill'); ?></th>
                            <th><?php esc_html_e('Status', 'auto-quill'); ?></th>
                            <th><?php esc_html_e('Aktion', 'auto-quill'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sources as $source): ?>
                            <tr>
                                <td><?php echo esc_html($source->title); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($source->feed_url); ?>" target="_blank">
                                        <?php echo esc_html(substr($source->feed_url, 0, 50)); ?>...
                                    </a>
                                </td>
                                <td>
                                    <?php if ($source->is_active): ?>
                                        <span class="dashicons dashicons-yes" style="color: green;"></span> Aktiv
                                    <?php else: ?>
                                        <span class="dashicons dashicons-no" style="color: red;"></span> Inaktiv
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js(__('Wirklich löschen?', 'auto-quill')); ?>');">
                                        <input type="hidden" name="action" value="auto_quill_delete_source">
                                        <input type="hidden" name="source_id" value="<?php echo (int) $source->id; ?>">
                                        <?php wp_nonce_field('auto_quill_delete_source_' . $source->id, 'auto_quill_nonce'); ?>
                                        <button type="submit" class="button button-small button-link-delete">
                                            <?php esc_html_e('Löschen', 'auto-quill'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php esc_html_e('Keine RSS-Quellen vorhanden.', 'auto-quill'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Zugriff verweigert');
        }

        $settings = get_option(self::SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors(self::SETTINGS_OPTION); ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ai_provider"><?php esc_html_e('KI-Provider', 'auto-quill'); ?></label>
                        </th>
                        <td>
                            <select id="ai_provider" name="auto_quill_settings[ai_provider]">
                                <option value="openai" <?php selected($settings['ai_provider'] ?? '', 'openai'); ?>>OpenAI</option>
                                <option value="claude" <?php selected($settings['ai_provider'] ?? '', 'claude'); ?>>Claude (Anthropic)</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ai_api_key"><?php esc_html_e('API-Schlüssel', 'auto-quill'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="ai_api_key" name="auto_quill_settings[ai_api_key]" value="<?php echo esc_attr($settings['ai_api_key'] ?? ''); ?>" style="width: 300px;">
                            <p class="description"><?php esc_html_e('Hier wird dein API-Schlüssel sicher gespeichert.', 'auto-quill'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="post_status"><?php esc_html_e('Standard Post-Status', 'auto-quill'); ?></label>
                        </th>
                        <td>
                            <select id="post_status" name="auto_quill_settings[post_status]">
                                <option value="draft" <?php selected($settings['post_status'] ?? '', 'draft'); ?>>Entwurf</option>
                                <option value="publish" <?php selected($settings['post_status'] ?? '', 'publish'); ?>>Veröffentlicht</option>
                                <option value="pending" <?php selected($settings['post_status'] ?? '', 'pending'); ?>>Genehmigung ausstehend</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label>
                                <input type="hidden" name="auto_quill_settings[auto_publish]" value="0">
                                <input type="checkbox" name="auto_quill_settings[auto_publish]" value="1" <?php checked(!empty($settings['auto_publish'])); ?>>
                                <?php esc_html_e('Posts automatisch veröffentlichen', 'auto-quill'); ?>
                            </label>
                        </th>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function get_today_topics($request) {
        $db = Database::getInstance();
        $today = date('Y-m-d');
        $today_topics = $db->get_row('topics', ['topic_date' => $today]);

        if (!$today_topics) {
            return new \WP_REST_Response(['topics' => []], 200);
        }

        return new \WP_REST_Response([
            'topics' => json_decode($today_topics->topics, true),
            'status' => $today_topics->status,
        ], 200);
    }

    public static function publish_post($request) {
        $params = $request->get_json_params();
        $post_content = $params['post_content'] ?? '';
        $post_title = $params['post_title'] ?? '';

        if (empty($post_content) || empty($post_title)) {
            return new \WP_REST_Response(['error' => 'Post-Titel und Inhalt erforderlich'], 400);
        }

        $settings = get_option('auto_quill_settings', []);
        $post_status = $settings['auto_publish'] ? 'publish' : $settings['post_status'] ?? 'draft';

        $post_id = wp_insert_post([
            'post_title' => sanitize_text_field($post_title),
            'post_content' => wp_kses_post($post_content),
            'post_status' => $post_status,
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
        ]);

        if (is_wp_error($post_id)) {
            return new \WP_REST_Response(['error' => $post_id->get_error_message()], 500);
        }

        // Markiere Topic als veröffentlicht
        $db = Database::getInstance();
        $db->update(
            'topics',
            ['post_id' => $post_id, 'status' => 'published'],
            ['selected_topic_title' => $post_title]
        );

        return new \WP_REST_Response([
            'success' => true,
            'post_id' => $post_id,
            'message' => sprintf(
                __('Post %s erstellt und als %s gespeichert', 'auto-quill'),
                $post_id,
                $post_status
            ),
        ], 201);
    }
}
