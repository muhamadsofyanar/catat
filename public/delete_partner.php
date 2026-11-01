<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $pdo->prepare("SELECT is_default FROM partners WHERE id = ?");
    $stmt->execute([$id]);
    $partner = $stmt->fetch();
    if ($partner && !$partner['is_default']) {
        $stmtLogo = $pdo->prepare("SELECT logo, name FROM partners WHERE id = ?");
        $stmtLogo->execute([$id]);
        $p = $stmtLogo->fetch();
        if ($p && $p['logo'] && $p['logo'] !== 'logo.png' && file_exists(__DIR__ . '/' . $p['logo'])) {
            unlink(__DIR__ . '/' . $p['logo']);
        }
        $stmtDel = $pdo->prepare("DELETE FROM partners WHERE id = ?");
        $stmtDel->execute([$id]);
        logActivity($pdo, 'DELETE_PARTNER', "Deleted Partner: " . ($p['name'] ?? "ID $id"));
    }
}
header("Location: partners.php");
exit;
