<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Akses ditolak.');
}
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
    http_response_code(400);
    exit('Gunakan backup native database untuk driver selain SQLite.');
}
$file = tempnam(__DIR__ . '/../tmp', 'lv-backup-');
if ($file === false) { http_response_code(503); exit('Backup sementara tidak dapat dibuat.'); }
unlink($file);
try {
    $pdo->exec('VACUUM INTO ' . $pdo->quote($file));
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="legavoice_' . date('Y-m-d_H-i-s') . '.sqlite"');
    header('Cache-Control: no-store');
    header('Content-Length: ' . filesize($file));
    readfile($file);
} finally {
    if (is_file($file)) unlink($file);
}
