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
    $only_overdue = isset($_GET['only_overdue']) && $_GET['only_overdue'] === '1';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $limit = min($limit, 100);
    $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'due_date';
    $order = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';
    $aging = isset($_GET['aging']) && $_GET['aging'] === '1';

    $conditions = [];
    $params = [];

    $conditions[] = "p.client_name != 'INTERNAL'";

    if ($q !== '') {
        $conditions[] = "(p.client_name LIKE ? OR p.service_name LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($partner_id !== '') {
        $conditions[] = "p.partner_id = ?";
        $params[] = $partner_id;
    }

    $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    $sql = "
        SELECT p.*,
        (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income') AS paid,
        c.phone, c.email
        FROM projects p
        LEFT JOIN clients c ON c.id = p.client_id
        $whereSql
        ORDER BY COALESCE(p.due_date, '9999-12-31') ASC, p.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $projects = [];
    $total_receivable = 0;
    $total_items = 0;
    $aging_summary = [
        'current' => 0.0,   // belum jatuh tempo
        'no_due' => 0.0,    // tidak ada tanggal jatuh tempo
        'over_0_30' => 0.0, // lewat 1-30 hari
        'over_31_60' => 0.0,
        'over_61_90' => 0.0,
        'over_90_plus' => 0.0
    ];

    foreach ($rows as $p) {
        $paid = $p['paid'] ?? 0;
        $remaining = ($p['total_price'] - $paid);
        if ($remaining > 0) {
            $is_overdue = !empty($p['due_date']) && strtotime($p['due_date']) < strtotime(date('Y-m-d'));
            if ($only_overdue && !$is_overdue) {
                continue;
            }
            $total_receivable += $remaining;
            $projects[] = [
                'id' => (int)$p['id'],
                'client_name' => $p['client_name'],
                'service_name' => $p['service_name'],
                'total_price' => (float)$p['total_price'],
                'paid' => (float)$paid,
                'remaining' => (float)$remaining,
                'due_date' => $p['due_date'],
                'phone' => $p['phone'] ?? null,
                'email' => $p['email'] ?? null,
                'partner_id' => $p['partner_id'] ?? null
            ];
            $total_items++;
            
            if ($aging) {
                if (empty($p['due_date'])) {
                    $aging_summary['no_due'] += (float)$remaining;
                } else {
                    $days = (int)floor((time() - strtotime($p['due_date'])) / 86400);
                    if ($days <= 0) {
                        $aging_summary['current'] += (float)$remaining;
                    } elseif ($days <= 30) {
                        $aging_summary['over_0_30'] += (float)$remaining;
                    } elseif ($days <= 60) {
                        $aging_summary['over_31_60'] += (float)$remaining;
                    } elseif ($days <= 90) {
                        $aging_summary['over_61_90'] += (float)$remaining;
                    } else {
                        $aging_summary['over_90_plus'] += (float)$remaining;
                    }
                }
            }
        }
    }
    
    $validSort = ['due_date','created_at','remaining','paid','total_price','client_name','service_name'];
    if (!in_array($sort_by, $validSort)) {
        $sort_by = 'due_date';
    }
    $order = $order === 'desc' ? 'desc' : 'asc';
    usort($projects, function($a, $b) use ($sort_by, $order) {
        $va = $a[$sort_by] ?? null;
        $vb = $b[$sort_by] ?? null;
        if ($sort_by === 'due_date' || $sort_by === 'created_at') {
            $ta = $va ? strtotime($va) : PHP_INT_MAX;
            $tb = $vb ? strtotime($vb) : PHP_INT_MAX;
            $cmp = $ta <=> $tb;
        } else {
            $cmp = $va <=> $vb;
        }
        return $order === 'desc' ? -$cmp : $cmp;
    });
    $offset = ($page - 1) * $limit;
    $paged = array_slice($projects, $offset, $limit);

    echo json_encode([
        'status' => 'success',
        'data' => $paged,
        'summary' => [
            'count' => $total_items,
            'total_receivable' => $total_receivable
        ],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => (int)ceil($total_items / $limit),
            'total_items' => $total_items,
            'limit' => $limit
        ] + ($aging ? ['aging' => $aging_summary] : [])
    ]);

} catch (Exception $e) {
    api_error(500, 'Database error: ' . $e->getMessage());
}
