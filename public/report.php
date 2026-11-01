<?php
require __DIR__ . '/../config/db.php';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$partner_id = $_GET['partner_id'] ?? '';
$service_id = $_GET['service_id'] ?? '';
$start_date = "$year-$month-01";
$end_date = date("Y-m-t", strtotime($start_date));
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND transaction_date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$income = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("
    SELECT SUM(t.amount) 
    FROM transactions t
    JOIN projects p ON t.project_id = p.id
    WHERE t.type = 'expense' 
    AND t.category != 'Prive'
    AND p.service_name != 'KEUANGAN PERUSAHAAN'
    AND t.transaction_date BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$expense_project = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("
    SELECT SUM(t.amount) 
    FROM transactions t
    JOIN projects p ON t.project_id = p.id
    WHERE t.type = 'expense' 
    AND t.category != 'Prive'
    AND p.service_name = 'KEUANGAN PERUSAHAAN'
    AND t.transaction_date BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$expense_operational = $stmt->fetchColumn() ?: 0;
$total_expense = $expense_project + $expense_operational;
$net_profit = $income - $total_expense;
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND category = 'Prive' AND transaction_date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$prive = $stmt->fetchColumn() ?: 0;
$pageTitle = "Laporan Keuangan Bulanan";
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="row justify_content-center">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0 text-dark">Laporan Keuangan</h3>
                    <p class="text-muted mb-0 small">Ringkasan Laba Rugi Bulanan</p>
                </div>
            </div>
            <div class="card border-0 mb-4 bg-light">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end justify-content-center">
                        <div class="col-auto"><label class="form-label fw-bold">Pilih Periode:</label></div>
                        <div class="col-auto">
                            <select name="month" class="form-select">
                                <?php
                                for ($m=1; $m<=12; $m++) {
                                    $m_padded = str_pad($m, 2, '0', STR_PAD_LEFT);
                                    $month_name = date('F', mktime(0, 0, 0, $m, 10));
                                    $selected = ($m_padded == $month) ? 'selected' : '';
                                    echo "<option value='$m_padded' $selected>$month_name</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <select name="year" class="form-select">
                                <?php
                                $current_year = date('Y');
                                for ($y=$current_year; $y>=$current_year-5; $y--) {
                                    $selected = ($y == $year) ? 'selected' : '';
                                    echo "<option value='$y' $selected>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <?php $partners = $pdo->query("SELECT id, name FROM partners ORDER BY is_default DESC, name ASC")->fetchAll(); ?>
                            <select name="partner_id" class="form-select">
                                <option value="">-- Semua Mitra --</option>
                                <?php foreach($partners as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($partner_id == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <?php $services = $pdo->query("SELECT id, name FROM services ORDER BY name ASC")->fetchAll(); ?>
                            <select name="service_id" class="form-select">
                                <option value="">-- Semua Layanan --</option>
                                <?php foreach($services as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= ($service_id == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Tampilkan</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card border-0">
                <div class="card-header bg-white text-center py-3 border-bottom-0">
                    <h4 class="mb-0 fw-bold">Laporan Laba Rugi (Income Statement)</h4>
                    <small class="text-muted">Periode: <?= date("F Y", strtotime("$year-$month-01")) ?></small>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <h6 class="text-uppercase text-muted border-bottom pb-2">Pendapatan (Revenue)</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Pemasukan Project</span>
                            <span class="fw-bold text-success"><?= formatRupiah($income) ?></span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <h6 class="text-uppercase text-muted border-bottom pb-2">Biaya Langsung Proyek (HPP)</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Biaya Project (Notaris, Ongkir, dll)</span>
                            <span>(<?= formatRupiah($expense_project) ?>)</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between bg-light p-2 rounded mb-4">
                        <span class="fw-bold">Laba Kotor (Gross Profit)</span>
                        <span class="fw-bold"><?= formatRupiah($income - $expense_project) ?></span>
                    </div>
                    <div class="mb-4">
                        <h6 class="text-uppercase text-muted border-bottom pb-2">Biaya Operasional (Opex)</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Biaya Rutin (Gaji, Listrik, Sewa)</span>
                            <span>(<?= formatRupiah($expense_operational) ?>)</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between bg-primary text-white p-3 rounded mb-4">
                        <span class="h5 mb-0">Laba Bersih (Net Profit)</span>
                        <span class="h5 mb-0"><?= formatRupiah($net_profit) ?></span>
                    </div>
                    <div class="alert alert-warning">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Prive Owner (Diambil Bulan Ini)</span>
                            <span class="fw-bold text-danger"><?= formatRupiah($prive) ?></span>
                        </div>
                    </div>
                    <?php
                    $projConditions = ["p.client_name != 'INTERNAL'"];
                    $projParams = [$start_date, $end_date, $start_date, $end_date];
                    if ($partner_id !== '') {
                        $projConditions[] = "p.partner_id = ?";
                    }
                    if ($service_id !== '') {
                        $projConditions[] = "p.service_id = ?";
                    }
                    $whereProjects = count($projConditions) ? "WHERE " . implode(" AND ", $projConditions) : "";
                    $sqlProjects = "
                        SELECT p.*,
                          (SELECT SUM(t.amount) FROM transactions t WHERE t.project_id = p.id AND t.type='income' AND t.transaction_date BETWEEN ? AND ?) as income_period,
                          (SELECT SUM(t.amount) FROM transactions t WHERE t.project_id = p.id AND t.type='expense' AND t.transaction_date BETWEEN ? AND ? AND t.category NOT IN ('Prive','Company Withdrawal','Kasbon','Pengembalian Kasbon','Pengembalian Dana Perusahaan')) as expense_period
                        FROM projects p
                        $whereProjects
                        ORDER BY p.created_at DESC
                    ";
                    $stmtP = $pdo->prepare($sqlProjects);
                    $bindParams = $projParams;
                    if ($partner_id !== '') $bindParams[] = $partner_id;
                    if ($service_id !== '') $bindParams[] = $service_id;
                    $stmtP->execute($bindParams);
                    $projects = $stmtP->fetchAll();
                    ?>
                    <div class="mt-4">
                        <h6 class="text-uppercase text-muted border-bottom pb-2">Laba Rugi Per Proyek (Periode Ini)</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Klien</th>
                                        <th>Layanan</th>
                                        <th class="text-end">Pendapatan</th>
                                        <th class="text-end">Biaya</th>
                                        <th class="text-end">Komisi Mitra</th>
                                        <th class="text-end">Net Cash</th>
                                        <th class="text-end">Margin Setelah Komisi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sum_income = 0; $sum_expense = 0; $sum_comm = 0; $sum_net = 0; $sum_margin = 0;
                                    foreach($projects as $pRow):
                                        $inc = $pRow['income_period'] ?: 0;
                                        $exp = $pRow['expense_period'] ?: 0;
                                        $comm = $pRow['partner_commission'] ?: 0;
                                        $net = $inc - $exp;
                                        $margin = $net - $comm;
                                        $sum_income += $inc; $sum_expense += $exp; $sum_comm += $comm; $sum_net += $net; $sum_margin += $margin;
                                    ?>
                                    <tr>
                                        <td class="text-nowrap"><?= htmlspecialchars($pRow['client_name'] ?? '') ?></td>
                                        <td class="text-nowrap"><?= htmlspecialchars($pRow['service_name'] ?? '') ?></td>
                                        <td class="text-end text-success"><?= formatRupiah($inc) ?></td>
                                        <td class="text-end text-danger">-<?= formatRupiah($exp) ?></td>
                                        <td class="text-end text-danger">-<?= formatRupiah($comm) ?></td>
                                        <td class="text-end fw-bold"><?= formatRupiah($net) ?></td>
                                        <td class="text-end fw-bold <?= $margin >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatRupiah($margin) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light">
                                        <th colspan="2" class="text-end">Total</th>
                                        <th class="text-end"><?= formatRupiah($sum_income) ?></th>
                                        <th class="text-end">-<?= formatRupiah($sum_expense) ?></th>
                                        <th class="text-end">-<?= formatRupiah($sum_comm) ?></th>
                                        <th class="text-end"><?= formatRupiah($sum_net) ?></th>
                                        <th class="text-end"><?= formatRupiah($sum_margin) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-center bg-white">
                    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Cetak Laporan</button>
                    <div class="btn-group ms-2">
                        <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel (CSV)
                        </button>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">Laporan Utama</h6></li>
                            <li><a class="dropdown-item" href="export_data.php?type=summary&month=<?= $month ?>&year=<?= $year ?>">Ringkasan Laba Rugi</a></li>
                            <li><a class="dropdown-item" href="export_data.php?type=cashflow&month=<?= $month ?>&year=<?= $year ?>&partner_id=<?= urlencode($partner_id) ?>&service_id=<?= urlencode($service_id) ?>">Arus Kas (Cash Flow)</a></li>
                            <li><a class="dropdown-item" href="export_data.php?type=project_pl&month=<?= $month ?>&year=<?= $year ?>&partner_id=<?= urlencode($partner_id) ?>&service_id=<?= urlencode($service_id) ?>">Laba Rugi Per Proyek</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Data Transaksi</h6></li>
                            <li><a class="dropdown-item" href="export_data.php?type=transactions&month=<?= $month ?>&year=<?= $year ?>">Semua Data Transaksi</a></li>
                            <li><a class="dropdown-item" href="export_data.php?type=general_expenses&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">Pengeluaran Umum</a></li>
                            <li><a class="dropdown-item" href="export_data.php?type=payables&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&partner_id=<?= urlencode($partner_id) ?>">Hutang Vendor (Unpaid)</a></li>
                            <li><a class="dropdown-item" href="export_data.php?type=partner_payments&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&partner_id=<?= urlencode($partner_id) ?>">Pembayaran Mitra</a></li>
                            <li><a class="dropdown-item" href="export_data.php?type=owner_debt&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">Kasbon & Dana Owner</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Analisa & Master Data</h6></li>
                            <li><a class="dropdown-item" href="export_data.php?type=services">Analisa Profit Layanan</a></li>
                            <li><a class="dropdown-item" href="export_data.php?type=clients">Data Klien</a></li>
                            <li><a class="dropdown-item" href="export_data.php?type=partners">Data Mitra</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 