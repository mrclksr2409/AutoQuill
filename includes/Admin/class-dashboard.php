<?php
namespace AutoQuill\Admin;

use AutoQuill\AI\Selector;
use AutoQuill\Core\Constants as C;
use AutoQuill\Database\ArticlesRepository;
use AutoQuill\Database\SourcesRepository;
use AutoQuill\Database\TopicsRepository;
use AutoQuill\RSS\Fetcher;

class Dashboard {
    public static function boot(): void {
        add_action('wp_ajax_' . C::ACTION_FETCH, [self::class, 'handle_fetch_now']);
        add_action('wp_ajax_' . C::ACTION_RECRAWL, [self::class, 'handle_recrawl']);
        add_action('wp_ajax_' . C::ACTION_RESELECT, [self::class, 'handle_reselect']);
    }

    public static function publish_button_label(array $settings): string {
        if (!empty($settings['auto_publish'])) {
            return __('Post veröffentlichen', 'auto-quill');
        }
        switch ($settings['post_status'] ?? 'draft') {
            case 'draft':
                return __('Als Entwurf speichern', 'auto-quill');
            case 'pending':
                return __('Zur Prüfung einreichen', 'auto-quill');
            case 'publish':
                return __('Post veröffentlichen', 'auto-quill');
            default:
                return __('Post veröffentlichen', 'auto-quill');
        }
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

    /**
     * CSS class for a topic rating. The score never goes into a style
     * attribute; only this fixed set of classes reaches the markup.
     */
    private static function rating_class(int $rating): string {
        if ($rating >= 75) {
            return 'is-rating-high';
        }
        if ($rating >= 50) {
            return 'is-rating-mid';
        }
        return 'is-rating-low';
    }

    private static function post_status_label(string $status): string {
        $object = get_post_status_object($status);
        return $object && !empty($object->label) ? (string) $object->label : $status;
    }

    /**
     * Resolves the blog post belonging to each listed article, in bulk.
     *
     * Two sources, in order: the articles.post_id column, and - for rows whose
     * link predates DB version 1.4 or that were re-inserted after a retention
     * pass - the durable post meta, looked up in one query rather than one per
     * row. A post that was deleted or trashed simply does not come back, so the
     * row renders as unlinked without any stale state to clean up.
     *
     * @param array $items Rows from ArticlesRepository::paginate().
     * @return array<int, array{id:int,title:string,status:string,edit_url:string}> keyed by article id
     */
    private static function resolve_linked_posts(array $items): array {
        global $wpdb;

        $post_ids = [];
        $by_url   = [];

        foreach ($items as $row) {
            $article_id = (int) $row->id;
            $post_id    = (int) ($row->post_id ?? 0);

            if ($post_id > 0) {
                $post_ids[$post_id][] = $article_id;
                continue;
            }

            $url = (string) $row->article_url;
            if ($url !== '') {
                $by_url[$url][] = $article_id;
            }
        }

        if (!empty($by_url)) {
            $urls         = array_keys($by_url);
            $placeholders = implode(',', array_fill(0, count($urls), '%s'));
            $meta_rows    = $wpdb->get_results($wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = %s AND meta_value IN ($placeholders)",
                array_merge([C::META_SOURCE_URL], $urls)
            ));

            foreach ($meta_rows ?: [] as $meta) {
                $url = (string) $meta->meta_value;
                if (!isset($by_url[$url])) {
                    continue;
                }
                foreach ($by_url[$url] as $article_id) {
                    $post_ids[(int) $meta->post_id][] = $article_id;
                }
            }
        }

        if (empty($post_ids)) {
            return [];
        }

        $posts = get_posts([
            'post__in'       => array_keys($post_ids),
            'post_type'      => 'post',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
        ]);

        $resolved = [];
        foreach ($posts as $post) {
            $title = get_the_title($post);
            $info  = [
                'id'       => (int) $post->ID,
                'title'    => $title !== '' ? $title : __('(ohne Titel)', 'auto-quill'),
                'status'   => (string) $post->post_status,
                // 'raw' context: the default HTML-escapes "&", which would be
                // double-escaped again once it lands in an href.
                'edit_url' => (string) get_edit_post_link($post->ID, 'raw'),
            ];

            foreach ($post_ids[(int) $post->ID] ?? [] as $article_id) {
                $resolved[$article_id] = $info;
            }
        }

        return $resolved;
    }

