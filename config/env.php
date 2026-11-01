<?php
require_once __DIR__ . '/../core/Env.php';
if (!function_exists('loadEnv')) {
    function loadEnv($path) { \Core\Env::load($path); }
}
loadEnv(__DIR__ . '/../.env');
if (!function_exists('env')) {
    function env($key, $default = null) { return \Core\Env::get($key, $default); }
}
