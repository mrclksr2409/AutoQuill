<?php
namespace AutoQuill\Database;

use AutoQuill\Core\Constants as C;
use AutoQuill\Core\Logger;

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
            Logger::error('db.articles', 'Insert fehlgeschlagen', [
                'wpdb_error' => $wpdb->last_error,
                'source_id'  => $row['source_id'],
                'title'      => $row['title'],
            ]);
            return false;
        }
        return (int) $wpdb->insert_id;
    }

    public function find(int $id) {
        global $wpdb;
        if ($id <= 0) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1",
            $id
        ));
    }

    public function find_by_title(string $title) {
        global $wpdb;
        $title = trim($title);
        if ($title === '') {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE LOWER(title) = LOWER(%s) ORDER BY published_date DESC LIMIT 1",
            $title
        ));
    }

    public function recent(int $hours = 24, int $limit = 50): array {
        global $wpdb;
        $time_ago = gmdate('Y-m-d H:i:s', time() - ($hours * 3600));
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

    public function delete_older_than(int $days): int {
        if ($days <= 0) {
            return 0;
        }
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        // Articles that already produced a post are never pruned. Deleting one
        // frees its article_hash, so the next fetch re-inserts the same item as
        // a fresh, unlinked row and offers it for generation again - which costs
        // the user a second API call and yields a duplicate post.
        //
        // Undated feed items end up with an empty published_date, which would
        // otherwise be older than any cutoff and be deleted on the very next
        // run; fall back to fetched_at for those.
        $n = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table()}
             WHERE post_id IS NULL
               AND COALESCE(NULLIF(published_date, '0000-00-00 00:00:00'), fetched_at) < %s",
            $cutoff
        ));
        return (int) $n;
    }

    /**
     * Paginated listing for the dashboard feed tab.
     *
     * @param array{page?:int, per_page?:int, source_id?:int, search?:string, linked?:string} $args
     * @return array{items: array, total: int}
     */
    public function paginate(array $args = []): array {
        global $wpdb;

        $page     = max(1, (int) ($args['page'] ?? 1));
        $per_page = (int) ($args['per_page'] ?? 20);
        // Clamped server-side: 0 or a negative value means "use the default",
        // not "return a single row".
        $per_page = $per_page > 0 ? min(100, $per_page) : 20;
        $source_id = (int) ($args['source_id'] ?? 0);
        $search    = trim((string) ($args['search'] ?? ''));
        $linked    = (string) ($args['linked'] ?? 'all');

        $where  = [];
        $params = [];

        if ($source_id > 0) {
            $where[]  = 'a.source_id = %d';
            $params[] = $source_id;
        }
        if ($search !== '') {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = '(a.title LIKE %s OR a.description LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        if ($linked === 'linked') {
            $where[] = 'a.post_id IS NOT NULL';
        } elseif ($linked === 'unlinked') {
            $where[] = 'a.post_id IS NULL';
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $articles  = $this->table();
        $sources   = $wpdb->prefix . C::TABLE_SOURCES;

        // prepare() with an empty args array triggers _doing_it_wrong, and the
        // unfiltered count query has no placeholders at all.
        $count_sql = "SELECT COUNT(*) FROM {$articles} a {$where_sql}";
        $total     = (int) $wpdb->get_var(
            $params ? $wpdb->prepare($count_sql, $params) : $count_sql
        );

        // Explicit column list: `content` holds up to 50 KB per row and must
        // never end up in a 20-row listing response.
        $select_sql = "SELECT a.id, a.source_id, a.title, a.description, a.author,
                              a.published_date, a.article_url, a.post_id, a.fetched_at,
                              s.title AS source_title
                       FROM {$articles} a
                       LEFT JOIN {$sources} s ON s.id = a.source_id
                       {$where_sql}
                       ORDER BY a.published_date DESC, a.id DESC
                       LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results($wpdb->prepare(
            $select_sql,
            array_merge($params, [$per_page, ($page - 1) * $per_page])
        ));

        return [
            'items' => $rows ?: [],
            'total' => $total,
        ];
    }

    public function set_post_id(int $article_id, int $post_id): bool {
        if ($article_id <= 0 || $post_id <= 0) {
            return false;
        }
        global $wpdb;
        $ok = $wpdb->update(
            $this->table(),
            ['post_id' => $post_id],
            ['id' => $article_id],
            ['%d'],
            ['%d']
        );
        if ($ok === false) {
            Logger::error('db.articles', 'set_post_id fehlgeschlagen', [
                'wpdb_error' => $wpdb->last_error,
                'article_id' => $article_id,
                'post_id'    => $post_id,
            ]);
            return false;
        }
        return true;
    }

    public function clear_post_id(int $article_id): bool {
        if ($article_id <= 0) {
            return false;
        }
        global $wpdb;
        // "Unlinked" is consistently NULL, matching the column definition.
        $ok = $wpdb->update(
            $this->table(),
            ['post_id' => null],
            ['id' => $article_id],
            ['%d'],
            ['%d']
        );
        return $ok !== false;
    }

    public function find_by_post_id(int $post_id) {
        global $wpdb;
        if ($post_id <= 0) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE post_id = %d LIMIT 1",
            $post_id
        ));
    }
}
