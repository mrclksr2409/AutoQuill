<?php
namespace AutoQuill\Core;

class Logger {
    const LEVEL_ERROR   = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO    = 'info';
    const LEVEL_DEBUG   = 'debug';

    const MAX_ENTRIES = 500;
    const MAX_AGE_DAYS = 7;
    const MAX_STRING_LEN = 2000;

    private static $redact_keys = [
        'api_key', 'apikey', 'authorization', 'x-api-key', 'password', 'secret', 'token', 'bearer',
    ];

    public static function error(string $source, string $message, array $context = []): void {
        self::write(self::LEVEL_ERROR, $source, $message, $context);
    }

    public static function warning(string $source, string $message, array $context = []): void {
        self::write(self::LEVEL_WARNING, $source, $message, $context);
    }

    public static function info(string $source, string $message, array $context = []): void {
        if (!self::is_debug_enabled()) {
            return;
        }
        self::write(self::LEVEL_INFO, $source, $message, $context);
    }

    public static function debug(string $source, string $message, array $context = []): void {
        if (!self::is_debug_enabled()) {
            return;
        }
        self::write(self::LEVEL_DEBUG, $source, $message, $context);
    }

    public static function is_debug_enabled(): bool {
        $settings = get_option(Constants::OPTION_KEY, Constants::defaults());
        if (!is_array($settings)) {
            return false;
        }
        return !empty($settings['debug_logging']);
    }

    private static function write(string $level, string $source, string $message, array $context): void {
        global $wpdb;

        $table = $wpdb->prefix . Constants::TABLE_LOGS;

        $redacted = self::redact($context);
        $json     = wp_json_encode($redacted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            $json = '{}';
        }

        $row = [
            'created_at' => current_time('mysql'),
            'level'      => substr($level, 0, 10),
            'source'     => substr($source, 0, 40),
            'message'    => self::truncate_string($message),
            'context'    => self::truncate_string($json, 16000),
        ];

        $ok = @$wpdb->insert($table, $row, ['%s', '%s', '%s', '%s', '%s']);

        if ($ok === false && ($level === self::LEVEL_ERROR || $level === self::LEVEL_WARNING)) {
            error_log('AutoQuill[' . $level . '][' . $source . '] ' . $message . ' ctx=' . $json);
            return;
        }

        if ($level === self::LEVEL_ERROR || $level === self::LEVEL_WARNING) {
            error_log('AutoQuill[' . $level . '][' . $source . '] ' . $message);
        }

        if (mt_rand(1, 100) === 1) {
            self::cleanup();
        }
    }

    public static function cleanup(): int {
        global $wpdb;
        $table  = $wpdb->prefix . Constants::TABLE_LOGS;
        $cutoff = gmdate('Y-m-d H:i:s', time() - (self::MAX_AGE_DAYS * DAY_IN_SECONDS));

        $deleted = (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            $cutoff
        ));

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > self::MAX_ENTRIES) {
            $extra = $count - self::MAX_ENTRIES;
            $deleted += (int) $wpdb->query($wpdb->prepare(
                "DELETE FROM $table ORDER BY id ASC LIMIT %d",
                $extra
            ));
        }
        return $deleted;
    }

    /**
     * @param array{level?:string, source?:string, since_id?:int, since?:string, limit?:int} $filters
     */
    public static function query(array $filters = []): array {
        global $wpdb;
        $table = $wpdb->prefix . Constants::TABLE_LOGS;

        $where = [];
        $args  = [];

        if (!empty($filters['level']) && in_array($filters['level'], [self::LEVEL_ERROR, self::LEVEL_WARNING, self::LEVEL_INFO, self::LEVEL_DEBUG], true)) {
            $where[] = 'level = %s';
            $args[]  = $filters['level'];
        }
        if (!empty($filters['source'])) {
            $where[] = 'source = %s';
            $args[]  = substr((string) $filters['source'], 0, 40);
        }
        if (!empty($filters['since_id'])) {
            $where[] = 'id > %d';
            $args[]  = (int) $filters['since_id'];
        }
        if (!empty($filters['since'])) {
            $where[] = 'created_at >= %s';
            $args[]  = (string) $filters['since'];
        }

        $limit = isset($filters['limit']) ? max(1, min(500, (int) $filters['limit'])) : 100;

        $sql = "SELECT id, created_at, level, source, message, context FROM $table";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id ASC LIMIT %d';
        $args[] = $limit;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
        foreach ($rows as &$r) {
            $r['id']      = (int) $r['id'];
            $decoded      = json_decode((string) $r['context'], true);
            $r['context'] = is_array($decoded) ? $decoded : [];
        }
        return $rows;
    }

    public static function clear(): int {
        global $wpdb;
        $table = $wpdb->prefix . Constants::TABLE_LOGS;
        return (int) $wpdb->query("TRUNCATE TABLE $table");
    }

    public static function known_sources(): array {
        global $wpdb;
        $table = $wpdb->prefix . Constants::TABLE_LOGS;
        $rows  = $wpdb->get_col("SELECT DISTINCT source FROM $table ORDER BY source ASC");
        return $rows ?: [];
    }

    private static function redact($value) {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (is_string($k) && self::is_secret_key($k)) {
                    $out[$k] = '***redacted***';
                    continue;
                }
                $out[$k] = self::redact($v);
            }
            return $out;
        }
        if (is_string($value)) {
            return self::truncate_string($value);
        }
        return $value;
    }

    private static function is_secret_key(string $key): bool {
        $lower = strtolower($key);
        foreach (self::$redact_keys as $needle) {
            if (strpos($lower, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function truncate_string(string $s, int $max = self::MAX_STRING_LEN): string {
        $len = function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
        if ($len <= $max) {
            return $s;
        }
        $cut = function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
        return $cut . '…[truncated ' . ($len - $max) . ' chars]';
    }
}
