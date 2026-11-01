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
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $partner_id = isset($_GET['partner_id']) ? $_GET['partner_id'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $limit = min($limit, 100);
    $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'transaction_date';
    $order = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';

    $partner_id = is_numeric($partner_id) ? (int)$partner_id : '';
    $validateDate = function($d) {
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        return $dt && $dt->format('Y-m-d') === $d;
    };
    if (($start_date !== '' || $end_date !== '') && !($start_date !== '' && $end_date !== '')) {
        api_error(400, 'start_date dan end_date harus keduanya diisi');
    }
    if ($start_date !== '' && !$validateDate($start_date)) {
        api_error(400, 'Format start_date tidak valid (YYYY-MM-DD)');
    }
    if ($end_date !== '' && !$validateDate($end_date)) {
        api_error(400, 'Format end_date tidak valid (YYYY-MM-DD)');
    }

    $conditions = [];
    $params = [];

    $conditions[] = "t.type = 'expense'";
    $conditions[] = "t.status = 'unpaid'";

    if ($q !== '') {
        $conditions[] = "(t.note LIKE ? OR t.category LIKE ? OR p.client_name LIKE ? OR p.service_name LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    if ($partner_id !== '' && $partner_id !== null) {
        $conditions[] = "p.partner_id = ?";
        $params[] = $partner_id;
    }

    if ($start_date !== '' && $end_date !== '') {
        $conditions[] = "t.transaction_date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }

    $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    $sql = "
        SELECT t.*, 
               p.client_name, 
               p.service_name,
               p.partner_id,
               part.name as partner_name
        FROM transactions t 
        LEFT JOIN projects p ON t.project_id = p.id 
        LEFT JOIN partners part ON p.partner_id = part.id
        $whereSql
        ORDER BY t.transaction_date ASC, t.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $total_unpaid = 0;
    $total_items = 0;

    foreach ($rows as $r) {
        $total_unpaid += (float)$r['amount'];
        $items[] = [
            'id' => (int)$r['id'],
            'transaction_date' => $r['transaction_date'],
            'category' => $r['category'] ?? null,
            'note' => $r['note'] ?? null,
            'amount' => (float)$r['amount'],
            'status' => $r['status'] ?? null,
            'client_name' => $r['client_name'] ?? null,
            'service_name' => $r['service_name'] ?? null,
            'partner_name' => $r['partner_name'] ?? null,
            'partner_id' => $r['partner_id'] ?? null,
            'project_id' => $r['project_id'] ?? null
        ];
        $total_items++;
    }
    
    $validSort = ['transaction_date','amount','category','client_name','service_name','partner_name'];
    if (!in_array($sort_by, $validSort)) {
        $sort_by = 'transaction_date';
    }
    $order = $order === 'desc' ? 'desc' : 'asc';
    usort($items, function($a, $b) use ($sort_by, $order) {
        $va = $a[$sort_by] ?? null;
        $vb = $b[$sort_by] ?? null;
        if ($sort_by === 'transaction_date') {
            $ta = $va ? strtotime($va) : PHP_INT_MAX;
            $tb = $vb ? strtotime($vb) : PHP_INT_MAX;
            $cmp = $ta <=> $tb;
        } else {
            $cmp = $va <=> $vb;
        }
        return $order === 'desc' ? -$cmp : $cmp;
    });
    $offset = ($page - 1) * $limit;
    $paged = array_slice($items, $offset, $limit);

    echo json_encode([
        'status' => 'success',
        'data' => $paged,
        'summary' => [
            'count' => $total_items,
            'total_unpaid' => $total_unpaid
        ],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => (int)ceil($total_items / $limit),
            'total_items' => $total_items,
            'limit' => $limit
        ]
    ]);

} catch (Exception $e) {
    api_error(500, 'Database error: ' . $e->getMessage());
}
