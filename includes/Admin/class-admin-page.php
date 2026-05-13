<?php
namespace AutoQuill\Admin;

use AutoQuill\Database;
use AutoQuill\RSS\Fetcher;
use AutoQuill\AI\Writer;

class AdminPage {
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

            <div class="auto-quill-add-source">
                <h2><?php esc_html_e('Neue RSS-Quelle hinzufügen', 'auto-quill'); ?></h2>
                <form method="post" id="add-source-form">
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
                                    <button class="button button-small delete-source" data-source-id="<?php echo $source->id; ?>">
                                        <?php esc_html_e('Löschen', 'auto-quill'); ?>
                                    </button>
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

        $settings = get_option('auto_quill_settings', []);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('auto_quill_settings'); ?>
                <?php do_settings_sections('auto_quill_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ai_provider"><?php esc_html_e('KI-Provider', 'auto-quill'); ?></label>
                        </th>
                        <td>
                            <select id="ai_provider" name="auto_quill_ai_provider">
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
                            <input type="password" id="ai_api_key" name="auto_quill_ai_api_key" value="<?php echo esc_attr($settings['ai_api_key'] ?? ''); ?>" style="width: 300px;">
                            <p class="description"><?php esc_html_e('Hier wird dein API-Schlüssel sicher gespeichert.', 'auto-quill'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="post_status"><?php esc_html_e('Standard Post-Status', 'auto-quill'); ?></label>
                        </th>
                        <td>
                            <select id="post_status" name="auto_quill_post_status">
                                <option value="draft" <?php selected($settings['post_status'] ?? '', 'draft'); ?>>Entwurf</option>
                                <option value="publish" <?php selected($settings['post_status'] ?? '', 'publish'); ?>>Veröffentlicht</option>
                                <option value="pending" <?php selected($settings['post_status'] ?? '', 'pending'); ?>>Genehmigung ausstehend</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label>
                                <input type="checkbox" name="auto_quill_auto_publish" value="1" <?php checked($settings['auto_publish'] ?? false, 1); ?>>
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
