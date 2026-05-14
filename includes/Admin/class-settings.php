<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;

class Settings {
    public static function boot(): void {
        add_action('admin_init', [self::class, 'register']);
    }

    public static function register(): void {
        register_setting(
            C::SETTINGS_GROUP,
            C::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [self::class, 'sanitize'],
                'default'           => C::defaults(),
                'show_in_rest'      => false,
            ]
        );
    }

    public static function sanitize($input): array {
        $prev = get_option(C::OPTION_KEY, C::defaults());
        if (!is_array($prev)) {
            $prev = C::defaults();
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

        if (array_key_exists('ai_api_key', $input)) {
            $new_key = sanitize_text_field((string) $input['ai_api_key']);
            if ($new_key !== '') {
                $clean['ai_api_key'] = $new_key;
            }
        }

        if (array_key_exists('openai_model', $input)) {
            $model = sanitize_text_field((string) $input['openai_model']);
            $clean['openai_model'] = $model !== '' ? $model : C::DEFAULT_OPENAI_MODEL;
        }

        if (array_key_exists('claude_model', $input)) {
            $model = sanitize_text_field((string) $input['claude_model']);
            $clean['claude_model'] = $model !== '' ? $model : C::DEFAULT_CLAUDE_MODEL;
        }

        if (isset($input['post_status'])) {
            $clean['post_status'] = in_array($input['post_status'], ['draft', 'publish', 'pending'], true)
                ? $input['post_status']
                : ($prev['post_status'] ?? 'draft');
        }

        $clean['auto_publish'] = !empty($input['auto_publish']);

        if (isset($input['posts_per_day'])) {
            $clean['posts_per_day'] = max(1, min(10, (int) $input['posts_per_day']));
        }

        if (isset($input['rss_lookback_days'])) {
            $v = (int) $input['rss_lookback_days'];
            if ($v < 0)   { $v = 0; }
            if ($v > 365) { $v = 365; }
            $clean['rss_lookback_days'] = $v;
        }

        if (array_key_exists('prompt_title', $input)) {
            $title_tpl = sanitize_textarea_field((string) $input['prompt_title']);
            $clean['prompt_title'] = trim($title_tpl) !== ''
                ? $title_tpl
                : C::defaults()['prompt_title'];
        }

        if (array_key_exists('prompt_body', $input)) {
            $body = sanitize_textarea_field((string) $input['prompt_body']);
            $clean['prompt_body'] = trim($body) !== ''
                ? $body
                : C::defaults()['prompt_body'];
        }

        if (array_key_exists('prompt_excerpt', $input)) {
            $excerpt = sanitize_textarea_field((string) $input['prompt_excerpt']);
            $clean['prompt_excerpt'] = trim($excerpt) !== ''
                ? $excerpt
                : C::defaults()['prompt_excerpt'];
        }

        if (array_key_exists('prompt_category', $input)) {
            $category_tpl = sanitize_textarea_field((string) $input['prompt_category']);
            $clean['prompt_category'] = trim($category_tpl) !== ''
                ? $category_tpl
                : C::defaults()['prompt_category'];
        }

        $clean['debug_logging'] = !empty($input['debug_logging']);

        add_settings_error(
            C::OPTION_KEY,
            'auto_quill_settings_updated',
            __('Einstellungen gespeichert.', 'auto-quill'),
            'updated'
        );

        return $clean;
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }

        $settings = get_option(C::OPTION_KEY, C::defaults());
        if (!is_array($settings)) {
            $settings = C::defaults();
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors(C::OPTION_KEY); ?>

            <form method="post" action="options.php">
                <?php settings_fields(C::SETTINGS_GROUP); ?>

                <h2 class="nav-tab-wrapper auto-quill-settings-tabs">
                    <a href="#tab-ki"      class="nav-tab nav-tab-active" data-tab="ki"><?php esc_html_e('KI-Provider', 'auto-quill'); ?></a>
                    <a href="#tab-publish" class="nav-tab"                data-tab="publish"><?php esc_html_e('Veröffentlichung', 'auto-quill'); ?></a>
                    <a href="#tab-prompts" class="nav-tab"                data-tab="prompts"><?php esc_html_e('Prompts', 'auto-quill'); ?></a>
                    <a href="#tab-debug"   class="nav-tab"                data-tab="debug"><?php esc_html_e('Debug', 'auto-quill'); ?></a>
                </h2>

                <div class="auto-quill-tab-panel" data-tab="ki">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ai_provider"><?php esc_html_e('KI-Provider', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <select id="ai_provider" name="<?php echo esc_attr(C::OPTION_KEY); ?>[ai_provider]">
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
                                <?php
                                $key_from_const = C::ai_api_key_from_constant();
                                $has_stored_key = !empty($settings['ai_api_key']);
                                $placeholder    = $has_stored_key
                                    ? esc_attr__('Gespeicherter Schlüssel — leer lassen, um ihn zu behalten', 'auto-quill')
                                    : esc_attr__('sk-…', 'auto-quill');
                                ?>
                                <input type="password" id="ai_api_key"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[ai_api_key]"
                                       value=""
                                       placeholder="<?php echo $placeholder; ?>"
                                       autocomplete="new-password"
                                       <?php disabled($key_from_const); ?>
                                       style="width: 300px;">
                                <p class="description">
                                    <?php if ($key_from_const): ?>
                                        <?php esc_html_e('Schlüssel wird aus der Konstante AUTO_QUILL_AI_KEY in wp-config.php geladen und hat Vorrang vor diesem Feld.', 'auto-quill'); ?>
                                    <?php else: ?>
                                        <?php esc_html_e('Für mehr Sicherheit kann der Schlüssel auch in wp-config.php als AUTO_QUILL_AI_KEY definiert werden.', 'auto-quill'); ?>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="openai_model"><?php esc_html_e('OpenAI-Modell', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="openai_model"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[openai_model]"
                                       value="<?php echo esc_attr($settings['openai_model'] ?? C::DEFAULT_OPENAI_MODEL); ?>"
                                       placeholder="<?php echo esc_attr(C::DEFAULT_OPENAI_MODEL); ?>"
                                       style="width: 300px;">
                                <p class="description"><?php
                                    /* translators: %s: default model name */
                                    printf(esc_html__('Standard: %s', 'auto-quill'), '<code>' . esc_html(C::DEFAULT_OPENAI_MODEL) . '</code>');
                                ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="claude_model"><?php esc_html_e('Claude-Modell', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="claude_model"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[claude_model]"
                                       value="<?php echo esc_attr($settings['claude_model'] ?? C::DEFAULT_CLAUDE_MODEL); ?>"
                                       placeholder="<?php echo esc_attr(C::DEFAULT_CLAUDE_MODEL); ?>"
                                       style="width: 300px;">
                                <p class="description"><?php
                                    /* translators: %s: default model name */
                                    printf(esc_html__('Standard: %s', 'auto-quill'), '<code>' . esc_html(C::DEFAULT_CLAUDE_MODEL) . '</code>');
                                ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="auto-quill-tab-panel" data-tab="publish" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="post_status"><?php esc_html_e('Standard Post-Status', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <select id="post_status" name="<?php echo esc_attr(C::OPTION_KEY); ?>[post_status]">
                                    <option value="draft" <?php selected($settings['post_status'] ?? '', 'draft'); ?>>Entwurf</option>
                                    <option value="publish" <?php selected($settings['post_status'] ?? '', 'publish'); ?>>Veröffentlicht</option>
                                    <option value="pending" <?php selected($settings['post_status'] ?? '', 'pending'); ?>>Genehmigung ausstehend</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label>
                                    <input type="hidden"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[auto_publish]"
                                           value="0">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[auto_publish]"
                                           value="1"
                                           <?php checked(!empty($settings['auto_publish'])); ?>>
                                    <?php esc_html_e('Posts automatisch veröffentlichen', 'auto-quill'); ?>
                                </label>
                            </th>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="rss_lookback_days"><?php esc_html_e('RSS-Rückblick (Tage)', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="rss_lookback_days" min="0" max="365" step="1"
                                       name="<?php echo esc_attr(C::OPTION_KEY); ?>[rss_lookback_days]"
                                       value="<?php echo esc_attr((string) ($settings['rss_lookback_days'] ?? 7)); ?>"
                                       style="width: 100px;">
                                <p class="description"><?php esc_html_e('Wie viele Tage zurück sollen Feed-Artikel berücksichtigt werden? 0 = unbegrenzt. Ältere Artikel werden auch aus der Datenbank entfernt.', 'auto-quill'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="auto-quill-tab-panel" data-tab="prompts" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="prompt_title"><?php esc_html_e('Prompt: Titel', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <textarea id="prompt_title" rows="8" class="large-text code"
                                          name="<?php echo esc_attr(C::OPTION_KEY); ?>[prompt_title]"><?php
                                    echo esc_textarea($settings['prompt_title'] ?? C::defaults()['prompt_title']);
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Anweisungen für die KI zur Erzeugung des Beitrags-Titels. Unterstützte Platzhalter:', 'auto-quill'); ?>
                                    <code>{topic_title}</code>, <code>{source_block}</code>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="prompt_body"><?php esc_html_e('Prompt: Beitragstext', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <textarea id="prompt_body" rows="10" class="large-text code"
                                          name="<?php echo esc_attr(C::OPTION_KEY); ?>[prompt_body]"><?php
                                    echo esc_textarea($settings['prompt_body'] ?? C::defaults()['prompt_body']);
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Anweisungen für die KI zur Erstellung des Blog-Beitrags. Unterstützte Platzhalter:', 'auto-quill'); ?>
                                    <code>{title}</code>, <code>{source_block}</code>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="prompt_excerpt"><?php esc_html_e('Prompt: Social-Media-Auszug', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <textarea id="prompt_excerpt" rows="6" class="large-text code"
                                          name="<?php echo esc_attr(C::OPTION_KEY); ?>[prompt_excerpt]"><?php
                                    echo esc_textarea($settings['prompt_excerpt'] ?? C::defaults()['prompt_excerpt']);
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Anweisungen für die KI zur Erstellung des Auszugs. Unterstützte Platzhalter:', 'auto-quill'); ?>
                                    <code>{title}</code>, <code>{content_excerpt}</code>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="prompt_category"><?php esc_html_e('Prompt: Kategorie', 'auto-quill'); ?></label>
                            </th>
                            <td>
                                <textarea id="prompt_category" rows="8" class="large-text code"
                                          name="<?php echo esc_attr(C::OPTION_KEY); ?>[prompt_category]"><?php
                                    echo esc_textarea($settings['prompt_category'] ?? C::defaults()['prompt_category']);
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Anweisungen für die KI zur Auswahl der Kategorien. Unterstützte Platzhalter:', 'auto-quill'); ?>
                                    <code>{title}</code>, <code>{content_excerpt}</code>, <code>{categories_list}</code>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="auto-quill-tab-panel" data-tab="debug" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Debug-Logging', 'auto-quill'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="hidden"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[debug_logging]"
                                           value="0">
                                    <input type="checkbox"
                                           id="debug_logging"
                                           name="<?php echo esc_attr(C::OPTION_KEY); ?>[debug_logging]"
                                           value="1"
                                           <?php checked(!empty($settings['debug_logging'])); ?>>
                                    <?php esc_html_e('Ausführliches Logging aktivieren (Info/Debug)', 'auto-quill'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Wenn aktiv, werden auch Info- und Debug-Einträge inkl. (gekürzter) API-Payloads aufgezeichnet. Standard: nur Warnungen und Fehler. Logs sind unter „AutoQuill → Logs" und in der Browser-Konsole sichtbar.', 'auto-quill'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>

            <div class="auto-quill-tab-panel" data-tab="debug" style="display:none;">
                <?php StatusPanel::render(); ?>
            </div>
        </div>
        <?php
    }
}
