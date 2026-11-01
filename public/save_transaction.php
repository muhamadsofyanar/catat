<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
use Modules\Finance\Models\Transaction;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionModel = new Transaction();
    $project_id = $_POST['project_id'];
    $type = $_POST['type'];
    $category = $_POST['category'];
    $amount = str_replace('.', '', $_POST['amount']);
    $note = $_POST['note'];
    $date = $_POST['date'] ?: date('Y-m-d');
    $status = $_POST['status'] ?? 'paid';
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $data = [
        'project_id' => $project_id,
        'type' => $type,
        'category' => $category,
        'amount' => $amount,
        'note' => $note,
        'transaction_date' => $date,
        'status' => $status,
        'due_date' => $due_date
    ];
    $transactionModel->create($data);
    logActivity($pdo, 'ADD_TRANSACTION', "Added $type ($category): " . formatRupiah($amount) . " to Project ID: $project_id");
    if (isset($_SERVER['HTTP_REFERER'])) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
    } else {
        header("Location: project_detail.php?id=" . $project_id);
    }
    exit;
}
