<?php
namespace AutoQuill\Rest;

use AutoQuill\AI\KeywordSuggester;
use AutoQuill\Image\PixabayClient;

class ImagesService {
    public static function suggest_keywords(\WP_REST_Request $request): \WP_REST_Response {
        $params  = $request->get_json_params() ?: [];
        $title   = (string) ($params['title']   ?? '');
        $excerpt = (string) ($params['excerpt'] ?? '');

        $keywords = (new KeywordSuggester())->suggest($title, $excerpt);

        return new \WP_REST_Response([
            'keywords' => $keywords,
        ], 200);
    }

    public static function search(\WP_REST_Request $request): \WP_REST_Response {
        $query    = trim((string) $request->get_param('query'));
        $page     = max(1, (int) $request->get_param('page'));
        $per_page = (int) ($request->get_param('per_page') ?: 20);

        if ($query === '') {
            return new \WP_REST_Response([
                'error' => __('Bitte einen Suchbegriff angeben.', 'auto-quill'),
            ], 400);
        }

        $result = (new PixabayClient())->search($query, $page, $per_page);

        if (is_wp_error($result)) {
            $code   = $result->get_error_code();
            $status = $code === 'no_pixabay_key' ? 400 : 502;
            return new \WP_REST_Response([
                'error' => $result->get_error_message(),
                'code'  => $code,
            ], $status);
        }

        return new \WP_REST_Response($result, 200);
    }
}
