<?php
namespace AutoQuill\Rest;

use AutoQuill\Admin\Dashboard;
use AutoQuill\Database\ArticlesRepository;
use AutoQuill\Database\Schema;

class ArticlesService {
    const DEFAULT_PER_PAGE = 20;

    public static function list_articles(\WP_REST_Request $request): \WP_REST_Response {
        // REST requests do not fire admin_init, where the schema check is
        // hooked, and this reads the articles.post_id column added in DB
        // version 1.4. The static guard inside makes repeats a no-op.
        Schema::ensure_tables();

        $page     = max(1, (int) $request->get_param('page'));
        $per_page = (int) $request->get_param('per_page');
        $per_page = $per_page > 0 ? min(100, $per_page) : self::DEFAULT_PER_PAGE;

        $result = (new ArticlesRepository())->paginate([
            'page'      => $page,
            'per_page'  => $per_page,
            'source_id' => (int) $request->get_param('source_id'),
            'search'    => (string) $request->get_param('search'),
            'linked'    => (string) $request->get_param('linked'),
        ]);

        $total = (int) $result['total'];

        return new \WP_REST_Response([
            // Rendered server-side on purpose: feed titles, descriptions and
            // URLs are untrusted third-party content, and escaping belongs in
            // PHP next to the rest of this plugin's output - not in hand-rolled
            // DOM construction in the browser.
            'html'     => Dashboard::render_feed_rows($result['items']),
            'total'    => $total,
            'page'     => $page,
            'pages'    => max(1, (int) ceil($total / $per_page)),
            'per_page' => $per_page,
        ], 200);
    }
}
