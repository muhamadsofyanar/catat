<?php
$root = '../';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$conditions = [];
$params = [];
if ($q !== '') {
    $conditions[] = "(name LIKE ? OR category LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($category_filter !== '') {
    $conditions[] = "category = ?";
    $params[] = $category_filter;
}
$sql = "SELECT * FROM services";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY category, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM services WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$grouped_services = [];
foreach ($services as $s) {
    $grouped_services[$s['category']][] = $s;
}
$pageTitle = "Manajemen Layanan";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">Daftar Layanan & Biaya Baku</h3>
        <p class="text-muted mb-0 small">Kelola layanan dan komponen biaya modal</p>
    </div>
    <div>
        <a href="export_data.php?type=services&q=<?= urlencode($q) ?>&category=<?= urlencode($category_filter) ?>" class="btn btn-outline-success me-2"><i class="bi bi-file-earmark-excel"></i> Export CSV</a>
        <a href="service_form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Layanan</a>
    </div>
</div>
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light">Kategori</span>
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari nama layanan..." value="<?= htmlspecialchars($q) ?>">
                    <button type="submit" class="btn btn-primary">Cari</button>
                    <?php if(!empty($q) || !empty($category_filter)): ?>
                        <a href="services.php" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<?php if (empty($grouped_services)): ?>
    <div class="alert alert-info">Belum ada data layanan. Silakan tambah layanan baru.</div>
<?php else: ?>
    <?php foreach ($grouped_services as $category => $items): ?>
        <h5 class="text-primary mt-4 mb-3 border-bottom pb-2"><?= htmlspecialchars($category ?: 'Lainnya') ?></h5>
        <div class="row">
            <?php foreach ($items as $s): ?>
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title"><?= htmlspecialchars($s['name'] ?? '') ?></h5>
                                <span class="badge bg-success"><?= formatRupiah($s['default_price']) ?></span>
                            </div>
                            <h6 class="card-subtitle mb-2 text-muted mt-2">Rincian Biaya Modal (HPP):</h6>
                            <ul class="list-group list-group-flush small mb-3">
                                <?php 
                                $costs = $pdo->prepare("SELECT * FROM service_costs WHERE service_id = ?");
                                $costs->execute([$s['id']]);
                                $costList = $costs->fetchAll();
                                $totalCost = 0;
                                ?>
                                <?php foreach($costList as $c): $totalCost += $c['amount']; ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <?= htmlspecialchars($c['description'] ?? '') ?>
                                        <span><?= formatRupiah($c['amount']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 fw-bold bg-light">
                                    Total Modal
                                    <span><?= formatRupiah($totalCost) ?></span>
                                </li>
                            </ul>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-success fw-bold small">
                                    Potensi Laba: <?= formatRupiah($s['default_price'] - $totalCost) ?>
                                </div>
                                <div>
                                    <a href="projects.php?service_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-info me-1" title="Lihat Proyek"><i class="bi bi-list-task"></i></a>
                                    <a href="service_form.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                                    <a href="delete_service.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus layanan ini?')">Hapus</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; 