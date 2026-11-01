<?php
require_once __DIR__ . '/../core/Env.php';
\Core\Env::load(__DIR__ . '/../.env');
if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = __DIR__ . '/../tmp';
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0755, true);
    }
    if (is_dir($sessionDir)) {
        session_save_path($sessionDir);
    }
    $secure = getenv('SESSION_SECURE_COOKIE') === 'true' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (function_exists('session_set_cookie_params')) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    @ini_set('session.use_strict_mode', '1');
    if (session_name() === 'PHPSESSID') {
        session_name('LVSESSID');
    }
    session_start();
    if (!isset($_SESSION['regenerated_at'])) {
        $_SESSION['regenerated_at'] = time();
    } elseif (time() - $_SESSION['regenerated_at'] > 900) {
        session_regenerate_id(true);
        $_SESSION['regenerated_at'] = time();
    }
}

