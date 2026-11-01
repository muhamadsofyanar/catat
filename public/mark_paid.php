<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses Ditolak. Hanya Admin yang dapat menandai transaksi sebagai 'paid'.");
}
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("UPDATE transactions SET status = 'paid' WHERE id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("SELECT note, amount FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    $info = $t ? ($t['note'] . " (" . formatRupiah($t['amount']) . ")") : "ID: $id";
    logActivity($pdo, 'MARK_PAID', "Marked transaction as paid: $info");
    if (isset($_SERVER['HTTP_REFERER'])) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
    } else {
        header("Location: general_expenses.php");
    }
} else {
    header("Location: index.php");
}
