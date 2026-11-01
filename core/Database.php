<?php
namespace Core;

use PDO;
use PDOException;

class Database {
    private static $pdo = null;

    public static function connect() {
        if (self::$pdo !== null) return self::$pdo;
        $driver = Env::get('DB_CONNECTION', 'sqlite');
        try {
            if ($driver === 'sqlite') {
                $dbFile = Env::get('DB_DATABASE', __DIR__ . '/../config/database.sqlite');
                if (!is_dir(dirname($dbFile)) && !mkdir(dirname($dbFile), 0750, true) && !is_dir(dirname($dbFile))) {
                    throw new \RuntimeException('Cannot create database directory.');
                }
                self::$pdo = new PDO('sqlite:' . $dbFile);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec('PRAGMA busy_timeout = 5000');
            } elseif ($driver === 'mysql') {
                // Legacy driver; the supplied container intentionally supports SQLite only.
                $host = Env::get('DB_HOST', '127.0.0.1');
                $port = Env::get('DB_PORT', '3306');
                $db = Env::get('DB_DATABASE', 'legavoice');
                self::$pdo = new PDO(
                    "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
                    Env::get('DB_USERNAME', 'root'),
                    Env::get('DB_PASSWORD', ''),
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                     PDO::ATTR_EMULATE_PREPARES => false]
                );
            } else {
                throw new \RuntimeException('Unsupported database driver.');
            }
            // Schema checks must also run for databases created by earlier versions.
            $pdo = self::$pdo;
            require_once __DIR__ . '/../config/schema.php';
        } catch (PDOException $e) {
            error_log('Database initialization failed: ' . $e->getMessage());
            if (PHP_SAPI === 'cli') exit(1);
            http_response_code(503);
            exit('Database unavailable. Check server logs.');
        }
        return self::$pdo;
    }
}