    /**
     * Renders the <tbody> rows of the feed list. Shared by the initial page
     * render and the REST endpoint that powers filtering and paging, so the
     * markup - and its escaping - exists exactly once.
     *
     * @param array $items Rows from ArticlesRepository::paginate().
     */
    public static function render_feed_rows(array $items): string {
        ob_start();

        if (empty($items)) {
            ?>
            <tr class="auto-quill-feed-empty">
                <td colspan="5"><?php esc_html_e('Keine Feed-Einträge gefunden.', 'auto-quill'); ?></td>
            </tr>
            <?php
            return (string) ob_get_clean();
        }

        $linked_posts = self::resolve_linked_posts($items);
        $date_format  = get_option('date_format') . ' ' . get_option('time_format');

        foreach ($items as $row) {
            $article_id = (int) $row->id;
            $post       = $linked_posts[$article_id] ?? null;
            // Feed-supplied URLs are untrusted; esc_url() blanks out schemes
            // like javascript:, and an empty href would render a dead link.
            $url        = esc_url((string) $row->article_url);
            $published  = (string) $row->published_date;
            $excerpt    = trim(wp_strip_all_tags((string) $row->description));
            $button_class = $post ? 'button' : 'button button-primary';
            ?>
            <tr<?php echo $post ? ' class="is-linked"' : ''; ?>>
                <td class="auto-quill-feed-title">
                    <?php if ($url !== ''): ?>
                        <a href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($row->title); ?>
                        </a>
                    <?php else: ?>
                        <?php echo esc_html($row->title); ?>
                    <?php endif; ?>
                    <?php if ($excerpt !== ''): ?>
                        <span class="auto-quill-feed-excerpt">
                            <?php echo esc_html(mb_substr($excerpt, 0, 160)); ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html((string) ($row->source_title ?? '')); ?></td>
                <td class="auto-quill-feed-date">
                    <?php
                    if ($published !== '' && $published !== '0000-00-00 00:00:00') {
                        echo esc_html(mysql2date($date_format, $published));
                    } else {
                        echo '&ndash;';
                    }
                    ?>
                </td>
                <td class="auto-quill-feed-post">
                    <?php if ($post): ?>
                        <a href="<?php echo esc_url($post['edit_url']); ?>">
                            <?php echo esc_html($post['title']); ?>
                        </a>
                        <span class="auto-quill-post-status">
                            <?php echo esc_html(self::post_status_label($post['status'])); ?>
                        </span>
                    <?php else: ?>
                        &ndash;
                    <?php endif; ?>
                </td>
                <td class="auto-quill-feed-action">
                    <a class="<?php echo esc_attr($button_class); ?> auto-quill-generate-link"
                       href="<?php echo esc_url(GeneratePage::url(['article_id' => $article_id])); ?>">
                        <?php
                        echo $post
                            ? esc_html__('Erneut generieren', 'auto-quill')
                            : esc_html__('Blog-Post generieren', 'auto-quill');
                        ?>
                    </a>
                </td>
            </tr>
            <?php
        }

        return (string) ob_get_clean();
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }

        $today        = current_time('Y-m-d');
        $today_topics = (new TopicsRepository())->find_by_date($today);
        $sources_repository = new SourcesRepository();
        $sources      = $sources_repository->active();
        // Deactivated feeds can still have articles in the table, so the feed
        // filter lists all of them, not just the active ones.
        $all_sources  = $sources_repository->all();
        ?>
        <div class="wrap auto-quill-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php Notices::flush(); ?>

            <div class="auto-quill-panel">
                <h2 class="nav-tab-wrapper auto-quill-dashboard-tabs">
                    <a href="#dash-topics" class="nav-tab nav-tab-active" data-tab="topics">
                        <?php esc_html_e('Top-Themen', 'auto-quill'); ?>
                    </a>
                    <a href="#dash-articles" class="nav-tab" data-tab="articles">
                        <?php esc_html_e('Alle Feed-Einträge', 'auto-quill'); ?>
                    </a>
                </h2>

                <div class="auto-quill-dash-panel" data-tab="topics">
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
                        <?php
                        $topics = json_decode($today_topics->topics, true) ?: [];
                        // The Selector already stores topics in rating order;
                        // this only re-sorts rows written before that change.
                        $topics = Selector::sort_by_rating($topics);

                        $articles_repo     = new ArticlesRepository();
                        $sources_repo      = new SourcesRepository();
                        $source_name_cache = [];
                        ?>
                        <div id="topics-list" class="topics-list">
                            <?php foreach ($topics as $idx => $topic): ?>
                                <?php
                                $article_id  = (int) ($topic['article_id'] ?? 0);
                                $article_url = '';
                                $source_name = '';
                                if ($article_id > 0) {
                                    $article = $articles_repo->find($article_id);
                                    if ($article) {
                                        $article_url = (string) $article->article_url;
                                        $source_id   = (int) $article->source_id;
                                        if ($source_id > 0) {
                                            if (!array_key_exists($source_id, $source_name_cache)) {
                                                $source = $sources_repo->find($source_id);
                                                $source_name_cache[$source_id] = $source ? (string) $source->title : '';
                                            }
                                            $source_name = $source_name_cache[$source_id];
                                        }
                                    }
                                }

