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
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
    $validateDate = function($d) {
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        return $dt && $dt->format('Y-m-d') === $d;
    };
    if (!$validateDate($start_date) || !$validateDate($end_date)) {
        api_error(400, 'Format tanggal tidak valid (YYYY-MM-DD)');
    }

    $stmtInc = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND (category IS NULL OR category NOT IN ('Pengembalian Kasbon','Pengembalian Dana Perusahaan')) AND transaction_date BETWEEN ? AND ?");
    $stmtInc->execute([$start_date, $end_date]);
    $income = $stmtInc->fetchColumn() ?: 0;

    $stmtExp = $pdo->prepare("
        SELECT SUM(amount) 
        FROM transactions 
        WHERE type = 'expense' 
          AND (category NOT IN ('Prive','Company Withdrawal','Company Expense','Kasbon','Pengembalian Kasbon','Pengembalian Dana Perusahaan') OR category IS NULL)
          AND transaction_date BETWEEN ? AND ?
    ");
    $stmtExp->execute([$start_date, $end_date]);
    $expense = $stmtExp->fetchColumn() ?: 0;

    $net = $income - $expense;

    $stmtTop = $pdo->prepare("
        SELECT COALESCE(category,'Lainnya') as category, SUM(amount) as total
        FROM transactions
        WHERE type = 'expense'
          AND (category NOT IN ('Prive','Company Withdrawal','Company Expense','Kasbon','Pengembalian Kasbon','Pengembalian Dana Perusahaan') OR category IS NULL)
          AND transaction_date BETWEEN ? AND ?
        GROUP BY COALESCE(category,'Lainnya')
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmtTop->execute([$start_date, $end_date]);
    $top_expenses = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    $total_income_all = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income'")->fetchColumn() ?: 0;
    $total_expense_paid_all = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND (status = 'paid' OR status IS NULL)")->fetchColumn() ?: 0;
    $cash_balance = $total_income_all - $total_expense_paid_all;

    $stmtReceivable = $pdo->query("
        SELECT SUM(p.total_price - COALESCE(paid_sum.paid, 0)) 
        FROM projects p
        LEFT JOIN (
            SELECT project_id, SUM(amount) as paid
            FROM transactions
            WHERE type = 'income'
            GROUP BY project_id
        ) paid_sum ON p.id = paid_sum.project_id
        WHERE p.client_name != 'INTERNAL' 
        AND (p.total_price - COALESCE(paid_sum.paid, 0)) > 0
    ");
    $total_receivable = $stmtReceivable->fetchColumn() ?: 0;

    $total_payable = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND status = 'unpaid'")->fetchColumn() ?: 0;

    $projects_active = $pdo->query("SELECT COUNT(*) FROM projects WHERE status != 'done' AND client_name != 'INTERNAL'")->fetchColumn() ?: 0;
    $projects_done = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'done'")->fetchColumn() ?: 0;

    echo json_encode([
        'status' => 'success',
        'data' => [
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'income' => (float)$income,
                'expense' => (float)$expense,
                'net' => (float)$net,
                'top_expenses' => $top_expenses
            ],
            'totals' => [
                'cash_balance' => (float)$cash_balance,
                'receivables_total' => (float)$total_receivable,
                'payables_total' => (float)$total_payable
            ],
            'counts' => [
                'projects_active' => (int)$projects_active,
                'projects_done' => (int)$projects_done
            ]
        ]
    ]);
} catch (Exception $e) {
    api_error(500, 'Database error: ' . $e->getMessage());
}
