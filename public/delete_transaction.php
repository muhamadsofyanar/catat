<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
if ($_SESSION['role'] !== 'admin') {
    die("Akses Ditolak. Hanya Admin yang dapat menghapus transaksi.");
}
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT project_id, amount, category, type FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
    $trans = $stmt->fetch();
    if ($trans) {
        logActivity($pdo, 'DELETE_TRANSACTION', "Deleted " . $trans['type'] . " (" . $trans['category'] . "): " . formatRupiah($trans['amount']));
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        if (isset($_SERVER['HTTP_REFERER'])) {
            header("Location: " . $_SERVER['HTTP_REFERER']);
        } elseif ($trans['project_id']) {
            header("Location: project_detail.php?id=" . $trans['project_id']);
        } else {
            header("Location: index.php");
        }
    } else {
        header("Location: index.php");
    }
    exit;
}
header("Location: index.php");
