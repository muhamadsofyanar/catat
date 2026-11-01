<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$project_id = $_POST['project_id'] ?? null;
if (!$project_id) {
    header("Location: index.php");
    exit;
}
$stmtProject = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND company_id = ?');
$stmtProject->execute([$project_id, currentCompanyId()]);
if (!$stmtProject->fetch()) { http_response_code(404); exit('Proyek tidak ditemukan.'); }
$category = $_POST['category'] ?? 'other';
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    header("Location: project_detail.php?id=" . $project_id);
    exit;
}
$uploadDir = __DIR__ . '/../uploads/projects/' . intval($project_id);
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$originalName = basename($_FILES['document']['name']);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'xls', 'xlsx', 'txt'], true) || $_FILES['document']['size'] > 20 * 1024 * 1024) {
    http_response_code(422); exit('Format dokumen tidak didukung atau ukuran melebihi 20 MB.');
}

$safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $originalName);
$targetPath = $uploadDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
if (!move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
    header("Location: project_detail.php?id=" . $project_id);
    exit;
}
$relativePath = 'uploads/projects/' . intval($project_id) . '/' . basename($targetPath);
$stmt = $pdo->prepare("INSERT INTO project_documents (project_id, file_name, file_path, category, uploaded_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
$stmt->execute([$project_id, $originalName, $relativePath, $category]);
logActivity($pdo, 'UPLOAD_DOCUMENT', "Upload doc for project $project_id: $originalName");
header("Location: project_detail.php?id=" . $project_id);
exit;
