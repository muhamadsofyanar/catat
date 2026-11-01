<?php
$root = '../';
require __DIR__ . '/../config/db.php';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$params = [];
$sql = "SELECT * FROM partners";
if ($q !== '') {
    $sql .= " WHERE name LIKE ? OR phone LIKE ? OR bank_name LIKE ?";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$sql .= " ORDER BY is_default DESC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$partners = $stmt->fetchAll();
$exportUrl = "export_data.php?type=partners&q=" . urlencode($q);
$pageTitle = "Manajemen Mitra (White Label)";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">Manajemen Mitra (White Label)</h3>
        <p class="text-muted mb-0 small">Kelola mitra dan brand untuk white label</p>
    </div>
    <a href="partner_form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Tambah Mitra Baru</a>
</div>
<div class="card border-0 mb-4">
    <div class="card-body bg-light rounded-3">
        <form method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="q" class="form-control" placeholder="Cari nama, no hp, atau bank..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Cari</button>
            </div>
        </form>
         <div class="mt-2 text-end">
             <a href="<?= $exportUrl ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Export CSV</a>
        </div>
    </div>
</div>
<div class="row">
    <?php foreach($partners as $p): ?>
    <div class="col-md-6 mb-3">
        <div class="card h-100 border-0 <?= $p['is_default'] ? 'border-start border-4 border-primary' : '' ?>">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <?php if(!empty($p['logo'])): ?>
                        <img src="<?= $p['logo'] ?>?t=<?= time() ?>" alt="Logo" class="me-3" style="height: 50px; max-width: 100px; object-fit: contain;">
                    <?php else: ?>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="bi bi-image text-muted"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h5 class="card-title mb-0">
                            <?= htmlspecialchars($p['name'] ?? '') ?>
                            <?php if($p['is_default']): ?>
                                <span class="badge bg-primary ms-1" style="font-size: 0.6em;">DEFAULT</span>
                            <?php endif; ?>
                        </h5>
                        <small class="text-muted"><?= htmlspecialchars($p['phone'] ?? '') ?></small>
                    </div>
                </div>
                <p class="card-text small text-muted mb-2">
                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($p['address'] ?? '') ?>
                </p>
                <div class="bg-light p-2 rounded small mb-3">
                    <strong>Bank:</strong> <?= htmlspecialchars($p['bank_name'] ?? '') ?><br>
                    <strong>Rek:</strong> <?= htmlspecialchars($p['bank_account'] ?? '') ?> (<?= htmlspecialchars($p['bank_holder'] ?? '') ?>)
                </div>
                <div class="d-flex gap-2">
                    <a href="partner_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info w-100">Detail</a>
                    <a href="partner_form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary w-100">Edit</a>
                    <?php if(!$p['is_default']): ?>
                        <a href="delete_partner.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Yakin hapus mitra ini?')">Hapus</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 