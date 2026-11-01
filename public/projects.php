<?php
$root = '../';
require __DIR__ . '/../config/db.php';

$pageTitle = "Daftar Proyek";
require __DIR__ . '/../includes/header.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$client_id = isset($_GET['client_id']) ? $_GET['client_id'] : '';
$partner_id = isset($_GET['partner_id']) ? $_GET['partner_id'] : '';
$service_id = isset($_GET['service_id']) ? $_GET['service_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

use Modules\Projects\Models\Project;
$projectModel = new Project();
$projects = $projectModel->getAll($_GET);

$clients = $pdo->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll();
$partners = $pdo->query("SELECT * FROM partners ORDER BY name ASC")->fetchAll();
$services = $pdo->query("SELECT * FROM services ORDER BY category, name ASC")->fetchAll();

$exportUrl = "export_data.php?type=projects"
    ."&q=".urlencode($q)
    ."&client_id=".urlencode($client_id)
    ."&partner_id=".urlencode($partner_id)
    ."&service_id=".urlencode($service_id)
    ."&status=".urlencode($status_filter)
    ."&start_date=".urlencode($start_date)
    ."&end_date=".urlencode($end_date);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">Daftar Proyek</h3>
        <p class="text-muted mb-0 small">Kelola semua proyek, status, dan keuangan</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $exportUrl ?>" class="btn btn-success shadow-sm"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Export CSV</a>
        <a href="project_form.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-2"></i>Proyek Baru</a>
    </div>
    </div>

<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body">
        <form method="GET" action="projects.php" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small text-muted">Pencarian</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Nama Klien / Layanan..." value="<?= htmlspecialchars($q) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Berjalan (Active)</option>
                    <option value="done" <?= $status_filter === 'done' ? 'selected' : '' ?>>Selesai (Done)</option>
                    <option value="internal" <?= $status_filter === 'internal' ? 'selected' : '' ?>>Internal</option>
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Klien</label>
                <select name="client_id" class="form-select">
                    <option value="">Semua Klien</option>
                    <?php foreach($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $client_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Mitra</label>
                <select name="partner_id" class="form-select">
                    <option value="">Semua Mitra</option>
                    <?php foreach($partners as $pt): ?>
                        <option value="<?= $pt['id'] ?>" <?= $partner_id == $pt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pt['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Layanan</label>
                <select name="service_id" class="form-select">
                    <option value="">Semua Layanan</option>
                    <?php foreach($services as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $service_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 me-2">Filter</button>
                <a href="projects.php" class="btn btn-light border" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
    </div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3 py-3 text-secondary small text-uppercase">Klien & Tanggal</th>
                        <th class="py-3 text-secondary small text-uppercase">Layanan</th>
                        <th class="py-3 text-secondary small text-uppercase">Mitra</th>
                        <th class="py-3 text-secondary small text-uppercase text-end">Nilai Proyek</th>
                        <th class="py-3 text-secondary small text-uppercase text-end">Pembayaran</th>
                        <th class="py-3 text-secondary small text-uppercase text-end">Biaya Ops</th>
                        <th class="py-3 text-secondary small text-uppercase text-center">Status</th>
                        <th class="pe-3 py-3 text-secondary small text-uppercase text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($projects)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Tidak ada data proyek yang sesuai filter.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach($projects as $p): 
                        $balance = $p['total_price'] - $p['paid'] - $p['partner_commission'];
                        $is_paid_off = ($p['total_price'] - $p['paid']) <= 0;
                        $progress = ($p['total_price'] > 0) ? ($p['paid'] / $p['total_price']) * 100 : 0;
                        $statusBadge = '';
                        if ($p['status'] == 'done') {
                            $statusBadge = '<span class="badge bg-success rounded-pill">Selesai</span>';
                        } elseif ($p['client_name'] == 'INTERNAL') {
                            $statusBadge = '<span class="badge bg-secondary rounded-pill">Internal</span>';
                        } else {
                            $statusBadge = '<span class="badge bg-primary rounded-pill">Berjalan</span>';
                        }
                        $paymentStatus = $is_paid_off ? '<span class="badge bg-success bg-opacity-10 text-success rounded-pill small">Lunas</span>' : '<span class="badge bg-warning bg-opacity-10 text-warning rounded-pill small">Belum Lunas</span>';
                    ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-bold text-dark">
                                <a href="client_detail.php?id=<?= $p['client_id'] ?>" class="text-dark text-decoration-none">
                                    <?= htmlspecialchars($p['client_name'] ?? '') ?>
                                </a>
                            </div>
                            <small class="text-muted"><?= date('d M Y', strtotime($p['created_at'])) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-info bg-opacity-10 text-info text-dark border"><?= htmlspecialchars($p['service_name'] ?? '') ?></span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php if(!empty($p['partner_id'])): ?>
                                    <a href="partner_detail.php?id=<?= $p['partner_id'] ?>" class="text-decoration-none text-muted">
                                        <?= htmlspecialchars($p['partner_name'] ?? '') ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($p['partner_name'] ?? '-') ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td class="text-end">
                            <div class="fw-bold"><?= formatRupiah($p['total_price']) ?></div>
                        </td>
                        <td class="text-end">
                            <div class="small"><?= formatRupiah($p['paid']) ?></div>
                            <?= $paymentStatus ?>
                        </td>
                        <td class="text-end">
                            <div class="text-danger"><?= formatRupiah($p['cost']) ?></div>
                            <?php if($p['unpaid_vendor'] > 0): ?>
                                <small class="text-warning fw-bold" style="font-size: 0.7rem;">Hutang: <?= formatRupiah($p['unpaid_vendor']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?= $statusBadge ?>
                        </td>
                        <td class="text-end pe-3">
                            <a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Detail</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>

<?php require __DIR__ . '/../includes/footer.php'; 