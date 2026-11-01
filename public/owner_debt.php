<?php
$root = '../';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
use Modules\Finance\Models\Transaction;
$transactionModel = new Transaction();
$kasbon_out = $transactionModel->getSum(['type' => 'expense', 'category' => 'Kasbon']);
$kasbon_in  = $transactionModel->getSum(['type' => 'income', 'category' => 'Pengembalian Kasbon']);
$total_kasbon = $kasbon_out - $kasbon_in;
$company_out = $transactionModel->getSum(['type' => 'expense', 'category' => 'Company Withdrawal']);
$company_in  = $transactionModel->getSum(['type' => 'income', 'category' => 'Pengembalian Dana Perusahaan']);
$total_company_debt = $company_out - $company_in;
$total_company_usage = $company_out;
$total_income = $transactionModel->getSum([
    'type' => 'income',
    'category_not_in' => ['Pengembalian Kasbon', 'Pengembalian Dana Perusahaan'],
    'include_null_category' => true
]);
$total_expense_operational = $transactionModel->getSum([
    'type' => 'expense',
    'category_not_in' => ['Prive', 'Company Withdrawal', 'Kasbon', 'Operasional Kantor'],
    'include_null_category' => true
]);
$net_profit = $total_income - $total_expense_operational;
$share_company = ($net_profit > 0) ? $net_profit * 0.40 : 0;
$share_owner = ($net_profit > 0) ? $net_profit * 0.60 : 0;
$total_prive = $transactionModel->getSum(['type' => 'expense', 'category' => 'Prive']);
$overdraft_prive = max(0, $total_prive - $share_owner);
$total_debt = $total_kasbon + $total_company_debt + $overdraft_prive;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$kasbon_logs = $transactionModel->getAll([
    'category_in' => ['Kasbon', 'Pengembalian Kasbon'],
    'start_date' => $start_date,
    'end_date' => $end_date,
    'q' => $q,
    'order' => 'DESC'
]);
$company_logs = $transactionModel->getAll([
    'category_in' => ['Company Withdrawal', 'Pengembalian Dana Perusahaan'],
    'start_date' => $start_date,
    'end_date' => $end_date,
    'q' => $q,
    'order' => 'DESC'
]);
$pageTitle = "Hutang Owner - LegaVoice";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-person-exclamation text-info me-2"></i>Hutang Owner (Pribadi)</h3>
        <p class="text-muted mb-0">Catatan penggunaan uang perusahaan/vendor untuk keperluan pribadi.</p>
    </div>
    <div>
        <a href="export_data.php?type=owner_debt&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&q=<?= urlencode($q) ?>" class="btn btn-outline-success me-2">
            <i class="bi bi-file-earmark-excel me-1"></i>Export CSV
        </a>
        <a href="repayment_form.php" class="btn btn-success fw-bold">
            <i class="bi bi-cash-coin me-2"></i>Kembalikan Dana
        </a>
    </div>
 </div>
<div class="card border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Cari Catatan</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Keterangan..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
                <a href="owner_debt.php" class="btn btn-sm btn-outline-secondary w-100 mt-1">Reset</a>
            </div>
        </form>
    </div>
 </div>
