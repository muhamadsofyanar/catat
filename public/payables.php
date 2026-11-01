<?php
$root = '../';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
use Modules\Finance\Models\Transaction;
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . $root . "index.php");
    exit;
}
$transactionModel = new Transaction();
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$partner_id = isset($_GET['partner_id']) ? $_GET['partner_id'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$filters = [
    'q' => $q,
    'partner_id' => $partner_id,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'type' => 'expense',
    'status' => 'unpaid'
];
$rows = $transactionModel->getAll($filters);
$total_unpaid = 0;
foreach($rows as $r) $total_unpaid += $r['amount'];
$stmtPartners = $pdo->prepare("SELECT * FROM partners WHERE company_id = ? ORDER BY name ASC");
$stmtPartners->execute([currentCompanyId()]);
$partners = $stmtPartners->fetchAll();
$exportUrl = "export_data.php?type=payables"
    ."&q=".urlencode($q)
    ."&partner_id=".urlencode($partner_id)
    ."&start_date=".urlencode($start_date)
    ."&end_date=".urlencode($end_date);
$pageTitle = "Hutang Vendor - LegaVoice";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
<div>
<h3 class="fw-bold mb-0 text-dark"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Hutang Vendor & Operasional</h3>
<p class="text-muted mb-0">Daftar pengeluaran yang harus segera dibayar.</p>
</div>
<div class="text-end">
<h2 class="fw-bold text-danger mb-0"><?= formatRupiah($total_unpaid) ?></h2>
<small class="text-uppercase fw-bold text-muted">Total Tagihan</small>
</div>
</div>
<div class="card border-0 mb-4 shadow-sm rounded-3">
<div class="card-body bg-light">
<form class="row g-3" method="GET" action="payables.php">
<div class="col-md-4">
<label class="form-label small fw-bold">Pencarian</label>
<div class="input-group">
<span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
<input type="text" name="q" class="form-control border-start-0" placeholder="Cari klien, vendor, catatan..." value="<?= htmlspecialchars($q) ?>">
</div>
</div>
<div class="col-md-3">
<label class="form-label small fw-bold">Mitra / Penanggung Jawab</label>
<select name="partner_id" class="form-select">
<option value="">Semua Mitra</option>
<?php foreach($partners as $pt): ?>
<option value="<?= $pt['id'] ?>" <?= $partner_id == $pt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pt['name'] ?? '') ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-3">
<label class="form-label small fw-bold">Dari Tanggal (Opsional)</label>
<input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
</div>
<div class="col-md-2 d-flex align-items-end gap-2">
<button class="btn btn-primary w-100" type="submit">Filter</button>
<?php if($q || $partner_id || $start_date): ?>
<a href="payables.php" class="btn btn-light border" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
<?php endif; ?>
</div>
</form>
<div class="mt-3 text-end">
<a href="<?= $exportUrl ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Export CSV</a>
</div>
</div>
</div>
<div class="card border-0 mb-4 shadow-sm">
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead>
<tr>
<th class="ps-4">Tanggal</th>
<th>Kategori / Vendor</th>
<th>Mitra</th>
<th>Terkait Proyek</th>
<th>Catatan</th>
<th class="text-end">Jumlah</th>
<th class="text-center pe-4">Aksi</th>
</tr>
</thead>
<tbody>
<?php if (count($rows) > 0): ?>
<?php foreach ($rows as $r): 
$is_cash_used = strpos($r['note'], '[UANG TERPAKAI]') !== false;
$display_note = str_replace('[UANG TERPAKAI]', '', $r['note']);
?>
<tr class="<?= $is_cash_used ? 'table-warning' : '' ?>">
<td class="ps-4 text-nowrap"><i class="bi bi-calendar3 text-muted me-1"></i><?= date('d M Y', strtotime($r['transaction_date'])) ?></td>
<td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= htmlspecialchars($r['category'] ?? 'Umum') ?></span></td>
<td><small class="text-muted"><?= htmlspecialchars($r['partner_name'] ?? '-') ?></small></td>
<td>
<?php if ($r['project_id']): ?>
<div class="fw-bold text-dark"><?= htmlspecialchars($r['client_name'] ?? '') ?></div>
<small class="text-muted d-block"><?= htmlspecialchars($r['service_name'] ?? '') ?></small>
<?php else: ?>
<span class="text-muted fst-italic">- Internal -</span>
<?php endif; ?>
</td>
<td>
<div class="text-wrap" style="max-width: 300px;">
<?= nl2br(htmlspecialchars($display_note)) ?>
<?php if($is_cash_used): ?><div class="badge bg-warning text-dark mt-1"><i class="bi bi-exclamation-circle me-1"></i>Dana Terpakai</div><?php endif; ?>
</div>
</td>
<td class="text-end fw-bold text-danger"><?= formatRupiah($r['amount']) ?></td>
<td class="text-center pe-4">
<div class="btn-group">
<a href="mark_paid.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Tandai tagihan ini sudah dibayar LUNAS?')"><i class="bi bi-check-lg me-1"></i>Bayar</a>
<button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
<ul class="dropdown-menu dropdown-menu-end border-0">
<li><a class="dropdown-item text-warning" href="edit_transaction.php?id=<?= $r['id'] ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
<li><hr class="dropdown-divider"></li>
<?php if(!$is_cash_used): ?>
<li><a class="dropdown-item text-warning" href="mark_as_kasbon.php?id=<?= $r['id'] ?>" onclick="return confirm('Tandai sebagai Kasbon (Hutang Pribadi)?\n\nHutang vendor ini akan tetap ada, tapi tercatat uangnya Anda pakai sebagai KASBON (Hutang Owner).')"><i class="bi bi-person-exclamation me-2"></i>Jadikan Kasbon (Hutang)</a></li>
<li><a class="dropdown-item text-info" href="mark_as_company.php?id=<?= $r['id'] ?>" onclick="return confirm('Tandai sebagai Terpakai PT? Hutang vendor ini akan tetap ada, tapi tercatat uangnya dipakai Perusahaan.')"><i class="bi bi-building-exclamation me-2"></i>Pakai Dana PT</a></li>
<li><hr class="dropdown-divider"></li>
<?php else: ?>
<li><a class="dropdown-item text-success" href="unmark_used.php?id=<?= $r['id'] ?>" onclick="return confirm('Kembalikan status dana ini menjadi belum terpakai? (Tagihan ini akan kembali menjadi Hutang Vendor aktif)')"><i class="bi bi-arrow-counterclockwise me-2"></i>Kembalikan (Batal Pakai)</a></li>
<li><hr class="dropdown-divider"></li>
<?php endif; ?>
<li><a class="dropdown-item text-danger" href="delete_transaction.php?id=<?= $r['id'] ?>" onclick="return confirm('Hapus tagihan ini?')"><i class="bi bi-trash me-2"></i>Hapus</a></li>
</ul>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="7" class="text-center py-5 text-muted"><div class="mb-2"><i class="bi bi-check2-circle fs-1 text-success"></i></div>Tidak ada hutang vendor yang belum dibayar.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 