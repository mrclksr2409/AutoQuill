<?php
namespace AutoQuill\Rest;

use AutoQuill\Core\Logger;

class LogsService {
    public static function list_logs(\WP_REST_Request $request): \WP_REST_Response {
        $logs = Logger::query([
            'level'    => (string) $request->get_param('level'),
            'source'   => (string) $request->get_param('source'),
            'since_id' => (int)    $request->get_param('since_id'),
            'since'    => (string) $request->get_param('since'),
            'limit'    => (int)    ($request->get_param('limit') ?: 100),
        ]);

        return new \WP_REST_Response([
            'logs'          => $logs,
            'server_time'   => current_time('mysql'),
            'debug_enabled' => Logger::is_debug_enabled(),
        ], 200);
    }

    public static function clear_logs(\WP_REST_Request $request): \WP_REST_Response {
        Logger::clear();
        return new \WP_REST_Response(['success' => true], 200);
    }
}
