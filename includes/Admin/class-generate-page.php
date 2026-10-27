<?php
namespace AutoQuill\Admin;

use AutoQuill\AI\Writer;
use AutoQuill\Core\Access;
use AutoQuill\Core\Constants as C;
use AutoQuill\Database\SourcesRepository;

/**
 * Hidden screen that turns one feed entry or daily topic into a blog post.
 *
 * Deliberately a VIEW of the dashboard page (admin.php?page=auto-quill with
 * aq_view=generate), not a page of its own.
 *
 * Registering it as a submenu and then calling remove_submenu_page() looks
 * tidier but breaks access: user_can_access_admin_page() resolves the page's
 * hook name through get_admin_page_parent(), which searches the $submenu array.
 * With the entry removed the parent comes back empty, the hook name is computed
 * as "admin_page_<slug>" instead of the "<parent>_page_<slug>" that was stored
 * in $_registered_pages at registration time, and WordPress refuses the request
 * with "Sorry, you are not allowed to access this page" - even for an
 * administrator. Sharing the dashboard's registered page sidesteps menu
 * registration, capability resolution and menu highlighting entirely.
 */
class GeneratePage {
    /** Is the current request asking for the generate view? */
    public static function is_requested(): bool {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view switch.
        return isset($_GET['page'], $_GET[C::VIEW_PARAM])
            && $_GET['page'] === C::MENU_SLUG
            && $_GET[C::VIEW_PARAM] === C::VIEW_GENERATE;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    /**
     * Builds the link the list screen uses to reach this view.
     *
     * @param array<string, int|string> $args Either ['article_id' => int] or
     *                                        ['topic_id' => int, 'topic_index' => int].
     */
    public static function url(array $args): string {
        $url = add_query_arg(
            array_merge([
                'page'         => C::MENU_SLUG,
                C::VIEW_PARAM  => C::VIEW_GENERATE,
            ], $args),
            admin_url('admin.php')
        );

        // Generating costs a paid API call, so the GET that auto-starts it is
        // CSRF-relevant. Without a valid nonce the view still renders, it just
        // waits for an explicit click.
        return wp_nonce_url($url, C::NONCE_GENERATE);
    }

    public static function back_url(bool $from_article): string {
        $url = admin_url('admin.php?page=' . C::MENU_SLUG);
        return $from_article ? $url . '#dash-articles' : $url;
    }

    /**
     * Reads the screen's query args once, for both render() and the script data.
     *
     * @return array{params: array<string, int>, autostart: bool, back_url: string}
     */
    public static function request_context(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- the
        // nonce gates the auto-start, not read access to this screen.
        $article_id  = isset($_GET['article_id']) ? absint($_GET['article_id']) : 0;
        $topic_id    = isset($_GET['topic_id']) ? absint($_GET['topic_id']) : 0;
        $topic_index = isset($_GET['topic_index']) ? (int) $_GET['topic_index'] : -1;
        $nonce       = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $params = [];
        if ($article_id > 0) {
            $params['article_id'] = $article_id;
        } elseif ($topic_id > 0) {
            $params['topic_id']    = $topic_id;
            $params['topic_index'] = $topic_index;
        }

        return [
            'params'    => $params,
            'autostart' => $nonce !== '' && (bool) wp_verify_nonce($nonce, C::NONCE_GENERATE),
            'back_url'  => self::back_url($article_id > 0),
        ];
    }

    public static function render(): void {
        if (!current_user_can(Access::CAP)) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }

        $context  = self::request_context();
        $params   = $context['params'];
        $back_url = $context['back_url'];

        if (empty($params)) {
            self::render_error(
                __('Es wurde kein Feed-Eintrag und kein Thema übergeben.', 'auto-quill'),
                $back_url
            );
            return;
        }

        $preview = Writer::source_preview($params);
        if (is_wp_error($preview)) {
            self::render_error($preview->get_error_message(), $back_url);
            return;
        }

        $settings = get_option(C::OPTION_KEY, C::defaults());
        if (!is_array($settings)) {
            $settings = C::defaults();
        }

        self::render_screen($preview, $params, $context['autostart'], $back_url, $settings);
    }

    private static function render_error(string $message, string $back_url): void {
        ?>
        <div class="wrap auto-quill-wrap">
            <?php self::render_header($back_url); ?>
            <div class="notice notice-error"><p><?php echo esc_html($message); ?></p></div>
        </div>
        <?php
    }