                                // Topics stored before the rating existed have no
                                // score - show no badge rather than a bogus zero.
                                $rating = (isset($topic['rating']) && $topic['rating'] !== null)
                                    ? (int) $topic['rating']
                                    : null;
                                $rating_reason = trim((string) ($topic['rating_reason'] ?? ''));
                                ?>
                                <div class="topic-card">
                                    <h3><?php echo esc_html($topic['title'] ?? ''); ?></h3>

                                    <?php if ($rating !== null): ?>
                                        <p class="auto-quill-rating-line">
                                            <span class="auto-quill-rating <?php echo esc_attr(self::rating_class($rating)); ?>"
                                                  title="<?php esc_attr_e('Bewertung von 0 bis 100', 'auto-quill'); ?>">
                                                <?php echo (int) $rating; ?>
                                            </span>
                                            <?php if ($rating_reason !== ''): ?>
                                                <span class="auto-quill-rating-reason">
                                                    <?php echo esc_html($rating_reason); ?>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>

                                    <p><?php echo esc_html(substr((string) ($topic['summary'] ?? ''), 0, 200)); ?></p>
                                    <?php if ($source_name !== '' || $article_url !== ''): ?>
                                        <p class="auto-quill-topic-source">
                                            <strong><?php esc_html_e('Quelle:', 'auto-quill'); ?></strong>
                                            <?php if ($source_name !== ''): ?>
                                                <?php echo esc_html($source_name); ?>
                                            <?php endif; ?>
                                            <?php if ($article_url !== ''): ?>
                                                <?php if ($source_name !== ''): ?> &ndash; <?php endif; ?>
                                                <a href="<?php echo esc_url($article_url); ?>"
                                                   target="_blank" rel="noopener noreferrer">
                                                    <?php esc_html_e('Originalartikel', 'auto-quill'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <a class="button button-primary auto-quill-generate-link"
                                       href="<?php echo esc_url(GeneratePage::url([
                                           'topic_id'    => (int) $today_topics->id,
                                           'topic_index' => (int) $idx,
                                       ])); ?>">
                                        <?php esc_html_e('Blog-Post generieren', 'auto-quill'); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php esc_html_e('Noch keine Themen für heute verfügbar. Topics werden täglich aktualisiert oder über die Buttons oben manuell ausgelöst.', 'auto-quill'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="auto-quill-dash-panel" data-tab="articles" style="display:none;">
                    <div class="auto-quill-feed-filters">
                        <label for="auto-quill-feed-source" class="screen-reader-text">
                            <?php esc_html_e('Quelle filtern', 'auto-quill'); ?>
                        </label>
                        <select id="auto-quill-feed-source">
                            <option value="0"><?php esc_html_e('Alle Quellen', 'auto-quill'); ?></option>
                            <?php foreach ($all_sources as $source): ?>
                                <option value="<?php echo (int) $source->id; ?>">
                                    <?php echo esc_html($source->title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="auto-quill-feed-search" class="screen-reader-text">
                            <?php esc_html_e('Feed-Einträge durchsuchen', 'auto-quill'); ?>
                        </label>
                        <input type="search" id="auto-quill-feed-search"
                               placeholder="<?php esc_attr_e('Titel oder Beschreibung durchsuchen', 'auto-quill'); ?>">

                        <label class="auto-quill-feed-unlinked-label">
                            <input type="checkbox" id="auto-quill-feed-unlinked">
                            <?php esc_html_e('Nur ohne Blog-Post', 'auto-quill'); ?>
                        </label>

                        <button type="button" class="button" id="auto-quill-feed-apply">
                            <?php esc_html_e('Filtern', 'auto-quill'); ?>
                        </button>
                    </div>

                    <div id="auto-quill-feed-status" class="auto-quill-feed-status" hidden></div>

                    <table class="widefat striped auto-quill-feed-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Titel', 'auto-quill'); ?></th>
                                <th scope="col"><?php esc_html_e('Feed', 'auto-quill'); ?></th>
                                <th scope="col"><?php esc_html_e('Datum', 'auto-quill'); ?></th>
                                <th scope="col"><?php esc_html_e('Blog-Post', 'auto-quill'); ?></th>
                                <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Aktion', 'auto-quill'); ?></span></th>
                            </tr>
                        </thead>
                        <tbody id="auto-quill-feed-body"></tbody>
                    </table>

                    <div id="auto-quill-feed-pagination" class="auto-quill-feed-pagination" hidden>
                        <button type="button" class="button" id="auto-quill-feed-prev">
                            &laquo; <?php esc_html_e('Zurück', 'auto-quill'); ?>
                        </button>
                        <span id="auto-quill-feed-page-info"></span>
                        <button type="button" class="button" id="auto-quill-feed-next">
                            <?php esc_html_e('Weiter', 'auto-quill'); ?> &raquo;
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
