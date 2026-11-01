<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'] ?? 0;
    $date = $_POST['date'] ?? date('Y-m-d');
    $note = $_POST['note'] ?? '';
    if ($amount > 0) {
        $stmt = $pdo->prepare("SELECT id FROM projects WHERE client_name = 'INTERNAL' LIMIT 1");
        $stmt->execute();
        $project = $stmt->fetch();
        if ($project) {
            $project_id = $project['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO projects (client_name, service_name, status) VALUES ('INTERNAL', 'KEUANGAN PERUSAHAAN', 'internal')");
            $stmt->execute();
            $project_id = $pdo->lastInsertId();
        }
        $usage_type = $_POST['usage_type'] ?? 'expense';
        $category = ($usage_type === 'loan') ? 'Company Withdrawal' : 'Company Expense';
        $stmt = $pdo->prepare("INSERT INTO transactions (project_id, type, category, amount, note, transaction_date, status) VALUES (?, 'expense', ?, ?, ?, ?, 'paid')");
        $stmt->execute([$project_id, $category, $amount, $note, $date]);
        logActivity($pdo, 'COMPANY_WITHDRAWAL', "Withdrew Company Funds ($category): " . formatRupiah($amount));
    }
    header("Location: index.php");
    exit;
} else {
    header("Location: index.php");
    exit;
}
