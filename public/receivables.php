<?php
$root = '../';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
use Modules\Finance\Models\Receivable;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . $root . "index.php");
    exit;
}

$receivableModel = new Receivable();
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$only_overdue = isset($_GET['only_overdue']) && $_GET['only_overdue'] === '1';
$partner_id = isset($_GET['partner_id']) ? $_GET['partner_id'] : '';
$filters = [
    'q' => $q,
    'only_overdue' => $only_overdue,
    'partner_id' => $partner_id
];
$projects = $receivableModel->getAll($filters);
$total_receivable_sum = $receivableModel->getTotalReceivable($projects);
$lastReminders = [];
try {
    $stmtRem = $pdo->prepare("SELECT description, action, created_at FROM activity_logs WHERE company_id = ? AND action IN ('REMINDER_WA','REMINDER_EMAIL') ORDER BY created_at DESC LIMIT 500");
    $stmtRem->execute([currentCompanyId()]);
    $rowsRem = $stmtRem->fetchAll();
    foreach ($rowsRem as $r) {
        if (preg_match('/PID:\s*(\d+)/', $r['description'], $m)) {
            $pid = (int)$m[1];
            if (!isset($lastReminders[$pid])) {
                $lastReminders[$pid] = ['action' => $r['action'], 'created_at' => $r['created_at']];
            }
        }
    }
} catch (Exception $e) {}

$aging = [
    'current' => 0,
    'no_due' => 0,
    'over_0_30' => 0,
    'over_31_60' => 0,
    'over_61_90' => 0,
    'over_90_plus' => 0
];
foreach ($projects as $p) {
    $remaining = $p['remaining'] ?? 0;
    if (!$remaining) continue;
    if (empty($p['due_date'])) {
        $aging['no_due'] += $remaining;
    } else {
        $days = (int)floor((time() - strtotime($p['due_date'])) / 86400);
        if ($days <= 0) {
            $aging['current'] += $remaining;
        } elseif ($days <= 30) {
            $aging['over_0_30'] += $remaining;
        } elseif ($days <= 60) {
            $aging['over_31_60'] += $remaining;
        } elseif ($days <= 90) {
            $aging['over_61_90'] += $remaining;
        } else {
            $aging['over_90_plus'] += $remaining;
        }
    }
}
function normalizePhone($phone) {
    $digits = preg_replace('/\D+/', '', $phone ?? '');
    if ($digits === '') return '';
    if (strpos($digits, '62') === 0) return $digits;
    if (strpos($digits, '0') === 0) return '62' . substr($digits, 1);
    return $digits;
}

$pageTitle = "Piutang & Penagihan - LegaVoice";
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-hourglass-split text-warning me-2"></i>Piutang & Penagihan</h3>
        <p class="text-muted mb-0">Daftar tagihan ke klien yang belum lunas.</p>
    </div>
    <div class="text-end">
        <h2 class="fw-bold text-warning mb-0"><?= formatRupiah($total_receivable_sum) ?></h2>
        <small class="text-uppercase fw-bold text-muted">Total Piutang</small>
    </div>
    </div>

<div class="row g-3 mb-3">
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold">Belum Jatuh Tempo</small>
                <h6 class="fw-bold text-success mb-0"><?= formatRupiah($aging['current']) ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold">Tanpa Due Date</small>
                <h6 class="fw-bold text-secondary mb-0"><?= formatRupiah($aging['no_due']) ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold">Lewat 1–30</small>
                <h6 class="fw-bold text-warning mb-0"><?= formatRupiah($aging['over_0_30']) ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold">Lewat 31–60</small>
                <h6 class="fw-bold text-warning mb-0"><?= formatRupiah($aging['over_31_60']) ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold">Lewat 61–90</small>
                <h6 class="fw-bold text-danger mb-0"><?= formatRupiah($aging['over_61_90']) ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold">Lewat >90</small>
                <h6 class="fw-bold text-danger mb-0"><?= formatRupiah($aging['over_90_plus']) ?></h6>
            </div>
        </div>
    </div>
    </div>

