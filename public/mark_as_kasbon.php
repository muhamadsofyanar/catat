<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') {
    die("Akses Ditolak. Hanya Admin yang dapat menandai transaksi sebagai kasbon.");
}
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: payables.php");
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$id]);
$transaction = $stmt->fetch();
if (!$transaction) {
    die("Transaksi tidak ditemukan.");
}
$current_note = $transaction['note'];
$new_note = $current_note . " [UANG TERPAKAI]";
$stmtUpdate = $pdo->prepare("UPDATE transactions SET note = ? WHERE id = ?");
$stmtUpdate->execute([$new_note, $id]);
if (isset($_SERVER['HTTP_REFERER'])) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
} else {
    header("Location: payables.php?msg=marked_kasbon");
}
exit;
