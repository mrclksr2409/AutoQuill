<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;

/**
 * Read-only "where did this post come from" box on the post edit screen.
 *
 * Display only: no fields, no save_post handler and therefore no nonce.
 */
class PostMetaBox {
    public static function boot(): void {
        add_action('add_meta_boxes', [self::class, 'register'], 10, 2);
    }

    /**
     * @param string        $post_type
     * @param \WP_Post|null $post
     */
    public static function register($post_type, $post = null): void {
        if ('post' !== $post_type) {
            return;
        }

        // Only show the box for posts AutoQuill actually created.
        if (!$post instanceof \WP_Post) {
            return;
        }
        if ((string) get_post_meta($post->ID, C::META_SOURCE_URL, true) === '') {
            return;
        }

        add_meta_box(
            'auto-quill-source',
            __('AutoQuill-Quelle', 'auto-quill'),
            [self::class, 'render'],
            'post',
            'side',
            'default'
        );
    }

    public static function render(\WP_Post $post): void {
        $source_url    = (string) get_post_meta($post->ID, C::META_SOURCE_URL, true);
        $article_title = (string) get_post_meta($post->ID, C::META_ARTICLE_TITLE, true);
        $feed_name     = (string) get_post_meta($post->ID, C::META_FEED_NAME, true);

        if ($source_url === '') {
            return;
        }

        $label = $article_title !== '' ? $article_title : $source_url;
        ?>
        <p class="auto-quill-meta-box">
            <?php if ($feed_name !== ''): ?>
                <strong><?php esc_html_e('Feed:', 'auto-quill'); ?></strong>
                <?php echo esc_html($feed_name); ?><br>
            <?php endif; ?>

            <strong><?php esc_html_e('Originalartikel:', 'auto-quill'); ?></strong><br>
            <a href="<?php echo esc_url($source_url); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html($label); ?>
            </a>
        </p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . C::MENU_SLUG)); ?>">
                <?php esc_html_e('Zum AutoQuill-Dashboard', 'auto-quill'); ?>
            </a>
        </p>
        <?php
    }
}
