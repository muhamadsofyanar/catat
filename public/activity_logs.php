<?php
$root = '../';
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$init = isset($_GET['init']) ? ($_GET['init'] === '1') : false;
$initMsg = '';
if ($isAdmin && $init) {
    try {
        logActivity($pdo, 'INIT_LOGS', 'Inisialisasi log oleh admin');
        $initMsg = 'Log berhasil diinisialisasi, data contoh telah ditambahkan.';
    } catch (Exception $e) {
        $initMsg = 'Gagal inisialisasi log: ' . $e->getMessage();
    }
}
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$action_filter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';
$conditions = [];
$params = [];
if ($q) {
    $conditions[] = "(description LIKE ? OR username LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($action_filter) {
    $conditions[] = "action = ?";
    $params[] = $action_filter;
}
$whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
$orderCol = 'created_at';
$errorMsg = '';
$logs = [];
$actions = [];
try {
    $pdo->query("SELECT created_at FROM activity_logs LIMIT 1");
} catch (Exception $e) {
    $orderCol = 'id';
}
try {
    $sql = "SELECT * FROM activity_logs $whereSql ORDER BY $orderCol DESC";
    if (empty($conditions)) {
        $sql .= " LIMIT 100";
    } else {
        $sql .= " LIMIT 500";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    $actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $errorMsg = "Gagal memuat log: " . $e->getMessage();
}
$pageTitle = "Log Aktivitas";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">Log Aktivitas</h3>
        <p class="text-muted mb-0 small">Riwayat aktivitas pengguna</p>
    </div>
    <?php if($isAdmin): ?>
    <a href="export_data.php?type=activity_logs&q=<?= urlencode($q) ?>&action_filter=<?= urlencode($action_filter) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Export CSV</a>
    <?php endif; ?>
    </div>
<?php if(!empty($errorMsg)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>
<?php if(!empty($initMsg)): ?>
<div class="alert alert-info"><?= htmlspecialchars($initMsg) ?></div>
<?php endif; ?>
<div class="card mb-4 bg-light">
    <div class="card-body">
        <form method="GET" action="" class="row g-2">
            <div class="col-md-3">
                <select name="action_filter" class="form-select">
                    <option value="">Semua Aksi</option>
                    <?php foreach($actions as $act): ?>
                        <option value="<?= htmlspecialchars($act) ?>" <?= $action_filter === $act ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <input type="text" name="q" class="form-control" placeholder="Cari deskripsi atau username..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <?php if($q || $action_filter): ?>
            <div class="col-md-2">
                <a href="activity_logs.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
            <?php endif; ?>
        </form>
        <?php if(empty($logs) && $isAdmin): ?>
        <div class="mt-3">
            <a href="activity_logs.php?init=1" class="btn btn-outline-primary"><i class="bi bi-rocket"></i> Inisialisasi Log</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Waktu</th>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Deskripsi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="ps-4 text-muted small" style="white-space: nowrap;">
                            <?php if(!empty($log['created_at'])): ?>
                                <?= date('d M Y H:i:s', strtotime($log['created_at'])) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><span class="fw-medium text-dark"><?= htmlspecialchars($log['username'] ?? 'System') ?></span></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['action'] ?? '') ?></span></td>
                        <td class="text-secondary small"><?= htmlspecialchars($log['description'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($logs) && empty($errorMsg)): ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada aktivitas tercatat.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 