<?php
namespace AutoQuill;

class Autoloader {
    public static function register() {
        spl_autoload_register([self::class, 'autoload']);
    }

    public static function autoload($class) {
        if (strpos($class, 'AutoQuill\\') !== 0) {
            return;
        }

        $relative = substr($class, strlen('AutoQuill\\'));
        $parts    = explode('\\', $relative);
        $leaf     = array_pop($parts);

        // CamelCase -> kebab-case (AdminPage -> admin-page, SourcesRepository -> sources-repository)
        $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $leaf));

        $sub_dir = $parts ? implode('/', $parts) . '/' : '';
        $file    = AUTO_QUILL_INC_DIR . $sub_dir . 'class-' . $kebab . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
}

Autoloader::register();
