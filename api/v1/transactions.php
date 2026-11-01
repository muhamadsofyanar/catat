<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/auth_middleware.php';

$user_data = authenticate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create Transaction
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($input['project_id'], $input['type'], $input['amount'])) {
        api_error(400, 'Missing required fields: project_id, type, amount');
    }

    $project_id = $input['project_id'];
    $type = $input['type']; // income, expense
    $category = $input['category'] ?? 'Lainnya';
    $amount = $input['amount'];
    $note = $input['note'] ?? '';
    $transaction_date = $input['transaction_date'] ?? date('Y-m-d');

    try {
        $stmt = $pdo->prepare("INSERT INTO transactions (project_id, type, category, amount, note, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$project_id, $type, $category, $amount, $note, $transaction_date]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Transaction created successfully',
            'data' => [
                'id' => $pdo->lastInsertId(),
                'project_id' => $project_id,
                'amount' => $amount
            ]
        ]);
    } catch (Exception $e) {
        api_error(500, 'Database error: ' . $e->getMessage());
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = max(1, min(100, $limit));

    try {
        if ($project_id) {
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE project_id = ? ORDER BY transaction_date DESC LIMIT ?");
            $stmt->execute([$project_id, $limit]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM transactions ORDER BY transaction_date DESC LIMIT ?");
            $stmt->execute([$limit]);
        }
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $transactions
        ]);
    } catch (Exception $e) {
        api_error(500, 'Database error: ' . $e->getMessage());
    }

} else {
    api_error(405, 'Method not allowed');
}
