<?php
namespace AutoQuill;

class Autoloader {
    public static function register() {
        spl_autoload_register([self::class, 'autoload']);
    }

    public static function autoload($class) {
        if (strpos($class, 'AutoQuill') !== 0) {
            return;
        }

        $relative   = str_replace('AutoQuill\\', '', $class);
        $parts      = explode('\\', $relative);
        $class_name = strtolower(array_pop($parts));
        $sub_dir    = $parts ? implode('/', $parts) . '/' : '';
        $file       = AUTO_QUILL_INC_DIR . $sub_dir . 'class-' . $class_name . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}

Autoloader::register();