    private static function render_header(string $back_url): void {
        \WPB_Admin_UI::header([
            'title'    => __('Blog-Post erstellen', 'auto-quill'),
            'subtitle' => __('Beitrag aus einem Feed-Eintrag oder Top-Thema generieren', 'auto-quill'),
            'icon'     => 'dashicons-rss',
            'version'  => AUTO_QUILL_VERSION,
            'actions'  => [
                [
                    'label' => __('Zurück zur Übersicht', 'auto-quill'),
                    'url'   => $back_url,
                ],
            ],
        ]);
    }

    /**
     * @param array{article: ?object, topic: array, topic_id: int, topic_index: int,
     *              ai_source: string, raw_html: string, truncated: bool} $preview
     */
    private static function render_screen(array $preview, array $params, bool $autostart, string $back_url, array $settings): void {
        $article       = $preview['article'];
        $publish_label = Dashboard::publish_button_label($settings);
        $headline      = $article
            ? (string) $article->title
            : (string) ($preview['topic']['title'] ?? '');
        ?>
        <div class="wrap auto-quill-wrap">
            <?php self::render_header($back_url); ?>

            <?php Notices::flush(); ?>

            <div class="wpb-grid wpb-grid--2">
                <div class="wpb-card auto-quill-source-column">
                    <div class="wpb-card__header">
                        <h2 class="wpb-card__title"><?php esc_html_e('Originaltext', 'auto-quill'); ?></h2>
                    </div>
                    <div class="wpb-card__body">
                        <?php self::render_article_header($article, $headline); ?>

                        <?php // Client-side tabs are handled by WP-Backend UI (data-wpb-tabs). ?>
                        <nav class="nav-tab-wrapper auto-quill-source-tabs" data-wpb-tabs>
                            <a href="#source-ai" class="nav-tab nav-tab-active" data-tab="ai">
                                <?php esc_html_e('Quelltext für die KI', 'auto-quill'); ?>
                            </a>
                            <a href="#source-raw" class="nav-tab" data-tab="raw">
                                <?php esc_html_e('Roh-HTML', 'auto-quill'); ?>
                            </a>
                        </nav>

                        <div id="source-ai" class="auto-quill-source-panel wpb-tab-panel" data-tab="ai">
                            <p class="description">
                                <?php esc_html_e('Exakt der Text, den das Modell erhalten hat. Was hier nicht steht, darf im Beitrag nicht auftauchen.', 'auto-quill'); ?>
                                <?php if ($preview['truncated']): ?>
                                    <strong><?php
                                        printf(
                                            /* translators: %d: character limit */
                                            esc_html__('Der Artikel wurde auf %d Zeichen gekürzt.', 'auto-quill'),
                                            (int) Writer::SOURCE_CONTENT_LIMIT
                                        );
                                    ?></strong>
                                <?php endif; ?>
                            </p>
                            <pre class="auto-quill-source-text wpb-code"><?php echo esc_html($preview['ai_source']); ?></pre>
                        </div>

                        <div id="source-raw" class="auto-quill-source-panel wpb-tab-panel" data-tab="raw" hidden>
                            <?php if (trim($preview['raw_html']) === ''): ?>
                                <?php
                                // alert() escapes its output.
                                echo \WPB_Admin_UI::alert(
                                    __('Für diesen Eintrag wurde kein Seiteninhalt gespeichert — die KI hat nur Titel und RSS-Beschreibung erhalten. Das erklärt einen dünnen oder ungenauen Beitrag.', 'auto-quill'),
                                    'warning'
                                );
                                ?>
                            <?php else: ?>
                                <p class="description">
                                    <?php esc_html_e('Der gespeicherte Seitenquelltext, unverändert und als Text dargestellt. Zum Ansehen der gerenderten Seite den Link oben verwenden.', 'auto-quill'); ?>
                                </p>
                                <pre class="auto-quill-source-text wpb-code"><?php echo esc_html($preview['raw_html']); ?></pre>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="wpb-card auto-quill-result-column" id="auto-quill-result-column">
                    <div class="wpb-card__header">
                        <h2 class="wpb-card__title"><?php esc_html_e('Generierter Beitrag', 'auto-quill'); ?></h2>
                    </div>
                    <div class="wpb-card__body">
                        <p class="auto-quill-generate-actions">
                            <button type="button" class="button button-primary" id="auto-quill-generate-btn" data-aq-busy-disable>
                                <?php
                                echo $autostart
                                    ? esc_html__('Neu generieren', 'auto-quill')
                                    : esc_html__('Jetzt generieren', 'auto-quill');
                                ?>
                            </button>
                        </p>

                        <?php
                        self::render_post_editor(
                            $publish_label,
                            $autostart
                                ? __('Der Beitrag wird gleich generiert…', 'auto-quill')
                                : __('Auf „Jetzt generieren" klicken, um den Beitrag zu erstellen.', 'auto-quill')
                        );
                        ?>
                    </div>
                </div>
            </div>

            <?php self::render_image_modal(); ?>
        </div>
        <?php
    }

