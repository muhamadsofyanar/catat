<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$id = $_GET['id'] ?? null;
$status = $_GET['status'] ?? null;
if (!$id || !$status) {
    header("Location: index.php");
    exit;
}
$status = ['completed' => 'done', 'cancel' => 'cancelled', 'active' => 'ongoing'][$status] ?? $status;
$allowed = ['ongoing', 'done', 'cancelled'];
if (!in_array($status, $allowed)) {
    die("Status tidak valid.");
}
$stmt = $pdo->prepare("SELECT client_name, service_name, status FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) {
    die("Proyek tidak ditemukan.");
}
$stmtU = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
$stmtU->execute([$status, $id]);
logActivity($pdo, 'UPDATE_PROJECT_STATUS', "Project #$id (" . $project['client_name'] . " - " . $project['service_name'] . ") status: " . $project['status'] . " -> " . $status);
header("Location: project_detail.php?id=" . $id);
exit;
