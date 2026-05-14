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
                                <code>{title}</code>, <code>{source_block}</code>, <code>{categories_list}</code>
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
                                <code>{title}</code>, <code>{source_block}</code>, <code>{categories_list}</code>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <?php StatusPanel::render(); ?>
        </div>
        <?php
    }
}