<div class="row mb-4 g-4">
    <div class="col-md-3">
        <div class="card bg-danger text-white h-100 border-0" style="background: linear-gradient(135deg, #ef4444, #b91c1c);">
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge bg-white bg-opacity-25 border border-white border-opacity-25">Total Kewajiban</span>
                </div>
                <h2 class="card-title fw-bold display-6 mb-1"><?= formatRupiah($total_debt) ?></h2>
                <p class="card-text opacity-75 small">Total uang yang harus dikembalikan ke sistem.</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="card-title fw-bold text-dark mb-0">Kasbon Vendor</h5>
                        <small class="text-muted">Uang vendor terpakai</small>
                    </div>
                    <i class="bi bi-exclamation-triangle text-warning fs-3"></i>
                </div>
                <h3 class="fw-bold text-warning mb-3"><?= formatRupiah($total_kasbon) ?></h3>
                <div class="bg-light rounded p-2 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Terpakai:</span>
                        <span class="text-danger fw-bold"><?= formatRupiah($kasbon_out) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Dikembalikan:</span>
                        <span class="text-success fw-bold"><?= formatRupiah($kasbon_in) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 h-100 border-start border-4 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="card-title fw-bold text-dark mb-0">Dana Perusahaan</h5>
                        <small class="text-muted">Hak PT diambil Pribadi</small>
                    </div>
                    <i class="bi bi-building text-primary fs-3"></i>
                </div>
                <h3 class="fw-bold text-primary mb-3"><?= formatRupiah($total_company_debt) ?></h3>
                <div class="bg-light rounded p-2 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total Pakai:</span>
                        <span class="text-danger fw-bold"><?= formatRupiah($total_company_usage) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Hak PT:</span>
                        <span class="text-success fw-bold"><?= formatRupiah($share_company) ?></span>
                    </div>
                    <?php if($total_company_debt > 0): ?>
                    <div class="d-flex justify-content-between border-top border-secondary pt-1 mt-1">
                        <span class="text-dark fw-bold">Total Hutang:</span>
                        <span class="text-danger fw-bold"><?= formatRupiah($total_company_debt) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="card-title fw-bold text-dark mb-0">Overdraft Prive</h5>
                        <small class="text-muted">Prive Berlebih</small>
                    </div>
                    <i class="bi bi-graph-down-arrow text-info fs-3"></i>
                </div>
                <h3 class="fw-bold text-info mb-3"><?= formatRupiah($overdraft_prive) ?></h3>
                <div class="bg-light rounded p-2 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total Prive:</span>
                        <span class="text-danger fw-bold"><?= formatRupiah($total_prive) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Sisa Jatah:</span>
                        <span class="text-success fw-bold"><?= formatRupiah(max(0, $share_owner - $total_prive)) ?></span>
                    </div>
                    <div class="d-flex justify-content-between border-top border-secondary pt-1 mt-1">
                        <span class="text-dark fw-bold">Lebih Bayar:</span>
                        <span class="text-danger fw-bold"><?= formatRupiah($overdraft_prive) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold py-3 text-warning-emphasis border-bottom">
                <i class="bi bi-clock-history me-2"></i>Riwayat Kasbon (Vendor)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover table-striped mb-0 small">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3">Tanggal</th>
                                <th>Keterangan</th>
                                <th class="text-end pe-3">Jumlah</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kasbon_logs as $log):
                                $is_return = $log['type'] === 'income';
                            ?>
                            <tr>
                                <td class="ps-3"><?= date('d/m/y', strtotime($log['transaction_date'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($log['note'] ?? '') ?>
                                    <?php if($is_return): ?>
                                        <span class="badge bg-success-subtle text-success ms-1">Pengembalian</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger ms-1">Hutang</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3 fw-bold <?= $is_return ? 'text-success' : 'text-danger' ?>">
                                    <?= $is_return ? '+' : '-' ?><?= formatRupiah($log['amount']) ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="edit_transaction.php?id=<?= $log['id'] ?>" class="btn btn-sm btn-link text-warning p-0 me-2" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="delete_transaction.php?id=<?= $log['id'] ?>" class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('Hapus data ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($kasbon_logs)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada riwayat</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 h-100">
            <div class="card-header bg-white fw-bold py-3 text-primary-emphasis border-bottom-0">
                <i class="bi bi-clock-history me-2"></i>Riwayat Dana Perusahaan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover table-striped mb-0 small">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3">Tanggal</th>
                                <th>Keterangan</th>
                                <th class="text-end pe-3">Jumlah</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($company_logs as $log):
                                $is_return = $log['type'] === 'income';
                            ?>
                            <tr>
                                <td class="ps-3"><?= date('d/m/y', strtotime($log['transaction_date'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($log['note'] ?? '') ?>
                                    <?php if($is_return): ?>
                                        <span class="badge bg-success-subtle text-success ms-1">Pengembalian</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger ms-1">Hutang</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3 fw-bold <?= $is_return ? 'text-success' : 'text-danger' ?>">
                                    <?= $is_return ? '+' : '-' ?><?= formatRupiah($log['amount']) ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="edit_transaction.php?id=<?= $log['id'] ?>" class="btn btn-sm btn-link text-warning p-0 me-2" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="delete_transaction.php?id=<?= $log['id'] ?>" class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('Hapus data ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($company_logs)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada riwayat</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 