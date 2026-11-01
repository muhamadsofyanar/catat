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

$user_data = authenticate();

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($id) {
        // Detail
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            api_error(404, 'Client not found');
        }

        // Get Client's Projects
        $stmtProjects = $pdo->prepare("SELECT id, service_name, status, total_price, created_at FROM projects WHERE client_name = ? ORDER BY created_at DESC");
        $stmtProjects->execute([$client['name']]); // Note: Using name link as per current schema
        $projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'client' => $client,
                'projects' => $projects
            ]
        ]);

    } else {
        // List
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $offset = ($page - 1) * $limit;

        $conditions = [];
        $params = [];

        if ($q) {
            $conditions[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM clients $whereSql");
        $stmtCount->execute($params);
        $total = $stmtCount->fetchColumn();
        $totalPages = ceil($total / $limit);

        $sql = "SELECT * FROM clients $whereSql ORDER BY name ASC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $clients,
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
