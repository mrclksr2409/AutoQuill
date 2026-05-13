<?php
namespace AutoQuill;

class Database {
    private static $instance;

    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'auto_quill_' . $table;
    }

    public function table_exists($table) {
        global $wpdb;
        $table_name = $this->get_table_name($table);
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
    }

    public function insert($table, $data, $format = null) {
        global $wpdb;
        return $wpdb->insert($this->get_table_name($table), $data, $format);
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        global $wpdb;
        return $wpdb->update($this->get_table_name($table), $data, $where, $format, $where_format);
    }

    public function delete($table, $where, $where_format = null) {
        global $wpdb;
        return $wpdb->delete($this->get_table_name($table), $where, $where_format);
    }

    public function query($sql) {
        global $wpdb;
        return $wpdb->get_results($sql);
    }

    public function get_row($table, $where = [], $output = OBJECT) {
        global $wpdb;
        $table_name = $this->get_table_name($table);
        list($clause, $values) = self::build_where($where);
        $sql = "SELECT * FROM {$table_name}{$clause}";
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        return $wpdb->get_row($sql, $output);
    }

    public function get_results($table, $where = [], $orderby = '', $limit = '', $output = OBJECT) {
        global $wpdb;
        $table_name = $this->get_table_name($table);
        list($clause, $values) = self::build_where($where);
        $sql = "SELECT * FROM {$table_name}{$clause}";

        $orderby = preg_replace('/[^a-zA-Z0-9_,\s]/', '', (string) $orderby);
        if ($orderby !== '') {
            $sql .= ' ORDER BY ' . $orderby;
        }

        $limit = absint($limit);
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        return $wpdb->get_results($sql, $output);
    }

    private static function build_where(array $where) {
        if (empty($where)) {
            return ['', []];
        }
        $parts = [];
        $values = [];
        foreach ($where as $key => $value) {
            $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
            if ($column === '') {
                continue;
            }
            $parts[] = "`{$column}` = %s";
            $values[] = $value;
        }
        if (empty($parts)) {
            return ['', []];
        }
        return [' WHERE ' . implode(' AND ', $parts), $values];
    }
}
