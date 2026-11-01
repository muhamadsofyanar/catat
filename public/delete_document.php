<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$id = $_GET['id'] ?? null;
$project_id = $_GET['project_id'] ?? null;
if ($id && $project_id) {
    $stmt = $pdo->prepare("SELECT file_path FROM project_documents WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if ($doc) {
        $fullPath = __DIR__ . '/../' . $doc['file_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        $stmt = $pdo->prepare("DELETE FROM project_documents WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($pdo, 'DELETE_DOCUMENT', "Deleted Document: " . basename($doc['file_path']) . " from Project ID: $project_id");
    }
}
if ($project_id) {
    header("Location: project_detail.php?id=" . $project_id);
} else {
    header("Location: index.php");
}
exit;
