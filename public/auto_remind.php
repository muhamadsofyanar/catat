<?php
$root = '../';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . $root . "index.php");
    exit;
}
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$only_overdue = isset($_GET['only_overdue']) && $_GET['only_overdue'] === '1';
$partner_id = isset($_GET['partner_id']) ? $_GET['partner_id'] : '';
$settingsRow = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$intervalDays = (int)($settingsRow['reminder_min_interval_days'] ?? 7);
$conditions = ["p.client_name != 'INTERNAL'"];
$params = [];
if ($q !== '') {
    $conditions[] = "(p.client_name LIKE ? OR p.service_name LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($partner_id !== '') {
    $conditions[] = "p.partner_id = ?";
    $params[] = $partner_id;
}
$whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
$stmt = $pdo->prepare("
    SELECT p.*,
        (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type='income') AS paid,
        c.phone, c.email
    FROM projects p
    LEFT JOIN clients c ON c.id = p.client_id
    $whereSql
    ORDER BY COALESCE(p.due_date, '9999-12-31') ASC, p.created_at DESC
");
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
$eligible = [];
foreach ($projects as $p) {
    $paid = (float)($p['paid'] ?? 0);
    $remaining = (float)($p['total_price'] - $paid);
    if ($remaining <= 0) continue;
    $is_overdue = !empty($p['due_date']) && strtotime($p['due_date']) < strtotime(date('Y-m-d'));
    if ($only_overdue && !$is_overdue) continue;
    $stmtLR = $pdo->prepare("SELECT action, created_at FROM activity_logs WHERE (action='REMINDER_WA' OR action='REMINDER_EMAIL') AND description LIKE ? ORDER BY created_at DESC LIMIT 1");
    $stmtLR->execute(['PID: ' . $p['id'] . '%']);
    $lr = $stmtLR->fetch(PDO::FETCH_ASSOC);
    $can_remind = true;
    $last_info = null;
    if ($lr) {
        $last_ts = strtotime($lr['created_at']);
        $diff_days = floor((time() - $last_ts) / 86400);
        $can_remind = $diff_days >= $intervalDays;
        $last_info = ['date' => date('d M Y H:i', $last_ts), 'channel' => ($lr['action'] === 'REMINDER_WA' ? 'WA' : 'Email'), 'days' => $diff_days];
    }
    $eligible[] = [
        'p' => $p,
        'remaining' => $remaining,
        'is_overdue' => $is_overdue,
        'can_remind' => $can_remind,
        'last' => $last_info
    ];
}
$pageTitle = "Batch Reminders";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-bell text-success me-2"></i>Batch Reminders</h3>
        <p class="text-muted mb-0">Kandidat penagihan berdasarkan due date dan jeda minimal pengingat.</p>
    </div>
    <div class="text-end">
        <span class="badge bg-light text-dark border">Jeda Minimal</span>
        <h6 class="fw-bold mb-0"><?= (int)$intervalDays ?> hari</h6>
    </div>
</div>
<div class="card border-0 mb-3">
    <div class="card-body">
        <form class="d-flex align-items-center" method="GET" action="auto_remind.php">
            <div class="input-group me-2" style="max-width: 300px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control border-start-0 bg-light" placeholder="Cari klien/layanan..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="me-2">
                <?php $partners = $pdo->query("SELECT id, name FROM partners ORDER BY is_default DESC, name ASC")->fetchAll(); ?>
                <select name="partner_id" class="form-select" onchange="this.form.submit()" style="max-width: 200px;">
                    <option value="">Semua Mitra</option>
                    <?php foreach($partners as $ptr): ?>
                        <option value="<?= $ptr['id'] ?>" <?= $partner_id == $ptr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ptr['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check form-switch text-nowrap me-3">
                <input class="form-check-input" type="checkbox" value="1" id="onlyOverdue" name="only_overdue" <?= $only_overdue ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label small fw-bold text-danger" for="onlyOverdue">Hanya Jatuh Tempo</label>
            </div>
            <button class="btn btn-primary me-2" type="submit">Filter</button>
            <?php if($q || $only_overdue || $partner_id): ?>
                <a href="auto_remind.php" class="btn btn-light border" title="Reset"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>
</div>
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Klien & Layanan</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-end">Sisa Tagihan</th>
                        <th>Status</th>
                        <th class="text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($eligible) > 0): ?>
                        <?php foreach ($eligible as $row): 
                            $p = $row['p'];
                            $can = $row['can_remind'];
                            $last = $row['last'];
                        ?>
                        <tr class="<?= $row['is_overdue'] ? 'table-danger' : '' ?>">
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['client_name'] ?? '') ?></div>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['service_name'] ?? '') ?></span>
                            </td>
                            <td>
                                <?php if ($p['due_date']): ?>
                                    <div class="<?= $row['is_overdue'] ? 'text-danger fw-bold' : 'text-muted' ?>">
                                        <?= date('d M Y', strtotime($p['due_date'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                                <?php if($last): ?>
                                    <div class="small text-muted mt-1">Terakhir: <?= $last['date'] ?> (<?= $last['channel'] ?>)</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold text-warning"><?= formatRupiah($row['remaining']) ?></td>
                            <td>
                                <?php if($can): ?>
                                    <span class="badge bg-success-subtle text-success border">Siap Diingatkan</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">Tunggu Jeda</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-4">
                                <div class="btn-group">
                                    <a href="preview_reminder.php?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info">Preview</a>
                                    <a href="send_reminder.php?project_id=<?= $p['id'] ?>&channel=wa" class="btn btn-sm btn-success <?= $can ? '' : 'disabled' ?>">WA</a>
                                    <a href="send_reminder.php?project_id=<?= $p['id'] ?>&channel=email" class="btn btn-sm btn-outline-secondary <?= $can ? '' : 'disabled' ?>">Email</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <div class="mb-2"><i class="bi bi-check2-circle fs-1 text-success"></i></div>
                                Tidak ada kandidat pengingat yang memenuhi jeda minimal.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 