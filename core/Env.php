<?php
namespace Core;

class Env {
    public static function load($path) {
        if (!is_file($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) continue;
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            if (getenv($name) === false && !array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }
    }

    public static function get($key, $default = null) {
        $value = getenv($key);
        if ($value !== false) return $value;
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}
