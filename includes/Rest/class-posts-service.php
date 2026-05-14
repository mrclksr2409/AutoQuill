<?php
namespace AutoQuill\Rest;

use AutoQuill\Core\Constants as C;
use AutoQuill\Database\TopicsRepository;

class PostsService {
    public static function get_today_topics(\WP_REST_Request $request): \WP_REST_Response {
        $today = current_time('Y-m-d');
        $row   = (new TopicsRepository())->find_by_date($today);

        if (!$row) {
            return new \WP_REST_Response(['topics' => []], 200);
        }
        return new \WP_REST_Response([
            'topics' => json_decode($row->topics, true) ?: [],
            'status' => $row->status,
        ], 200);
    }

    public static function publish_post(\WP_REST_Request $request): \WP_REST_Response {
        $params       = $request->get_json_params() ?: [];
        $post_title   = (string) ($params['post_title']   ?? '');
        $post_content = (string) ($params['post_content'] ?? '');
        $post_excerpt = (string) ($params['post_excerpt'] ?? '');
        $category_ids = (array)  ($params['category_ids'] ?? []);
        $topic_id     = (int)    ($params['topic_id']     ?? 0);

        if ($post_content === '' || $post_title === '') {
            return new \WP_REST_Response(['error' => __('Post-Titel und Inhalt erforderlich', 'auto-quill')], 400);
        }

        $settings = get_option(C::OPTION_KEY, C::defaults());
        if (!is_array($settings)) {
            $settings = C::defaults();
        }
        $post_status = !empty($settings['auto_publish'])
            ? 'publish'
            : ($settings['post_status'] ?? 'draft');

        $insert_args = [
            'post_title'   => sanitize_text_field($post_title),
            'post_content' => wp_kses_post($post_content),
            'post_excerpt' => sanitize_textarea_field($post_excerpt),
            'post_status'  => $post_status,
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
        ];

        $category_ids = array_values(array_filter(array_map('intval', $category_ids)));
        if (!empty($category_ids)) {
            $insert_args['post_category'] = $category_ids;
        }

        $post_id = wp_insert_post($insert_args);

        if (is_wp_error($post_id)) {
            return new \WP_REST_Response(['error' => $post_id->get_error_message()], 500);
        }

        if ($topic_id > 0) {
            (new TopicsRepository())->mark_published($topic_id, (int) $post_id);
        }

        return new \WP_REST_Response([
            'success' => true,
            'post_id' => (int) $post_id,
            'message' => sprintf(
                /* translators: 1: post ID, 2: post status */
                __('Post %1$s erstellt und als %2$s gespeichert', 'auto-quill'),
                $post_id,
                $post_status
            ),
        ], 201);
    }
}
