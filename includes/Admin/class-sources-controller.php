<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;
use AutoQuill\Database\SourcesRepository;

class SourcesController {
    public static function boot(): void {
        add_action('admin_post_' . C::ACTION_ADD,    [self::class, 'handle_add']);
        add_action('admin_post_' . C::ACTION_DELETE, [self::class, 'handle_delete']);
    }

    public static function handle_add(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }
        check_admin_referer(C::ACTION_ADD, 'auto_quill_nonce');

        $title = sanitize_text_field(wp_unslash($_POST['source_title'] ?? ''));
        $url   = esc_url_raw(wp_unslash($_POST['source_url'] ?? ''), ['http', 'https']);

        if ($title === '' || $url === '' || !wp_http_validate_url($url)) {
            Notices::error(__('Ungültige Eingabe.', 'auto-quill'));
        } else {
            $repo = new SourcesRepository();
            $id   = $repo->insert($title, $url, true);
            if ($id) {
                Notices::success(__('RSS-Quelle hinzugefügt.', 'auto-quill'));
            } else {
                $msg = __('Speichern fehlgeschlagen.', 'auto-quill');
                $err = $repo->last_error();
                if ($err !== '') {
                    $msg .= ' (' . $err . ')';
                }
                Notices::error($msg);
            }
        }

        wp_safe_redirect(self::page_url());
        exit;
    }

    public static function handle_delete(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }
        $id = absint($_POST['source_id'] ?? 0);
        check_admin_referer(C::ACTION_DELETE . '_' . $id, 'auto_quill_nonce');

        if ($id <= 0) {
            Notices::error(__('Ungültige Quelle.', 'auto-quill'));
        } else {
            $repo = new SourcesRepository();
            if ($repo->delete($id)) {
                Notices::success(__('RSS-Quelle gelöscht.', 'auto-quill'));
            } else {
                $msg = __('Löschen fehlgeschlagen.', 'auto-quill');
                $err = $repo->last_error();
                if ($err !== '') {
                    $msg .= ' (' . $err . ')';
                }
                Notices::error($msg);
            }
        }

        wp_safe_redirect(self::page_url());
        exit;
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }

        $sources = (new SourcesRepository())->all();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php Notices::flush(); ?>

            <div class="auto-quill-add-source">
                <h2><?php esc_html_e('Neue RSS-Quelle hinzufügen', 'auto-quill'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(C::ACTION_ADD); ?>">
                    <?php wp_nonce_field(C::ACTION_ADD, 'auto_quill_nonce'); ?>

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
                                <input type="url" id="source-url" name="source_url" required
                                       style="width: 100%; max-width: 500px;">
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
                                    <a href="<?php echo esc_url($source->feed_url); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html(self::shorten($source->feed_url)); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ((int) $source->is_active === 1): ?>
                                        <span class="dashicons dashicons-yes" style="color: green;"></span>
                                        <?php esc_html_e('Aktiv', 'auto-quill'); ?>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-no" style="color: red;"></span>
                                        <?php esc_html_e('Inaktiv', 'auto-quill'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                          style="display:inline"
                                          onsubmit="return confirm('<?php echo esc_js(__('Wirklich löschen?', 'auto-quill')); ?>');">
                                        <input type="hidden" name="action" value="<?php echo esc_attr(C::ACTION_DELETE); ?>">
                                        <input type="hidden" name="source_id" value="<?php echo (int) $source->id; ?>">
                                        <?php wp_nonce_field(C::ACTION_DELETE . '_' . $source->id, 'auto_quill_nonce'); ?>
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

    private static function page_url(): string {
        return admin_url('admin.php?page=' . C::SOURCES_PAGE_SLUG);
    }

    private static function shorten(string $url, int $len = 60): string {
        return strlen($url) > $len ? substr($url, 0, $len) . '…' : $url;
    }
}
