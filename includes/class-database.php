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
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
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
        $sql = "SELECT * FROM $table_name";

        if (!empty($where)) {
            $where_clause = [];
            foreach ($where as $key => $value) {
                $where_clause[] = "$key = " . $wpdb->prepare('%s', $value);
            }
            $sql .= ' WHERE ' . implode(' AND ', $where_clause);
        }

        return $wpdb->get_row($sql, $output);
    }

    public function get_results($table, $where = [], $orderby = '', $limit = '', $output = OBJECT) {
        global $wpdb;
        $table_name = $this->get_table_name($table);
        $sql = "SELECT * FROM $table_name";

        if (!empty($where)) {
            $where_clause = [];
            foreach ($where as $key => $value) {
                $where_clause[] = "$key = " . $wpdb->prepare('%s', $value);
            }
            $sql .= ' WHERE ' . implode(' AND ', $where_clause);
        }

        if (!empty($orderby)) {
            $sql .= " ORDER BY $orderby";
        }

        if (!empty($limit)) {
            $sql .= " LIMIT $limit";
        }

        return $wpdb->get_results($sql, $output);
    }
}