<div class="card border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <form class="d-flex align-items-center w-100" method="GET" action="receivables.php">
            <div class="input-group me-2" style="max-width: 300px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control border-start-0 bg-light" placeholder="Cari klien/layanan..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="me-2">
                <?php 
                    $stmtPartners = $pdo->prepare("SELECT id, name FROM partners WHERE company_id = ? ORDER BY is_default DESC, name ASC");
                    $stmtPartners->execute([currentCompanyId()]);
                    $partners = $stmtPartners->fetchAll(); 
                ?>
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
                <a href="receivables.php" class="btn btn-light border" title="Reset"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
            <a href="export_data.php?type=receivables&q=<?= urlencode($q) ?>&only_overdue=<?= $only_overdue ?>&partner_id=<?= urlencode($partner_id) ?>" class="btn btn-success ms-2"><i class="bi bi-file-earmark-spreadsheet"></i> Export</a>
            <a href="export_data.php?type=receivables_reminders&q=<?= urlencode($q) ?>&only_overdue=<?= $only_overdue ?>&partner_id=<?= urlencode($partner_id) ?>" class="btn btn-outline-success ms-2"><i class="bi bi-chat-text"></i> Export Reminders</a>
            <a href="auto_remind.php?only_overdue=<?= $only_overdue ?>&partner_id=<?= urlencode($partner_id) ?>&q=<?= urlencode($q) ?>" class="btn btn-outline-primary ms-2"><i class="bi bi-bell"></i> Batch Reminders</a>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Klien & Layanan</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-end">Nilai Proyek</th>
                        <th class="text-end">Dibayar</th>
                        <th class="text-end">Sisa Tagihan</th>
                        <th class="text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($projects) > 0): ?>
                        <?php foreach ($projects as $p): 
                            $is_overdue = !empty($p['due_date']) && strtotime($p['due_date']) < strtotime(date('Y-m-d'));
                            $wa_phone = normalizePhone($p['phone']);
                        ?>
                        <tr class="<?= $is_overdue ? 'table-danger' : '' ?>">
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['client_name'] ?? '') ?></div>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['service_name'] ?? '') ?></span>
                            </td>
                            <td>
                                <?php if ($p['due_date']): ?>
                                    <div class="<?= $is_overdue ? 'text-danger fw-bold' : 'text-muted' ?>">
                                        <?= date('d M Y', strtotime($p['due_date'])) ?>
                                    </div>
                                    <?php if($is_overdue): ?>
                                        <small class="text-danger"><i class="bi bi-exclamation-circle-fill me-1"></i>Lewat Jatuh Tempo</small>
                                    <?php endif; ?>
                                    <?php 
                                        try {
                                            $stmtLR = $pdo->prepare("SELECT action, created_at FROM activity_logs WHERE (action='REMINDER_WA' OR action='REMINDER_EMAIL') AND description LIKE ? ORDER BY created_at DESC LIMIT 1");
                                            $stmtLR->execute(['PID: ' . $p['id'] . '%']);
                                            $lr = $stmtLR->fetch();
                                            if ($lr) {
                                                $kanal = ($lr['action'] === 'REMINDER_WA') ? 'WA' : 'Email';
                                                echo '<div class="small text-muted mt-1">Terakhir diingatkan: ' . date('d M Y H:i', strtotime($lr['created_at'])) . ' (' . $kanal . ')</div>';
                                            }
                                        } catch (Exception $e) {}
                                    ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= formatRupiah($p['total_price']) ?></td>
                            <td class="text-end text-success"><?= formatRupiah($p['paid']) ?></td>
                            <td class="text-end fw-bold text-warning"><?= formatRupiah($p['remaining']) ?></td>
                            <td class="text-center pe-4">
                                <div class="btn-group">
                                    <a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                                    <a href="preview_reminder.php?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" title="Preview Pengingat"><i class="bi bi-eye"></i> Preview</a>
                                    <?php if($wa_phone): ?>
                                        <a href="send_reminder.php?project_id=<?= $p['id'] ?>&channel=wa" class="btn btn-sm btn-success text-white" title="Kirim WA"><i class="bi bi-whatsapp"></i></a>
                                    <?php endif; ?>
                                    <?php if(!empty($p['email'])): ?>
                                        <a href="send_reminder.php?project_id=<?= $p['id'] ?>&channel=email" class="btn btn-sm btn-outline-secondary" title="Kirim Email"><i class="bi bi-envelope"></i></a>
                                    <?php endif; ?>
                                    <?php $lr = $lastReminders[$p['id']] ?? null; ?>
                                    <?php if($lr): ?>
                                        <span class="ms-2 text-muted small"><?= date('d M Y', strtotime($lr['created_at'])) ?> (<?= $lr['action'] === 'REMINDER_WA' ? 'WA' : 'Email' ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <div class="mb-2"><i class="bi bi-emoji-smile fs-1 text-primary"></i></div>
                                Tidak ada tagihan yang belum lunas.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>

<?php require __DIR__ . '/../includes/footer.php'; 