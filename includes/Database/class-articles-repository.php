<?php
namespace AutoQuill\Database;

use AutoQuill\Core\Constants as C;

class ArticlesRepository {
    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . C::TABLE_ARTICLES;
    }

    public function exists_by_hash(string $hash): bool {
        global $wpdb;
        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table()} WHERE article_hash = %s LIMIT 1",
            $hash
        ));
        return !empty($row);
    }

    public function insert(array $data) {
        global $wpdb;
        $row = [
            'source_id'      => (int) ($data['source_id'] ?? 0),
            'title'          => (string) ($data['title'] ?? ''),
            'description'    => (string) ($data['description'] ?? ''),
            'content'        => (string) ($data['content'] ?? ''),
            'author'         => (string) ($data['author'] ?? ''),
            'published_date' => (string) ($data['published_date'] ?? current_time('mysql')),
            'article_url'    => (string) ($data['article_url'] ?? ''),
            'article_hash'   => (string) ($data['article_hash'] ?? ''),
        ];
        $ok = $wpdb->insert(
            $this->table(),
            $row,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        if ($ok === false) {
            error_log('AutoQuill ArticlesRepository::insert failed: ' . $wpdb->last_error);
            return false;
        }
        return (int) $wpdb->insert_id;
    }

    public function recent(int $hours = 24, int $limit = 50): array {
        global $wpdb;
        $time_ago = date('Y-m-d H:i:s', time() - ($hours * 3600));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE fetched_at >= %s ORDER BY published_date DESC LIMIT %d",
            $time_ago,
            $limit
        ));
        return $rows ?: [];
    }

    public function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table()}");
    }
}
