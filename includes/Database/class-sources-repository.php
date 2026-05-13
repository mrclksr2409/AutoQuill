<?php
namespace AutoQuill\Database;

use AutoQuill\Core\Constants as C;

class SourcesRepository {
    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . C::TABLE_SOURCES;
    }

    public function insert(string $title, string $feed_url, bool $is_active = true) {
        global $wpdb;
        $ok = $wpdb->insert(
            $this->table(),
            [
                'title'     => $title,
                'feed_url'  => $feed_url,
                'is_active' => $is_active ? 1 : 0,
            ],
            ['%s', '%s', '%d']
        );
        if ($ok === false) {
            error_log('AutoQuill SourcesRepository::insert failed: ' . $wpdb->last_error);
            return false;
        }
        return (int) $wpdb->insert_id;
    }

    public function delete(int $id): bool {
        global $wpdb;
        $ok = $wpdb->delete($this->table(), ['id' => $id], ['%d']);
        if ($ok === false) {
            error_log('AutoQuill SourcesRepository::delete failed: ' . $wpdb->last_error);
            return false;
        }
        return $ok > 0;
    }

    public function all(): array {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table()} ORDER BY id ASC");
        return $rows ?: [];
    }

    public function find(int $id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1",
            $id
        ));
        return $row ?: null;
    }

    public function active(): array {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table()} WHERE is_active = 1 ORDER BY id ASC");
        return $rows ?: [];
    }

    public function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table()}");
    }

    public function last_error(): string {
        global $wpdb;
        return (string) $wpdb->last_error;
    }
}
