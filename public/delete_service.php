<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$id = $_GET['id'] ?? null;
if ($id) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE service_id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        echo "<script>alert('Gagal hapus: Layanan ini sedang digunakan oleh proyek aktif.'); window.location='services.php';</script>";
        exit;
    }
    $stmt = $pdo->prepare("SELECT name FROM services WHERE id = ?");
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();
    if ($name) {
        $pdo->prepare("DELETE FROM service_costs WHERE service_id = ?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($pdo, 'DELETE_SERVICE', "Deleted Service: $name");
    }
}
header("Location: services.php");
exit;
