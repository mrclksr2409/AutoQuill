<?php
namespace AutoQuill\Rest;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;
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
        $image_url    = esc_url_raw((string) ($params['image_url'] ?? ''));
        $image_alt    = sanitize_text_field((string) ($params['image_alt'] ?? ''));

        Logger::info('posts', 'publish_post-Request', [
            'topic_id'     => $topic_id,
            'title_len'    => strlen($post_title),
            'content_len'  => strlen($post_content),
            'category_ids' => $category_ids,
        ]);

        if ($post_content === '' || $post_title === '') {
            Logger::warning('posts', 'Publish abgebrochen: Titel oder Inhalt leer', ['topic_id' => $topic_id]);
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

        $post_id = wp_insert_post($insert_args, true);

        if (is_wp_error($post_id)) {
            Logger::error('posts', 'wp_insert_post fehlgeschlagen', [
                'topic_id' => $topic_id,
                'code'     => $post_id->get_error_code(),
                'message'  => $post_id->get_error_message(),
            ]);
            return new \WP_REST_Response(['error' => $post_id->get_error_message()], 500);
        }

        if ($topic_id > 0) {
            (new TopicsRepository())->mark_published($topic_id, (int) $post_id);
        }

        $attachment_id = 0;
        if ($image_url !== '' && wp_http_validate_url($image_url)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $sideload = media_sideload_image($image_url, (int) $post_id, $image_alt !== '' ? $image_alt : null, 'id');

            if (is_wp_error($sideload)) {
                Logger::warning('posts', 'Featured-Image-Sideload fehlgeschlagen', [
                    'post_id'   => (int) $post_id,
                    'image_url' => $image_url,
                    'code'      => $sideload->get_error_code(),
                    'message'   => $sideload->get_error_message(),
                ]);
            } else {
                $attachment_id = (int) $sideload;
                set_post_thumbnail((int) $post_id, $attachment_id);
                if ($image_alt !== '') {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_alt);
                }
                Logger::info('posts', 'Featured Image gesetzt', [
                    'post_id'       => (int) $post_id,
                    'attachment_id' => $attachment_id,
                ]);
            }
        }

        Logger::info('posts', 'Post erstellt', [
            'post_id'       => (int) $post_id,
            'topic_id'      => $topic_id,
            'post_status'   => $post_status,
            'attachment_id' => $attachment_id,
        ]);

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
