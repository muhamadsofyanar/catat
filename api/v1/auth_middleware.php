<?php
// api/v1/auth_middleware.php
require_once __DIR__ . '/../JWT.php';

function authenticate() {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        // Polyfill for Nginx/CLI
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        // Handle Authorization specifically as it might not be in HTTP_ prefix in some setups or standard
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['Authorization'])) {
             $headers['Authorization'] = $_SERVER['Authorization'];
        }
    }
    
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authorization header missing']);
        exit;
    }

    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $jwt = $matches[1];
        $payload = JWT::decode($jwt);

        if ($payload) {
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Token expired']);
                exit;
            }
            return $payload['data'];
        }
    }

    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
    exit;
}

function api_error($httpCode, $message) {
    http_response_code($httpCode);
    echo json_encode(['status' => 'error', 'code' => $httpCode, 'message' => $message]);
    exit;
}
