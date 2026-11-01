<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
$stmt = $pdo->prepare('SELECT d.* FROM project_documents d JOIN projects p ON p.id = d.project_id WHERE d.id = ? AND p.company_id = ?');
$stmt->execute([(int)($_GET['id'] ?? 0), currentCompanyId()]);
$doc = $stmt->fetch();
$root = realpath(__DIR__ . '/../uploads');
$file = $doc ? realpath(__DIR__ . '/../' . $doc['file_path']) : false;
if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404); exit('Dokumen tidak ditemukan.');
}
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode(basename($doc['file_name'])));
header('Content-Length: ' . filesize($file));
readfile($file);
