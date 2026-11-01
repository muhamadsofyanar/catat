<?php

namespace Core;

class Autoloader {
    public static function register() {
        spl_autoload_register(function ($class) {
            $prefix = '';
            $base_dir = '';

            if (strpos($class, 'Core\\') === 0) {
                $prefix = 'Core\\';
                $base_dir = __DIR__ . '/';
            } elseif (strpos($class, 'Modules\\') === 0) {
                $prefix = 'Modules\\';
                $base_dir = __DIR__ . '/../modules/';
            } else {
                return;
            }

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }
}
