<?php
$root = '../';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$vendor = $_GET['vendor'] ?? '';
$partner_id = $_GET['partner_id'] ?? '';
$conditions = [];
$params = [];
$conditions[] = "t.type = 'expense'";
$conditions[] = "(t.status = 'paid' OR t.status IS NULL)"; 
$conditions[] = "t.transaction_date BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;
$conditions[] = "t.category NOT IN ('Kasbon', 'Prive', 'Company Withdrawal', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan', 'Operasional Kantor', 'Company Expense')";
if ($vendor) {
    $conditions[] = "t.category LIKE ?";
    $params[] = "%$vendor%";
}
if ($partner_id) {
    $conditions[] = "p.partner_id = ?";
    $params[] = $partner_id;
}
$whereSql = "WHERE " . implode(" AND ", $conditions);
$stmt = $pdo->prepare("
    SELECT t.*, p.client_name, p.service_name,
    part.name as partner_name
    FROM transactions t
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN partners part ON p.partner_id = part.id
    $whereSql
    ORDER BY t.transaction_date DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();
$total_paid = array_sum(array_column($payments, 'amount'));
$partners = $pdo->query("SELECT * FROM partners ORDER BY name ASC")->fetchAll();
$exportUrl = "export_data.php?type=partner_payments"
    ."&start_date=".urlencode($start_date)
    ."&end_date=".urlencode($end_date)
    ."&vendor=".urlencode($vendor)
    ."&partner_id=".urlencode($partner_id);
$pageTitle = "Riwayat Pembayaran Mitra - LegaVoice";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">Riwayat Pembayaran Mitra</h3>
        <p class="text-muted mb-0">Daftar pembayaran ke vendor/mitra yang sudah lunas.</p>
    </div>
    <div class="text-end">
        <h2 class="fw-bold text-success mb-0"><?= formatRupiah($total_paid) ?></h2>
        <small class="text-uppercase fw-bold text-muted">Total Terbayar</small>
    </div>
</div>
<div class="card border-0 mb-4">
    <div class="card-body bg-light rounded-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-bold">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Mitra (Penanggung Jawab)</label>
                <select name="partner_id" class="form-select">
                    <option value="">Semua Mitra</option>
                    <?php foreach($partners as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $partner_id == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Cari Vendor / Kategori</label>
                <input type="text" name="vendor" class="form-control" placeholder="Contoh: Notaris, Ongkir..." value="<?= htmlspecialchars($vendor) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
            </div>
        </form>
        <div class="mt-3 text-end">
             <a href="<?= $exportUrl ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Export CSV</a>
        </div>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                        <th class="ps-4">Tanggal</th>
                        <th>Mitra / Vendor</th>
                        <th>Kategori</th>
                        <th>Project Terkait</th>
                        <th>Keterangan</th>
                        <th class="text-end pe-4">Jumlah</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(count($payments) > 0): ?>
                    <?php foreach($payments as $p): ?>
                    <tr>
                        <td class="ps-4 text-nowrap"><?= date('d M Y', strtotime($p['transaction_date'])) ?></td>
                        <td>
                            <?php if(!empty($p['partner_name'])): ?>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($p['partner_name'] ?? '') ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= htmlspecialchars($p['category'] ?? '') ?></span></td>
                        <td>
                            <?php if($p['client_name']): ?>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['client_name'] ?? '') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($p['service_name'] ?? '') ?></small>
                            <?php else: ?>
                                <span class="text-muted fst-italic">- Operasional -</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['note'] ?? '') ?></td>
                        <td class="text-end fw-bold text-success pe-4"><?= formatRupiah($p['amount']) ?></td>
                        <td class="text-end pe-4">
                            <a href="edit_transaction.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning border-0 me-1" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a href="delete_transaction.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Hapus data ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Tidak ada data pembayaran pada periode ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 