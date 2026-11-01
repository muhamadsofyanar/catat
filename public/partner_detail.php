<?php
$root = '../';
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: partners.php");
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt->execute([$id]);
$partner = $stmt->fetch();
if (!$partner) {
    die("Partner not found.");
}
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE partner_id = ?");
$stmtCount->execute([$id]);
$total_projects = $stmtCount->fetchColumn();
$stmtVal = $pdo->prepare("SELECT SUM(total_price) FROM projects WHERE partner_id = ?");
$stmtVal->execute([$id]);
$total_value = $stmtVal->fetchColumn() ?: 0;
$stmtComm = $pdo->prepare("SELECT SUM(partner_commission) FROM projects WHERE partner_id = ?");
$stmtComm->execute([$id]);
$total_commission = $stmtComm->fetchColumn() ?: 0;
$stmtProjects = $pdo->prepare("
    SELECT p., 
    (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income') as paid
    FROM projects p 
    WHERE p.partner_id = ? 
    ORDER BY created_at DESC
");
$stmtProjects->execute([$id]);
$projects = $stmtProjects->fetchAll();
$pageTitle = "Detail Mitra: " . $partner['name'];
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($partner['name'] ?? '') ?></h3>
        <p class="text-muted mb-0 small">Detail profil dan performa mitra (White Label)</p>
    </div>
    <div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-file-earmark-excel me-1"></i> Export
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export_data.php?type=projects&partner_id=<?= $partner['id'] ?>">Data Proyek</a></li>
                <li><a class="dropdown-item" href="export_data.php?type=partner_payments&partner_id=<?= $partner['id'] ?>">Riwayat Pembayaran</a></li>
            </ul>
        </div>
        <a href="partner_form.php?id=<?= $partner['id'] ?>" class="btn btn-outline-primary me-2"><i class="bi bi-pencil me-1"></i> Edit Profil</a>
        <a href="partners.php" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>
</div>
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <?php if(!empty($partner['logo'])): ?>
                        <img src="<?= $partner['logo'] ?>?t=<?= time() ?>" alt="Logo" class="me-3 border rounded p-1" style="height: 60px; max-width: 100px; object-fit: contain;">
                    <?php else: ?>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center me-3 border" style="width: 60px; height: 60px;">
                            <i class="bi bi-image text-muted fs-4"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h5 class="card-title mb-0"><?= htmlspecialchars($partner['name'] ?? '') ?></h5>
                        <?php if($partner['is_default']): ?>
                            <span class="badge bg-primary">DEFAULT PARTNER</span>
                        <?php endif; ?>
                    </div>
                </div>
                <h6 class="card-subtitle mb-3 text-muted fw-bold border-bottom pb-2">INFORMASI KONTAK & BANK</h6>
                <div class="mb-3">
                    <small class="text-muted d-block">No. Handphone</small>
                    <span class="fs-6"><?= htmlspecialchars($partner['phone'] ?: '-') ?></span>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Alamat</small>
                    <span class="fs-6"><?= htmlspecialchars($partner['address'] ?: '-') ?></span>
                </div>
                <div class="bg-light p-3 rounded">
                    <small class="text-muted d-block mb-1">Rekening Bank:</small>
                    <div class="fw-bold"><?= htmlspecialchars($partner['bank_name'] ?: '-') ?></div>
                    <div class="font-monospace fs-5"><?= htmlspecialchars($partner['bank_account'] ?: '-') ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($partner['bank_holder'] ?: '-') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="row g-3 h-100">
            <div class="col-md-4">
                <div class="card border-0 bg-primary bg-opacity-10 h-100 text-center">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <h2 class="fw-bold text-primary mb-0"><?= $total_projects ?></h2>
                        <small class="text-muted text-uppercase fw-bold">Total Proyek</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-info bg-opacity-10 h-100 text-center">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <h4 class="fw-bold text-info mb-0"><?= formatRupiah($total_value) ?></h4>
                        <small class="text-muted text-uppercase fw-bold">Total Omset Proyek</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-success bg-opacity-10 h-100 text-center">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <h4 class="fw-bold text-success mb-0"><?= formatRupiah($total_commission) ?></h4>
                        <small class="text-muted text-uppercase fw-bold">Total Komisi (Hak Mitra)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom-0">
        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Riwayat Proyek (White Label)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Tanggal</th>
                        <th>Klien</th>
                        <th>Layanan</th>
                        <th>Status</th>
                        <th class="text-end">Nilai Proyek</th>
                        <th class="text-end">Komisi Mitra</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($projects) > 0): ?>
                        <?php foreach ($projects as $p): 
                            $statusBadge = 'bg-secondary';
                            if ($p['status'] == 'ongoing') $statusBadge = 'bg-primary';
                            if ($p['status'] == 'completed') $statusBadge = 'bg-success';
                            if ($p['status'] == 'cancelled') $statusBadge = 'bg-danger';
                        ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($p['client_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['service_name'] ?? '') ?></td>
                            <td><span class="badge <?= $statusBadge ?> rounded-pill"><?= strtoupper($p['status']) ?></span></td>
                            <td class="text-end"><?= formatRupiah($p['total_price']) ?></td>
                            <td class="text-end fw-bold text-success"><?= formatRupiah($p['partner_commission']) ?></td>
                            <td class="text-end pe-4">
                                <a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Detail
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada riwayat proyek untuk mitra ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 