<?php
$root = '../';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . $root . "index.php");
    exit;
}
$stmt = $pdo->prepare("SELECT id FROM projects WHERE client_name = 'INTERNAL' AND service_name = 'KEUANGAN PERUSAHAAN'");
$stmt->execute();
$project = $stmt->fetch();
if (!$project) {
    $stmt = $pdo->prepare("INSERT INTO projects (client_name, service_name, status) VALUES ('INTERNAL', 'KEUANGAN PERUSAHAAN', 'internal')");
    $stmt->execute();
    $project_id = $pdo->lastInsertId();
    logActivity($pdo, 'CREATE_PROJECT', "Created Internal Project for Expenses");
} else {
    $project_id = $project['id'];
}
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$type_filter = isset($_GET['type_filter']) ? $_GET['type_filter'] : '';
$category_filter = isset($_GET['category_filter']) ? $_GET['category_filter'] : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$conditions = ["project_id = ?", "category NOT IN ('Prive', 'Kasbon', 'Company Withdrawal')"];
$params = [$project_id];
if ($start_date && $end_date) {
    $conditions[] = "transaction_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}
if ($type_filter) {
    $conditions[] = "type = ?";
    $params[] = $type_filter;
}
if ($category_filter) {
    $conditions[] = "category = ?";
    $params[] = $category_filter;
}
if ($q) {
    $conditions[] = "note LIKE ?";
    $params[] = "%$q%";
}
$whereSql = implode(" AND ", $conditions);
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE $whereSql ORDER BY transaction_date DESC");
$stmt->execute($params);
$transactions = $stmt->fetchAll();
$catStmt = $pdo->prepare("SELECT DISTINCT category FROM transactions WHERE project_id = ? AND category NOT IN ('Prive', 'Kasbon', 'Company Withdrawal') ORDER BY category ASC");
$catStmt->execute([$project_id]);
$existing_categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
$pageTitle = "Transaksi Umum (Non-Proyek)";
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="mb-3">Transaksi Umum (Non-Proyek)</h3>
            <div class="alert alert-info border-start border-4 border-info mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle-fill fs-3 me-3 text-info"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Info Keuangan</h6>
                        <ul class="mb-0 small ps-3">
                            <li>Transaksi ini <strong>mengurangi Saldo Kas Tunai</strong>.</li>
                            <li><strong>TIDAK mengurangi Profit Sharing Proyek</strong> (Aman untuk bagi hasil).</li>
                            <li><strong>BUKAN Hutang Owner</strong> (Murni biaya operasional kantor).</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card border-0 mb-4">
                <div class="card-header bg-white border-bottom-0 pb-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i>Input Transaksi Baru</h5>
                </div>
                <div class="card-body">
                    <form action="save_transaction.php" method="POST" class="row g-3">
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">1. Jenis Transaksi</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-arrow-left-right"></i></span>
                                <select name="type" class="form-select" id="typeSelect" onchange="updateCategories()">
                                    <option value="expense">Pengeluaran (Uang Keluar)</option>
                                    <option value="income">Pemasukan (Uang Masuk)</option>
                                </select>
                            </div>
                            <div class="form-text">Pilih 'Pengeluaran' untuk biaya kantor.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">2. Kategori</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-tags"></i></span>
                                <select name="category" class="form-select" id="categorySelect"></select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">3. Nominal (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="amount" class="form-control" required placeholder="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">4. Tanggal</label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                             <label class="form-label fw-bold">5. Status Pembayaran</label>
                             <select name="status" class="form-select">
                                 <option value="paid">Lunas / Sudah Dibayar</option>
                                 <option value="unpaid">Belum Dibayar (Hutang Kantor)</option>
                             </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">6. Keterangan (Opsional)</label>
                            <input type="text" name="note" class="form-control" placeholder="Contoh: Beli Kertas HVS 1 Rim, Bayar Token Listrik, dll">
                        </div>
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold"><i class="bi bi-save me-2"></i>Simpan Transaksi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="card border-0 mb-4 bg-light">
        <div class="card-body">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Tipe</label>
                    <select name="type_filter" class="form-select form-select-sm">
                        <option value="">Semua Tipe</option>
                        <option value="income" <?= $type_filter === 'income' ? 'selected' : '' ?>>Pemasukan</option>
                        <option value="expense" <?= $type_filter === 'expense' ? 'selected' : '' ?>>Pengeluaran</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Kategori</label>
                    <select name="category_filter" class="form-select form-select-sm">
                        <option value="">Semua Kategori</option>
                        <?php foreach($existing_categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Cari Catatan</label>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Kata kunci..." value="<?= htmlspecialchars($q) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
                    <a href="general_expenses.php" class="btn btn-sm btn-outline-secondary w-100 mt-1">Reset</a>
                </div>
            </form>
        </div>
    </div>
    <div class="card border-0">
        <div class="card-header bg-white border-bottom-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Riwayat Transaksi Umum</h5>
            <a href="export_data.php?type=general_expenses&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&type_filter=<?= $type_filter ?>&category_filter=<?= urlencode($category_filter) ?>&q=<?= urlencode($q) ?>" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Tipe</th>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t):
                            $is_income = $t['type'] === 'income';
                            $row_class = $is_income ? 'table-success' : '';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><?= date('d/m/Y', strtotime($t['transaction_date'])) ?></td>
                            <td>
                                <?php if($is_income): ?>
                                    <span class="badge bg-success">Masuk</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Keluar</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= $t['category'] ?></span></td>
                            <td><?= $t['note'] ?></td>
                            <td>
                                <?php if($t['status'] == 'unpaid'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                    <a href="mark_paid.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-success ms-1" title="Tandai selesai" onclick="return confirm('Tandai transaksi ini sudah selesai?')"><i class="bi bi-check-lg"></i></a>
                                <?php else: ?>
                                    <span class="badge bg-primary">Selesai</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold <?= $is_income ? 'text-success' : 'text-danger' ?>">
                                <?= $is_income ? '+' : '-' ?><?= formatRupiah($t['amount']) ?>
                            </td>
                            <td class="text-end">
                                <a href="edit_transaction.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="delete_transaction.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus data ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($transactions) == 0): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada data transaksi umum.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
const expenseCategories = [
    "Gaji Tetap", "Listrik", "Internet", "Sewa", "Maintenance", "Marketing", "ATK", "Lainnya"
];
const incomeCategories = [
    "Investasi", "Bunga Bank", "Penjualan Aset", "Refund", "Lainnya"
];
function updateCategories() {
    const type = document.getElementById('typeSelect').value;
    const categorySelect = document.getElementById('categorySelect');
    categorySelect.innerHTML = '';
    const categories = type === 'expense' ? expenseCategories : incomeCategories;
    categories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat;
        option.text = cat;
        categorySelect.appendChild(option);
    });
}
updateCategories();
</script>
<?php require __DIR__ . '/../includes/footer.php'; 