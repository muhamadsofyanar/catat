<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
if ($_SESSION['role'] !== 'admin') { http_response_code(403); exit('Akses ditolak.'); }
try {
    (new \Modules\Finance\Models\OwnerFunds())->save('prive', $_POST['amount'] ?? '', $_POST['date'] ?? date('Y-m-d'), $_POST['note'] ?? '');
    header('Location: index.php');
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
