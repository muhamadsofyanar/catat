<?php
$root = '../';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/header.php';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if (!$q) {
    echo '<div class="alert alert-warning">Silakan masukkan kata kunci pencarian.</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}
$term = "%$q%";
$stmt = $pdo->prepare("
    SELECT p.*, part.name as partner_name 
    FROM projects p 
    LEFT JOIN partners part ON p.partner_id = part.id
    WHERE p.client_name LIKE ? OR p.service_name LIKE ? OR p.description LIKE ?
    ORDER BY p.created_at DESC LIMIT 20
");
$stmt->execute([$term, $term, $term]);
$projects = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT * FROM clients WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? LIMIT 20");
$stmt->execute([$term, $term, $term]);
$clients = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT * FROM partners WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR expertise LIKE ? LIMIT 20");
$stmt->execute([$term, $term, $term, $term]);
$partners = $stmt->fetchAll();
$stmt = $pdo->prepare("
    SELECT t.*, p.client_name 
    FROM transactions t 
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.category LIKE ? OR t.note LIKE ?
    ORDER BY t.transaction_date DESC LIMIT 20
");
$stmt->execute([$term, $term]);
$transactions = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>Hasil Pencarian: "<?= htmlspecialchars($q) ?>"</h3>
</div>
<ul class="nav nav-tabs mb-4" id="searchTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="projects-tab" data-bs-toggle="tab" data-bs-target="#projects" type="button" role="tab">
            Proyek <span class="badge bg-secondary ms-1"><?= count($projects) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="clients-tab" data-bs-toggle="tab" data-bs-target="#clients" type="button" role="tab">
            Klien <span class="badge bg-secondary ms-1"><?= count($clients) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="partners-tab" data-bs-toggle="tab" data-bs-target="#partners" type="button" role="tab">
            Mitra <span class="badge bg-secondary ms-1"><?= count($partners) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
            Transaksi <span class="badge bg-secondary ms-1"><?= count($transactions) ?></span>
        </button>
    </li>
</ul>
<div class="tab-content" id="searchTabContent">
    <div class="tab-pane fade show active" id="projects" role="tabpanel">
        <?php if (empty($projects)): ?>
            <p class="text-muted">Tidak ditemukan proyek yang cocok.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Klien</th>
                            <th>Layanan</th>
                            <th>Nilai</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): ?>
                        <tr>
                            <td><?= $p['created_at'] ?></td>
                            <td><?= htmlspecialchars($p['client_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['service_name'] ?? '') ?></td>
                            <td>Rp <?= number_format($p['total_price'], 0, ',', '.') ?></td>
                            <td>
                                <span class="badge bg-<?= $p['status'] == 'done' ? 'success' : ($p['status'] == 'cancel' ? 'danger' : 'primary') ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="clients" role="tabpanel">
        <?php if (empty($clients)): ?>
            <p class="text-muted">Tidak ditemukan klien yang cocok.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Telepon</th>
                            <th>Alamat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['address'] ?? '') ?></td>
                            <td>
                                <a href="client_detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-info">Detail</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="partners" role="tabpanel">
        <?php if (empty($partners)): ?>
            <p class="text-muted">Tidak ditemukan mitra yang cocok.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Keahlian</th>
                            <th>Email</th>
                            <th>Telepon</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partners as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['expertise'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['phone'] ?? '') ?></td>
                            <td>
                                <a href="partner_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info">Detail</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="transactions" role="tabpanel">
        <?php if (empty($transactions)): ?>
            <p class="text-muted">Tidak ditemukan transaksi yang cocok.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Tipe</th>
                            <th>Kategori</th>
                            <th>Deskripsi</th>
                            <th>Jumlah</th>
                            <th>Proyek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?= $t['transaction_date'] ?></td>
                            <td>
                                <span class="badge bg-<?= $t['type'] == 'income' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($t['type'] == 'income' ? 'Masuk' : 'Keluar') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($t['category'] ?? '') ?></td>
                            <td>
                                <?= htmlspecialchars($t['description'] ?? '') ?>
                                <?php if($t['note']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($t['note'] ?? '') ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="<?= $t['type'] == 'income' ? 'text-success' : 'text-danger' ?>">
                                Rp <?= number_format($t['amount'], 0, ',', '.') ?>
                            </td>
                            <td>
                                <?php if($t['project_id']): ?>
                                    <a href="project_detail.php?id=<?= $t['project_id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($t['client_name'] ?? '') ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 