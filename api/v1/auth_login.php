<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username dan password wajib diisi']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Username atau password salah']);
        exit;
    }

    $payload = [
        'iss' => 'legavoice',
        'sub' => (string)$user['id'],
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60),
        'data' => [
            'user_id' => (int)$user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]
    ];

    $token = JWT::encode($payload);

    echo json_encode([
        'status' => 'success',
        'message' => 'Login berhasil',
        'data' => [
            'token' => $token,
            'expires_in' => 24 * 60 * 60,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
