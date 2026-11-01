<?php
$root = '../';
require __DIR__ . '/../config/db.php';
use Modules\Finance\Models\Transaction;
$transactionModel = new Transaction();
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t', strtotime($start_date));
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$partner_id = isset($_GET['partner_id']) ? trim($_GET['partner_id']) : '';
$client_id = isset($_GET['client_id']) ? trim($_GET['client_id']) : '';
$service_id = isset($_GET['service_id']) ? trim($_GET['service_id']) : '';
$filters = [
    'start_date' => $start_date,
    'end_date' => $end_date,
    'category' => $category,
    'partner_id' => $partner_id,
    'client_id' => $client_id,
    'service_id' => $service_id,
    'q' => $q,
    'exclude_unpaid_expense' => true
];
if ($filter === 'income') {
    $filters['type'] = 'income';
} elseif ($filter === 'expense') {
    $filters['type'] = 'expense';
} elseif ($filter === 'prive') {
    $filters['category'] = 'Prive';
}
$transactions = $transactionModel->getAll($filters);
$balance = 0;
$total_prive = $transactionModel->getTotalPrive();
$pageTitle = "Riwayat Arus Kas";
require __DIR__ . '/../includes/header.php';
?>
<style>
.table-hover tbody tr:hover{background-color:#f8f9fa}.balance-cell{font-family:'Courier New',monospace;font-weight:bold}
</style>
<div class="row">
<div class="col-md-12">
<div class="d-flex justify-content-between align-items-center mb-3">
<h3>Riwayat Arus Kas (Cash Flow)</h3>
<a href="<?= $root ?>index.php" class="btn btn-outline-secondary">Kembali ke Dashboard</a>
</div>
<div class="d-flex justify-content-between align-items-center mb-3">
<div class="btn-group" role="group" aria-label="Filter">
<a href="cash_flow.php?filter=all" class="btn btn-outline-dark <?= $filter === 'all' ? 'active' : '' ?>"><i class="bi bi-list-ul"></i> Semua</a>
<a href="cash_flow.php?filter=prive" class="btn btn-outline-warning <?= $filter === 'prive' ? 'active' : '' ?>"><i class="bi bi-wallet2"></i> Prive</a>
<a href="cash_flow.php?filter=income" class="btn btn-outline-success <?= $filter === 'income' ? 'active' : '' ?>"><i class="bi bi-arrow-down-circle"></i> Pendapatan</a>
<a href="cash_flow.php?filter=expense" class="btn btn-outline-danger <?= $filter === 'expense' ? 'active' : '' ?>"><i class="bi bi-arrow-up-circle"></i> Pengeluaran</a>
</div>
<div class="d-flex align-items-center gap-2">
<span class="badge rounded-pill text-bg-warning">Total Prive diambil: <?= formatRupiah($total_prive) ?></span>
<?php 
$exportUrl = "export_data.php?type=cashflow"
."&start_date=".urlencode($start_date)
."&end_date=".urlencode($end_date)
."&filter=".urlencode($filter)
."&category=".urlencode($category)
."&partner_id=".urlencode($partner_id)
."&client_id=".urlencode($client_id)
."&service_id=".urlencode($service_id)
."&q=".urlencode($q);
?>
<a href="<?= $exportUrl ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
</div>
</div>
<form method="GET" class="row g-2 align-items-end mb-3">
<div class="col-auto">
<label class="form-label small">Pencarian</label>
<input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm" placeholder="Cari transaksi...">
</div>
<div class="col-auto">
<label class="form-label small">Dari</label>
<input type="date" name="start_date" value="<?= $start_date ?>" class="form-control form-control-sm">
</div>
<div class="col-auto">
<label class="form-label small">Sampai</label>
<input type="date" name="end_date" value="<?= $end_date ?>" class="form-control form-control-sm">
</div>
<div class="col-auto">
<label class="form-label small">Kategori</label>
<input type="text" name="category" value="<?= htmlspecialchars($category) ?>" class="form-control form-control-sm" placeholder="Pengeluaran saja">
</div>
<div class="col-auto">
<?php $partners = $pdo->query("SELECT id, name FROM partners ORDER BY is_default DESC, name ASC")->fetchAll(); ?>
<label class="form-label small">Mitra</label>
<select name="partner_id" class="form-select form-select-sm">
<option value="">Semua</option>
<?php foreach($partners as $p): ?>
<option value="<?= $p['id'] ?>" <?= ($partner_id == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['name'] ?? '') ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-auto">
<?php 
    $stmtClients = $pdo->prepare("SELECT id, name FROM clients WHERE company_id = ? ORDER BY name ASC");
    $stmtClients->execute([currentCompanyId()]);
    $clients = $stmtClients->fetchAll(); 
?>
<label class="form-label small">Klien</label>
<select name="client_id" class="form-select form-select-sm">
<option value="">Semua</option>
<?php foreach($clients as $c): ?>
<option value="<?= $c['id'] ?>" <?= ($client_id == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name'] ?? '') ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-auto">
<?php 
    $stmtServices = $pdo->prepare("SELECT id, name FROM services WHERE company_id = ? ORDER BY name ASC");
    $stmtServices->execute([currentCompanyId()]);
    $services = $stmtServices->fetchAll(); 
?>
<label class="form-label small">Layanan</label>
<select name="service_id" class="form-select form-select-sm">
<option value="">Semua</option>
<?php foreach($services as $s): ?>
<option value="<?= $s['id'] ?>" <?= ($service_id == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name'] ?? '') ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-auto">
<label class="form-label small">Tipe</label>
<select name="filter" class="form-select form-select-sm">
<option value="all" <?= $filter==='all'?'selected':'' ?>>Semua</option>
<option value="income" <?= $filter==='income'?'selected':'' ?>>Pendapatan</option>
<option value="expense" <?= $filter==='expense'?'selected':'' ?>>Pengeluaran</option>
<option value="prive" <?= $filter==='prive'?'selected':'' ?>>Prive</option>
</select>
</div>
<div class="col-auto">
<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-filter"></i> Terapkan</button>
</div>
</form>
<div class="alert alert-light border d-flex justify-content-between align-items-center">
<div>
<small class="text-muted">Periode:</small>
<span class="fw-bold"><?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></span>
<?php if ($category !== ''): ?>
<span class="ms-3"><small class="text-muted">Kategori:</small> <span class="badge bg-secondary"><?= htmlspecialchars($category) ?></span></span>
<?php endif; ?>
</div>
<div>
<a class="btn btn-sm btn-light border" href="cash_flow.php">Reset</a>
</div>
</div>
<div class="card border-0">
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead class="text-center sticky-top bg-white">
<tr>
<th>Tanggal</th>
<th>Keterangan</th>
<th>Proyek/Kategori</th>
<th>Masuk (Debit)</th>
<th>Keluar (Kredit)</th>
<th>Saldo (Balance)</th>
<th class="text-end">Aksi</th>
</tr>
</thead>
<tbody>
<?php foreach ($transactions as $t): ?>
<?php 
$amount_in = ($t['type'] == 'income') ? $t['amount'] : 0;
$amount_out = ($t['type'] == 'expense') ? $t['amount'] : 0;
$balance += $amount_in - $amount_out;
$row_class = '';
if ($t['category'] == 'Prive') $row_class = 'table-warning';
?>
<tr class="<?= $row_class ?>">
<td class="text-center"><?= date('d/m/Y', strtotime($t['transaction_date'])) ?></td>
<td><?= htmlspecialchars($t['note'] ?? '') ?><?php if($t['type'] == 'expense' && $t['category'] != 'Prive'): ?><br><small class="text-muted"><?= $t['category'] ?></small><?php endif; ?></td>
<td><?php if($t['client_name'] == 'INTERNAL'): ?><span class="badge bg-secondary">INTERNAL</span><?php else: ?><strong><?= htmlspecialchars($t['client_name'] ?? '') ?></strong><br><small><?= htmlspecialchars($t['service_name'] ?? '') ?></small><?php endif; ?></td>
<td class="text-end text-success"><?= $amount_in > 0 ? formatRupiah($amount_in) : '-' ?></td>
<td class="text-end text-danger"><?= $amount_out > 0 ? formatRupiah($amount_out) : '-' ?></td>
<td class="text-end balance-cell text-primary"><?= formatRupiah($balance) ?></td>
<td class="text-end">
<a href="edit_transaction.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
<a href="delete_transaction.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus data ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
</td>
</tr>
<?php endforeach; ?>
<?php if (count($transactions) == 0): ?>
<tr><td colspan="7" class="text-center py-4 text-muted">Belum ada transaksi.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<div class="alert alert-info mt-3">
<i class="bi bi-info-circle"></i> <strong>Catatan:</strong> Tabel ini hanya menampilkan uang yang benar-benar masuk atau keluar (Cash Basis). Biaya yang statusnya "Hutang" (Unpaid) tidak akan mengurangi Saldo Kas sampai Anda melunasinya.
<?php if($filter !== 'all'): ?><br><small>Saldo pada tampilan ini dihitung dari transaksi yang sesuai filter.</small><?php endif; ?>
</div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 