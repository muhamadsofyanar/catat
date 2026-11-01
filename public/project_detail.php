<?php
$root = '../';
require __DIR__ . '/../config/db.php';
use Modules\Projects\Models\Project;
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}
$projectModel = new Project();
$project = $projectModel->find($id);
if (!$project) {
    die("Proyek tidak ditemukan.");
}
$partnerName = 'Default';
$partnerIdForLink = null;
$p = $projectModel->getPartner($project['partner_id']);
if ($p) {
    $partnerName = $p['name'];
    $partnerIdForLink = $p['id'];
}
$fees = $projectModel->getFees($id);
$documents = $projectModel->getDocuments($id);
$docs_requirements = array_filter($documents, fn($d) => $d['category'] === 'requirement');
$docs_deliverables = array_filter($documents, fn($d) => $d['category'] === 'deliverable');
$docs_others = array_filter($documents, fn($d) => $d['category'] === 'other');
$transactions = $projectModel->getTransactions($id);
$incomes = array_filter($transactions, fn($t) => $t['type'] === 'income');
$expenses = array_filter($transactions, fn($t) => $t['type'] === 'expense' && !in_array($t['category'], ['Kasbon', 'Prive', 'Company Withdrawal']));
$total_income = array_sum(array_column($incomes, 'amount'));
$total_expense = array_sum(array_column($expenses, 'amount'));
$latest_income_id = !empty($incomes) ? reset($incomes)['id'] : null;
$partner_commission = $project['partner_commission'] ?? 0;
$remaining_bill = $project['total_price'] - $total_income;
$net_profit = $total_income - $total_expense;
$expense_by_cat = [];
foreach ($expenses as $e) {
    $cat = $e['category'] ?: 'Lainnya';
    $expense_by_cat[$cat] = ($expense_by_cat[$cat] ?? 0) + $e['amount'];
}
$projected_net_after_commission = $net_profit - $partner_commission;
$standard_costs = $projectModel->getServiceStandardCosts($project['service_id']);
$total_standard_cost = 0;
foreach($standard_costs as $sc) $total_standard_cost += $sc['amount'];
$lastReminder = null;
try {
    $stmtLR = $pdo->prepare("SELECT action, description, created_at FROM activity_logs WHERE (action='REMINDER_WA' OR action='REMINDER_EMAIL') AND description LIKE ? ORDER BY created_at DESC LIMIT 1");
    $stmtLR->execute(['PID: ' . $project['id'] . '%']);
    $lastReminder = $stmtLR->fetch();
} catch (Exception $e) {}
$pageTitle = "Detail: " . $project['client_name'] . " - LegaVoice";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Detail Proyek</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0">
            <a href="client_detail.php?id=<?= $project['client_id'] ?>" class="text-dark text-decoration-none"><?= htmlspecialchars($project['client_name'] ?? '') ?></a>
        </h3>
        <p class="text-muted mb-0"><?= htmlspecialchars($project['service_name'] ?? '') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="print_invoice.php?id=<?= $project['id'] ?>" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer me-2"></i>Invoice</a>
        <?php if($latest_income_id): ?>
            <a href="print_receipt.php?id=<?= $latest_income_id ?>" target="_blank" class="btn btn-outline-success"><i class="bi bi-receipt me-2"></i>Kwitansi</a>
        <?php else: ?>
             <button class="btn btn-outline-success disabled" title="Belum ada pembayaran"><i class="bi bi-receipt me-2"></i>Kwitansi</button>
        <?php endif; ?>
        <a href="print_bast.php?id=<?= $project['id'] ?>" target="_blank" class="btn btn-outline-info"><i class="bi bi-file-earmark-text me-2"></i>BAST</a>
        <a href="export_data.php?type=project_statement&project_id=<?= $project['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Statement</a>
        <div class="dropdown">
            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-gear me-2"></i>Aksi
            </button>
            <ul class="dropdown-menu dropdown-menu-end border-0">
                <?php if($project['status'] !== 'completed'): ?>
                    <li><a class="dropdown-item text-success" href="update_project_status.php?id=<?= $project['id'] ?>&status=completed" onclick="return confirm('Tandai proyek ini sebagai Selesai?')"><i class="bi bi-check-circle me-2"></i>Tandai Selesai</a></li>
                <?php else: ?>
                    <li><a class="dropdown-item text-warning" href="update_project_status.php?id=<?= $project['id'] ?>&status=ongoing" onclick="return confirm('Buka kembali proyek ini?')"><i class="bi bi-arrow-counterclockwise me-2"></i>Buka Kembali</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="project_form.php?id=<?= $project['id'] ?>"><i class="bi bi-pencil me-2"></i>Edit Proyek</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="delete_project.php?id=<?= $project['id'] ?>" onclick="return confirm('Yakin hapus proyek ini?')"><i class="bi bi-trash me-2"></i>Hapus Proyek</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="text-uppercase fw-bold text-muted small mb-3">Status Proyek</h6>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <?php
                    $statusBadge = 'bg-secondary';
                    $statusLabel = $project['status'];
                    if ($project['status'] == 'ongoing') { $statusBadge = 'bg-primary'; $statusLabel = 'Berjalan'; }
                    elseif ($project['status'] == 'completed') { $statusBadge = 'bg-success'; $statusLabel = 'Selesai'; }
                    elseif ($project['status'] == 'cancelled') { $statusBadge = 'bg-danger'; $statusLabel = 'Batal'; }
                    ?>
                    <span class="badge <?= $statusBadge ?> fs-6"><?= ucfirst($statusLabel) ?></span>
                    <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($project['created_at'])) ?></span>
                </div>
                <hr class="my-3">
                <div class="mb-3">
                    <?php if(!empty($project['original_price']) && $project['original_price'] > $project['total_price']): ?>
                        <small class="text-muted d-block">Harga Asli</small>
                        <span class="text-decoration-line-through text-muted"><?= formatRupiah($project['original_price']) ?></span>
                        <?php if(!empty($project['discount']) && $project['discount'] > 0): ?>
                            <small class="text-muted d-block mt-1">Diskon</small>
                            <span class="text-danger">-<?= formatRupiah($project['discount']) ?></span>
                        <?php endif; ?>
                        <small class="text-muted d-block mt-2">Total Nilai Proyek (Net)</small>
                    <?php else: ?>
                        <small class="text-muted d-block">Total Nilai Proyek</small>
                    <?php endif; ?>
                    <h4 class="fw-bold text-dark"><?= formatRupiah($project['total_price']) ?></h4>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Sisa Tagihan (Net)</small>
                    <h5 class="fw-bold <?= $remaining_bill > 0 ? 'text-danger' : 'text-success' ?>"><?= formatRupiah($remaining_bill) ?></h5>
                    <?php if($partner_commission > 0): ?>
                        <small class="text-info d-block"><i class="bi bi-info-circle me-1"></i>Dikurangi Komisi Mitra: <?= formatRupiah($partner_commission) ?></small>
                    <?php endif; ?>
                </div>
                <div class="mb-0">
                    <small class="text-muted d-block">Mitra Pelaksana</small>
                    <?php if($partnerIdForLink): ?>
                        <a href="partner_detail.php?id=<?= $partnerIdForLink ?>" class="fw-bold text-dark text-decoration-none"><?= htmlspecialchars($partnerName) ?></a>
                    <?php else: ?>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($partnerName) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header bg-white py-3 border-bottom-0">
                <ul class="nav nav-tabs card-header-tabs" id="profitTabs" role="tablist">
                    <li class="nav-item" role="presentation"><button class="nav-link active fw-bold" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab" aria-selected="true">Ringkasan</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link fw-bold" id="chart-tab" data-bs-toggle="tab" data-bs-target="#chart" type="button" role="tab" aria-selected="false">Visualisasi</button></li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="profitTabsContent">
                    <div class="tab-pane fade show active" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Pemasukan</span><span class="text-success fw-bold"><?= formatRupiah($total_income) ?></span></div>
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Pengeluaran</span><span class="text-danger fw-bold">-<?= formatRupiah($total_expense) ?></span></div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center"><span class="fw-bold text-dark">Net Profit (Cash)</span><span class="h5 mb-0 fw-bold <?= $net_profit >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatRupiah($net_profit) ?></span></div>
                        <div class="d-flex justify-content-between mt-2"><span class="text-muted">Komisi Mitra (Kontraktual)</span><span class="fw-bold text-danger">-<?= formatRupiah($partner_commission) ?></span></div>
                        <div class="d-flex justify-content-between"><span class="fw-bold text-dark">Margin Setelah Komisi</span><span class="fw-bold <?= $projected_net_after_commission >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatRupiah($projected_net_after_commission) ?></span></div>
                        <hr>
                        <div class="mt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted d-block small">Sisa Tagihan</span>
                                    <strong class="<?= $remaining_bill > 0 ? 'text-danger' : 'text-success' ?>"><?= formatRupiah($remaining_bill) ?></strong>
                                    <?php if(!empty($project['due_date'])): ?>
                                        <div class="small text-muted mt-1">Jatuh Tempo: <?= date('d M Y', strtotime($project['due_date'])) ?></div>
                                    <?php endif; ?>
                                    <?php if($lastReminder): ?>
                                        <div class="small text-muted mt-1">Terakhir diingatkan: <?= date('d M Y H:i', strtotime($lastReminder['created_at'])) ?> (<?= $lastReminder['action'] === 'REMINDER_WA' ? 'WA' : 'Email' ?>)</div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="send_reminder.php?project_id=<?= $project['id'] ?>&channel=wa" class="btn btn-sm btn-success"><i class="bi bi-whatsapp me-1"></i> WA</a>
                                    <a href="send_reminder.php?project_id=<?= $project['id'] ?>&channel=email" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope me-1"></i> Email</a>
                                    <?php if(!empty($project['payment_link'])): ?>
                                        <a href="<?= htmlspecialchars($project['payment_link'] ?? '') ?>" target="_blank" rel="noreferrer" class="btn btn-sm btn-outline-primary"><i class="bi bi-credit-card me-1"></i> Buka Payment</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if(!empty($project['payment_link'])): ?>
                        <div class="mt-2">
                            <div class="small">
                                <span class="text-muted">Payment Link:</span>
                                <a href="<?= htmlspecialchars($project['payment_link'] ?? '') ?>" target="_blank" class="text-decoration-none"><?= htmlspecialchars($project['payment_link'] ?? '') ?></a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if(count($expense_by_cat) > 0): ?>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th class="text-muted">Rincian Biaya</th><th class="text-end">Jumlah</th></tr></thead>
                                <tbody>
                                    <?php foreach($expense_by_cat as $cat => $sum): ?>
                                    <tr><td><?= htmlspecialchars($cat) ?></td><td class="text-end text-danger">-<?= formatRupiah($sum) ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        <?php if($total_standard_cost > 0): ?>
                            <div class="alert alert-info mt-3 mb-0 py-2 small"><i class="bi bi-info-circle me-1"></i>Estimasi Biaya Standar: <strong><?= formatRupiah($total_standard_cost) ?></strong></div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade" id="chart" role="tabpanel" aria-labelledby="chart-tab">
                        <div class="mb-4"><h6 class="small text-muted fw-bold text-uppercase mb-3">Pemasukan vs Pengeluaran</h6><canvas id="financeChart" style="max-height: 250px; width: 100%;"></canvas></div>
                        <hr>
                        <div><h6 class="small text-muted fw-bold text-uppercase mb-3">Distribusi Biaya</h6><?php if(empty($expense_by_cat)): ?><p class="text-muted small text-center py-4">Belum ada pengeluaran tercatat.</p><?php else: ?><canvas id="expenseChart" style="max-height: 250px; width: 100%;"></canvas><?php endif; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center"><span>Dokumen</span><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-upload"></i></button></div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if(empty($documents)): ?>
                        <div class="list-group-item text-muted text-center py-3">Belum ada dokumen</div>
                    <?php else: ?>
                        <?php foreach($documents as $doc): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div class="text-truncate me-2">
                                        <a href="download_document.php?id=<?= (int)$doc['id'] ?>" target="_blank" class="text-decoration-none fw-bold text-dark"><i class="bi bi-file-earmark-text me-2"></i><?= htmlspecialchars($doc['file_name'] ?? '') ?></a>
                                        <div class="small text-muted"><?= ucfirst($doc['category']) ?> • <?= date('d M Y', strtotime($doc['uploaded_at'])) ?></div>
                                    </div>
                                    <a href="delete_document.php?id=<?= $doc['id'] ?>&project_id=<?= $id ?>" class="text-danger" onclick="return confirm('Hapus dokumen?')"><i class="bi bi-trash"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 mb-4">
            <div class="card-header bg-white fw-bold py-3">Catat Transaksi Baru</div>
            <div class="card-body">
                <form action="save_transaction.php" method="POST">
                    <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label small text-muted">Tipe</label><select name="type" class="form-select" required id="transType"><option value="expense">Pengeluaran</option><option value="income">Pemasukan</option></select></div>
                        <div class="col-md-3"><label class="form-label small text-muted">Kategori</label><input type="text" name="category" class="form-control" list="catList" placeholder="Contoh: Transport"><datalist id="catList"><option value="DP Klien"><option value="Pelunasan Klien"><option value="Biaya Notaris"><option value="Transport"><option value="Kasbon"></datalist></div>
                        <div class="col-md-3"><label class="form-label small text-muted">Status</label><select name="status" class="form-select" id="transStatus"><option value="paid">Lunas (Bayar Langsung)</option><option value="unpaid">Belum Lunas (Hutang)</option></select></div>
                        <div class="col-md-3"><label class="form-label small text-muted">Jumlah (Rp)</label><input type="number" name="amount" class="form-control" required min="0"></div>
                        <div class="col-md-3"><label class="form-label small text-muted">Tanggal</label><input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="col-12"><label class="form-label small text-muted">Catatan</label><textarea name="note" class="form-control" rows="2" placeholder="Detail transaksi..."></textarea></div>
                        <div class="col-12 text-end"><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Transaksi</button></div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0">
            <div class="card-header bg-white fw-bold py-3 border-bottom-0 d-flex justify-content-between align-items-center"><span>Riwayat Transaksi</span><a href="export_data.php?type=project_statement&project_id=<?= $project['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th class="ps-3">Tanggal</th><th>Tipe</th><th>Kategori</th><th>Catatan</th><th class="text-end">Jumlah</th><th class="text-end pe-3">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="ps-3"><?= date('d/m/y', strtotime($t['transaction_date'])) ?></td>
                                <td><?php if($t['type'] == 'income'): ?><span class="badge bg-success-subtle text-success">Masuk</span><?php else: ?><span class="badge bg-danger-subtle text-danger">Keluar</span><?php endif; ?></td>
                                <td><?= htmlspecialchars($t['category'] ?? '') ?></td>
                                <td><?= htmlspecialchars($t['note'] ?? '') ?><?php if($t['status'] == 'unpaid'): ?><span class="badge bg-warning text-dark ms-1">Belum Lunas</span><?php endif; ?></td>
                                <td class="text-end fw-bold"><?= formatRupiah($t['amount']) ?></td>
                                <td class="text-end pe-3">
                                    <?php if($t['type'] == 'expense' && $t['status'] == 'unpaid'): ?>
                                        <a href="mark_paid.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-success border-0 me-1" onclick="return confirm('Tandai tagihan ini sudah dibayar LUNAS?')"><i class="bi bi-check-lg me-1"></i>Bayar</a>
                                    <?php endif; ?>
                                    <?php if($t['type'] == 'income'): ?>
                                        <a href="print_receipt.php?id=<?= $t['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success border-0 me-1" title="Cetak Kwitansi"><i class="bi bi-receipt"></i></a>
                                    <?php endif; ?>
                                    <a href="edit_transaction.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-warning border-0 me-1" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="delete_transaction.php?id=<?= $t['id'] ?>&project_id=<?= $id ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Hapus transaksi ini?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($transactions)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada transaksi</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctxFinance = document.getElementById('financeChart').getContext('2d');
    const totalIncome = <?= $total_income ?>;
    const totalExpense = <?= $total_expense ?>;
    const standardCost = <?= $total_standard_cost ?>;
    new Chart(ctxFinance, {
        type: 'bar',
        data: {
            labels: ['Estimasi Biaya', 'Realisasi Biaya', 'Pemasukan'],
            datasets: [{
                label: 'Jumlah (Rp)',
                data: [standardCost, totalExpense, totalIncome],
                backgroundColor: ['rgba(13, 202, 240, 0.6)','rgba(220, 53, 69, 0.6)','rgba(25, 135, 84, 0.6)'],
                borderColor: ['rgba(13, 202, 240, 1)','rgba(220, 53, 69, 1)','rgba(25, 135, 84, 1)'],
                borderWidth: 1, borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) {
                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.raw);
            }}}},
            scales: { y: { beginAtZero: true, ticks: { callback: function(value) {
                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumSignificantDigits: 2 }).format(value);
            }}}}
        }
    });
    const expenseData = <?= json_encode($expense_by_cat) ?>;
    if (Object.keys(expenseData).length > 0) {
        const ctxExpense = document.getElementById('expenseChart').getContext('2d');
        const labels = Object.keys(expenseData);
        const values = Object.values(expenseData);
        const colors = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69','#2e59d9','#17a673','#2c9faf'];
        new Chart(ctxExpense, { type: 'doughnut', data: { labels, datasets: [{ data: values, backgroundColor: colors.slice(0, labels.length), hoverBackgroundColor: colors.slice(0, labels.length), hoverBorderColor: "rgba(234, 236, 244, 1)" }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } }, tooltip: { callbacks: { label: function(context) { let label = context.label || ''; if (label) { label += ': '; } label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.raw); return label; }}}}, cutout: '70%', } });
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('transType');
    const statusSelect = document.getElementById('transStatus');
    function updateStatusVisibility() {
        if (typeSelect.value === 'income') {
            statusSelect.value = 'paid';
            statusSelect.closest('.col-md-3').style.display = 'none';
        } else {
            statusSelect.closest('.col-md-3').style.display = 'block';
        }
    }
    typeSelect.addEventListener('change', updateStatusVisibility);
    updateStatusVisibility();
});
</script>
