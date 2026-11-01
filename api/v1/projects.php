<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Authorization');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error(405, 'Method not allowed');
}

// Authenticate user
$user_data = authenticate();

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($id) {
        // --- DETAIL MODE ---
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            api_error(404, 'Project not found');
        }

        // Fetch Transactions
        $stmtTrans = $pdo->prepare("SELECT * FROM transactions WHERE project_id = ? ORDER BY transaction_date DESC");
        $stmtTrans->execute([$id]);
        $transactions = $stmtTrans->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Documents
        $stmtDocs = $pdo->prepare("SELECT * FROM project_documents WHERE project_id = ?");
        $stmtDocs->execute([$id]);
        $documents = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch Fees
        $stmtFees = $pdo->prepare("SELECT * FROM project_fees WHERE project_id = ?");
        $stmtFees->execute([$id]);
        $fees = $stmtFees->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'project' => $project,
                'transactions' => $transactions,
                'documents' => $documents,
                'fees' => $fees
            ]
        ]);

    } else {
        // --- LIST MODE ---
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $offset = ($page - 1) * $limit;

        $conditions = [];
        $params = [];

        if ($q) {
            $conditions[] = "(client_name LIKE ? OR service_name LIKE ? OR status LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Count Total
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM projects $whereSql");
        $stmtCount->execute($params);
        $total = $stmtCount->fetchColumn();
        $totalPages = ceil($total / $limit);

        // Fetch Data
        $sql = "SELECT id, client_name, service_name, status, total_price, created_at FROM projects $whereSql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $projects,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $total,
                'limit' => $limit
            ]
        ]);
    }

} catch (Exception $e) {
    api_error(500, 'Database error: ' . $e->getMessage());
}
