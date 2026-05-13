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

        $path = str_replace('AutoQuill\\', '', $class);
        $path = str_replace('\\', '/', $path);
        $file = AUTO_QUILL_INC_DIR . 'class-' . strtolower(str_replace('/', '-', $path)) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}

Autoloader::register();
