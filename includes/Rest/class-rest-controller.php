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

        register_rest_route(self::NS, '/articles', [
            'methods'             => 'GET',
            'callback'            => ['\AutoQuill\Rest\ArticlesService', 'list_articles'],
            'permission_callback' => $can,
            'args'                => [
                'page' => [
                    'type'              => 'integer',
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'type'              => 'integer',
                    'default'           => 20,
                    'minimum'           => 1,
                    'maximum'           => 100,
                    'sanitize_callback' => 'absint',
                ],
                'source_id' => [
                    'type'              => 'integer',
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'linked' => [
                    'type'    => 'string',
                    'default' => 'all',
                    'enum'    => ['all', 'linked', 'unlinked'],
                ],
            ],
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

        register_rest_route(self::NS, '/models', [
            'methods'             => 'POST',
            'callback'            => ['\AutoQuill\Rest\ModelsService', 'list_models'],
            'permission_callback' => $can,
            'args'                => [
                'provider' => [
                    'type'     => 'string',
                    'required' => true,
                    'enum'     => \AutoQuill\Core\Constants::AI_PROVIDERS,
                ],
                'api_key' => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'base_url' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'refresh' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        $interview_id = [
            'id' => [
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ],
        ];

        register_rest_route(self::NS, '/interviews', [
            [
                'methods'             => 'GET',
                'callback'            => ['\AutoQuill\Rest\InterviewsService', 'list_interviews'],
                'permission_callback' => $can,
                'args'                => [
                    'page' => [
                        'type'              => 'integer',
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'per_page' => [
                        'type'              => 'integer',
                        'default'           => 20,
                        'minimum'           => 1,
                        'maximum'           => 100,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => ['\AutoQuill\Rest\InterviewsService', 'create_interview'],
                'permission_callback' => $can,
            ],
        ]);

        register_rest_route(self::NS, '/interviews/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => ['\AutoQuill\Rest\InterviewsService', 'get_interview'],
                'permission_callback' => $can,
                'args'                => $interview_id,
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => ['\AutoQuill\Rest\InterviewsService', 'delete_interview'],
                'permission_callback' => $can,
                'args'                => $interview_id,
            ],
        ]);

        foreach (['answer', 'question', 'write'] as $action) {
            register_rest_route(self::NS, '/interviews/(?P<id>\d+)/' . $action, [
                'methods'             => 'POST',
                'callback'            => ['\AutoQuill\Rest\InterviewsService', $action],
                'permission_callback' => $can,
                'args'                => $interview_id,
            ]);
        }

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
