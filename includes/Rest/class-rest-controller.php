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

        register_rest_route(self::NS, '/suggest-image-keywords', [
            'methods'             => 'POST',
            'callback'            => ['\AutoQuill\Rest\ImagesService', 'suggest_keywords'],
            'permission_callback' => $can,
        ]);

        register_rest_route(self::NS, '/search-images', [
            'methods'             => 'GET',
            'callback'            => ['\AutoQuill\Rest\ImagesService', 'search'],
            'permission_callback' => $can,
        ]);

        register_rest_route(self::NS, '/logs', [
            [
                'methods'             => 'GET',
                'callback'            => ['\AutoQuill\Rest\LogsService', 'list_logs'],
                'permission_callback' => $can,
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => ['\AutoQuill\Rest\LogsService', 'clear_logs'],
                'permission_callback' => $can,
            ],
        ]);
    }
}
