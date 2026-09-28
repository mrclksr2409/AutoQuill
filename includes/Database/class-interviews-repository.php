<?php
namespace AutoQuill\Database;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

/**
 * Interviews: a free topic plus the conversation between the AI editor and
 * the author, kept so an interview can be resumed and later traced from the
 * post it produced.
 *
 * Status: open (still talking) -> drafted (a post was generated at least
 * once) -> published (saved as a WordPress post). Answering again after
 * drafting is allowed and does not reset the status.
 */
class InterviewsRepository {
    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . C::TABLE_INTERVIEWS;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return int New row ID, 0 on failure.
     */
    public function create(string $topic, string $notes, array $messages, int $user_id): int {
        global $wpdb;
        $now = current_time('mysql');
        $ok  = $wpdb->insert(
            $this->table(),
            [
                'topic'      => $topic,
                'notes'      => $notes,
                'messages'   => wp_json_encode(array_values($messages)),
                'status'     => 'open',
                'user_id'    => $user_id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );
        if ($ok === false) {
            Logger::error('db.interviews', 'create fehlgeschlagen', [
                'wpdb_error' => $wpdb->last_error,
            ]);
            return 0;
        }
        return (int) $wpdb->insert_id;
    }

    public function find(int $id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1",
            $id
        ));
    }

    /**
     * Newest activity first. The messages column is included: the list shows
     * the answer count, and a page holds at most a few dozen rows.
     */
    public function list_recent(int $limit = 20, int $offset = 0): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d",
            max(1, min(100, $limit)),
            max(0, $offset)
        ));
        return $rows ?: [];
    }

    public function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table()}");
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    public function save_messages(int $id, array $messages): bool {
        return $this->update($id, ['messages' => wp_json_encode(array_values($messages))], ['%s'], 'save_messages');
    }

    /** Never downgrades a published interview back to drafted. */
    public function mark_drafted(int $id): bool {
        $row = $this->find($id);
        if (!$row || $row->status === 'published') {
            return true;
        }
        return $this->update($id, ['status' => 'drafted'], ['%s'], 'mark_drafted');
    }

    public function mark_published(int $id, int $post_id): bool {
        return $this->update($id, ['status' => 'published', 'post_id' => $post_id], ['%s', '%d'], 'mark_published');
    }

    public function delete(int $id): bool {
        global $wpdb;
        $ok = $wpdb->delete($this->table(), ['id' => $id], ['%d']);
        if ($ok === false) {
            Logger::error('db.interviews', 'delete fehlgeschlagen', [
                'wpdb_error' => $wpdb->last_error,
                'id'         => $id,
            ]);
            return false;
        }
        return true;
    }

    /**
     * Decoded conversation of a row; tolerant of a damaged column.
     *
     * @return array<int, array{role:string, text:string, at?:string, skipped?:bool, enough?:bool}>
     */
    public static function messages($row): array {
        if (!$row || !isset($row->messages)) {
            return [];
        }
        $decoded = json_decode((string) $row->messages, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $message) {
            if (!is_array($message) || !isset($message['role'], $message['text'])) {
                continue;
            }
            $role = $message['role'] === 'user' ? 'user' : 'ai';
            $item = [
                'role' => $role,
                'text' => (string) $message['text'],
                'at'   => (string) ($message['at'] ?? ''),
            ];
            if (!empty($message['skipped'])) {
                $item['skipped'] = true;
            }
            if ($role === 'ai' && !empty($message['enough'])) {
                $item['enough'] = true;
            }
            $out[] = $item;
        }
        return $out;
    }

    /** Answers given, skipped questions excluded. */
    public static function answer_count(array $messages): int {
        $count = 0;
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'user' && empty($message['skipped'])) {
                $count++;
            }
        }
        return $count;
    }

    private function update(int $id, array $data, array $formats, string $op): bool {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        $formats[]          = '%s';

        $ok = $wpdb->update($this->table(), $data, ['id' => $id], $formats, ['%d']);
        if ($ok === false) {
            Logger::error('db.interviews', $op . ' fehlgeschlagen', [
                'wpdb_error' => $wpdb->last_error,
                'id'         => $id,
            ]);
            return false;
        }
        return true;
    }
}
