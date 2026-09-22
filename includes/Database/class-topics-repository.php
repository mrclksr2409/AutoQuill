<?php
namespace AutoQuill\Database;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

class TopicsRepository {
    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . C::TABLE_TOPICS;
    }

    public function find_by_date(string $date) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE topic_date = %s LIMIT 1",
            $date
        ));
    }

    public function find(int $id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1",
            $id
        ));
    }

    public function upsert_for_date(string $date, array $topics): bool {
        global $wpdb;
        $existing = $this->find_by_date($date);
        $now = current_time('mysql');
        if ($existing) {
            $ok = $wpdb->update(
                $this->table(),
                [
                    'topics'     => wp_json_encode($topics),
                    'status'     => 'pending',
                    'updated_at' => $now,
                ],
                ['topic_date' => $date],
                ['%s', '%s', '%s'],
                ['%s']
            );
        } else {
            $ok = $wpdb->insert(
                $this->table(),
                [
                    'topic_date' => $date,
                    'topics'     => wp_json_encode($topics),
                    'status'     => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }
        if ($ok === false) {
            Logger::error('db.topics', 'upsert_for_date fehlgeschlagen', [
                'wpdb_error' => $wpdb->last_error,
                'date'       => $date,
            ]);
            return false;
        }
        return true;
    }

    public function mark_generated(int $id, int $selected_topic_id, string $title): bool {
        global $wpdb;
        $ok = $wpdb->update(
            $this->table(),
            [
                'selected_topic_id'    => $selected_topic_id,
                'selected_topic_title' => $title,
                'status'               => 'generated',
                'updated_at'           => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s'],
            ['%d']
        );
        if ($ok === false) {
            Logger::error('db.topics', 'mark_generated fehlgeschlagen', [
                'wpdb_error' => $wpdb->last_error,
                'id'         => $id,
            ]);
            return false;
        }
        return true;
    }

    public function mark_published(int $id, int $post_id): bool {
        global $wpdb;
        $ok = $wpdb->update(
            $this->table(),
            [
                'post_id'    => $post_id,
                'status'     => 'published',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s', '%s'],
            ['%d']
        );
        if ($ok === false) {
            Logger::error('db.topics', 'mark_published fehlgeschlagen', [
                'wpdb_error' => $wpdb->last_error,
                'id'         => $id,
                'post_id'    => $post_id,
            ]);
            return false;
        }
        return true;
    }

    /**
     * Topic rows touched since a point in time.
     *
     * Filters on updated_at (DATETIME) rather than topic_date (a DATE, which
     * would silently truncate a timestamp comparison). updated_at is also what
     * actually moves on both insert and re-selection.
     *
     * @param string $local_datetime Local 'Y-m-d H:i:s' - the column is written
     *                               with current_time('mysql').
     */
    public function since(string $local_datetime, int $limit = 20): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()}
             WHERE updated_at >= %s
             ORDER BY topic_date DESC
             LIMIT %d",
            $local_datetime,
            max(1, min(100, $limit))
        ));
        return $rows ?: [];
    }

    public function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table()}");
    }
}
