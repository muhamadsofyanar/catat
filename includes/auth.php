<?php
// Ensure secure session is active
require_once __DIR__ . '/../config/session.php';

// Session timeout (idle)
$IDLE_TIMEOUT = 45 * 60; // 45 minutes
$now = time();
if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > $IDLE_TIMEOUT)) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    $loginPath = (basename(dirname($_SERVER['PHP_SELF'])) === 'public') ? '../login.php?timed_out=1' : 'login.php?timed_out=1';
    header("Location: " . $loginPath);
    exit;
}
$_SESSION['last_activity'] = $now;

// Require login
if (!isset($_SESSION['user_id'])) {
    $loginPath = (basename(dirname($_SERVER['PHP_SELF'])) === 'public') ? '../login.php' : 'login.php';
    header("Location: " . $loginPath);
    exit;
}
