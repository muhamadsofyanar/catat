<?php
require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();
\Core\Env::load(__DIR__ . '/../.env');
header('Content-Type: application/json');
try {
    $pdo = new PDO('sqlite:' . \Core\Env::get('DB_DATABASE', __DIR__ . '/../config/database.sqlite'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->query('SELECT id FROM users LIMIT 1')->fetch();
    echo '{"status":"ok"}';
} catch (Throwable $e) {
    http_response_code(503);
    echo '{"status":"unavailable"}';
}