    /**
     * The right-hand post editor: busy overlay, error box, title, preview,
     * excerpt, categories, featured image and the save button. Title, excerpt
     * and categories are shown read-only - they are edited in WordPress after
     * saving. Shared by the
     * generate screen and the interview screen; assets/post-editor.js drives it.
     */
    public static function render_post_editor(string $publish_label, string $placeholder): void {
        ?>
        <div class="auto-quill-result-body">
            <div id="auto-quill-busy" class="wpb-busy" hidden>
                <span class="wpb-spinner wpb-spinner--lg" aria-hidden="true"></span>
                <p class="auto-quill-busy-text" role="status" aria-live="polite">
                    <span id="auto-quill-busy-label"></span>
                    <span id="auto-quill-busy-elapsed" class="auto-quill-busy-elapsed"></span>
                </p>
            </div>

            <div id="auto-quill-generate-error" class="auto-quill-generate-error wpb-alert wpb-alert--error" role="alert" hidden></div>

            <div id="auto-quill-meta-fields" class="auto-quill-meta-fields">
                <div class="auto-quill-field">
                    <strong><?php esc_html_e('Titel', 'auto-quill'); ?></strong>
                    <h2 id="auto-quill-title" data-placeholder="<?php esc_attr_e('Wird automatisch von der KI gefüllt', 'auto-quill'); ?>" class="auto-quill-meta-value auto-quill-meta-title is-empty"><?php esc_html_e('Wird automatisch von der KI gefüllt', 'auto-quill'); ?></h2>
                </div>
                <div class="auto-quill-field auto-quill-field--body">
                    <strong><?php esc_html_e('Text', 'auto-quill'); ?></strong>
                    <div id="post-preview" class="post-preview wpb-card wpb-card--muted">
                        <p><?php echo esc_html($placeholder); ?></p>
                    </div>
                </div>
                <div class="auto-quill-field">
                    <strong><?php esc_html_e('Social-Media-Auszug', 'auto-quill'); ?></strong>
                    <p id="auto-quill-excerpt" data-placeholder="<?php esc_attr_e('Wird automatisch von der KI gefüllt', 'auto-quill'); ?>" class="auto-quill-meta-value is-empty"><?php esc_html_e('Wird automatisch von der KI gefüllt', 'auto-quill'); ?></p>
                </div>
                <div class="auto-quill-field">
                    <strong><?php esc_html_e('Kategorien', 'auto-quill'); ?></strong>
                    <p id="auto-quill-categories" data-placeholder="<?php esc_attr_e('Wird automatisch von der KI gefüllt', 'auto-quill'); ?>" class="auto-quill-meta-value is-empty"><?php esc_html_e('Wird automatisch von der KI gefüllt', 'auto-quill'); ?></p>
                </div>
                <div class="auto-quill-field">
                    <label>
                        <strong><?php esc_html_e('Beitragsbild', 'auto-quill'); ?></strong>
                        <span class="description"><?php esc_html_e('(optional, via Pixabay)', 'auto-quill'); ?></span>
                    </label>
                    <div id="auto-quill-image-preview" class="auto-quill-image-preview is-empty">
                        <span class="placeholder"><?php esc_html_e('Kein Bild ausgewählt', 'auto-quill'); ?></span>
                    </div>
                    <p class="auto-quill-image-actions">
                        <button type="button" class="button" id="auto-quill-pick-image-btn">
                            <?php esc_html_e('Bild auswählen', 'auto-quill'); ?>
                        </button>
                        <button type="button" class="button-link wpb-link-danger" id="auto-quill-clear-image-btn" hidden>
                            <?php esc_html_e('Bild entfernen', 'auto-quill'); ?>
                        </button>
                    </p>
                </div>
            </div>

            <button class="button button-primary" id="publish-post-btn" style="display:none;">
                <?php echo esc_html($publish_label); ?>
            </button>

            <div id="auto-quill-generate-result" class="auto-quill-generate-result wpb-alert wpb-alert--success" role="status" hidden></div>
        </div>
        <?php
    }

