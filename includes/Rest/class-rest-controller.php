<?php
namespace AutoQuill\Rest;

class RestController {
    const NS = 'auto-quill/v1';

    public static function boot(): void {
        add_action('rest_api_init', [self::class, 'register']);
    }

    public static function register(): void {
        $can = function () {
            return current_user_can('manage_options');
        };

        register_rest_route(self::NS, '/topics', [
            'methods'             => 'GET',
            'callback'            => ['\AutoQuill\Rest\PostsService', 'get_today_topics'],
            'permission_callback' => $can,
        ]);

        register_rest_route(self::NS, '/generate-post', [
            'methods'             => 'POST',
            'callback'            => ['\AutoQuill\AI\Writer', 'generate_post'],
            'permission_callback' => $can,
        ]);

        register_rest_route(self::NS, '/publish-post', [
            'methods'             => 'POST',
            'callback'            => ['\AutoQuill\Rest\PostsService', 'publish_post'],
            'permission_callback' => $can,
        ]);
    }
}