    /** Pixabay picker used by the post editor. */
    public static function render_image_modal(): void {
        ?>
        <?php // Native <dialog>: Esc and a backdrop click close it (WP-Backend UI). ?>
        <dialog id="auto-quill-image-modal" class="wpb-modal wpb-modal--wide" aria-labelledby="auto-quill-image-modal-title">
            <div class="wpb-modal__header">
                <h2 class="wpb-modal__title" id="auto-quill-image-modal-title"><?php esc_html_e('Beitragsbild auswählen', 'auto-quill'); ?></h2>
                <button type="button" class="wpb-modal__close" data-modal-close aria-label="<?php esc_attr_e('Schließen', 'auto-quill'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
            </div>
            <form class="auto-quill-modal-search" id="auto-quill-image-search-form">
                <input type="text" id="auto-quill-image-query"
                       placeholder="<?php esc_attr_e('Suchbegriff…', 'auto-quill'); ?>"
                       class="regular-text" autocomplete="off">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Suchen', 'auto-quill'); ?>
                </button>
            </form>
            <div class="wpb-modal__body">
                <div id="auto-quill-image-status" class="auto-quill-image-status wpb-muted" hidden></div>
                <div id="auto-quill-image-grid" class="auto-quill-image-grid"></div>
                <div id="auto-quill-image-pagination" class="auto-quill-image-pagination" hidden>
                    <button type="button" class="button" id="auto-quill-image-prev">&laquo; <?php esc_html_e('Zurück', 'auto-quill'); ?></button>
                    <span id="auto-quill-image-page-info"></span>
                    <button type="button" class="button" id="auto-quill-image-next"><?php esc_html_e('Weiter', 'auto-quill'); ?> &raquo;</button>
                </div>
            </div>
            <div class="wpb-modal__footer wpb-muted">
                <small>
                    <?php
                    printf(
                        /* translators: %s: link to Pixabay */
                        esc_html__('Bilder von %s — Pixabay Content License', 'auto-quill'),
                        '<a href="https://pixabay.com/" target="_blank" rel="noopener noreferrer">Pixabay</a>'
                    );
                    ?>
                </small>
            </div>
        </dialog>
        <?php
    }

    /**
     * Shows which article was actually resolved. For the topic path this can be
     * a title-match fallback rather than the stored article_id, so naming the
     * resolved row makes a surprising result explainable instead of mysterious.
     */
    private static function render_article_header($article, string $headline): void {
        $url       = $article ? esc_url((string) $article->article_url) : '';
        $feed_name = '';
        $published = '';

        if ($article) {
            $source_id = (int) $article->source_id;
            if ($source_id > 0) {
                $source = (new SourcesRepository())->find($source_id);
                if ($source) {
                    $feed_name = (string) $source->title;
                }
            }

            $date = (string) $article->published_date;
            if ($date !== '' && $date !== '0000-00-00 00:00:00') {
                $published = mysql2date(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    $date
                );
            }
        }
        ?>
        <div class="auto-quill-source-header">
            <h3 class="wpb-card__title"><?php echo esc_html($headline); ?></h3>
            <p class="auto-quill-source-meta wpb-muted wpb-small">
                <?php if ($feed_name !== ''): ?>
                    <span><?php echo esc_html($feed_name); ?></span>
                <?php endif; ?>
                <?php if ($published !== ''): ?>
                    <span><?php echo esc_html($published); ?></span>
                <?php endif; ?>
                <?php if ($url !== ''): ?>
                    <a href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Originalartikel öffnen', 'auto-quill'); ?> &nearr;
                    </a>
                <?php endif; ?>
            </p>
            <?php if (!$article): ?>
                <?php
                // alert() escapes its output.
                echo \WPB_Admin_UI::alert(
                    __('Zu diesem Thema konnte kein Feed-Eintrag zugeordnet werden. Die KI arbeitet nur mit Titel und Zusammenfassung.', 'auto-quill'),
                    'warning'
                );
                ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
