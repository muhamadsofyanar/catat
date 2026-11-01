<?php
$root = '../';
require __DIR__ . '/../config/db.php';

// Fetch Summary Data
// Filters
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$show_internal = isset($_GET['show_internal']) && $_GET['show_internal'] === '1';
$only_unpaid = isset($_GET['only_unpaid']) && $_GET['only_unpaid'] === '1';

// Build WHERE conditions for projects listing and count
$conditions = [];
$params = [];
if (!$show_internal) {
    $conditions[] = "client_name != 'INTERNAL'";
}
if ($q !== '') {
    $conditions[] = "(client_name LIKE ? OR service_name LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Count projects according to filters
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE company_id = ? " . ($whereSql ? ' AND ' . preg_replace('/^WHERE\s+/','', $whereSql) : ''));
$stmtCount->execute(array_merge([currentCompanyId()], $params));
$total_projects = $stmtCount->fetchColumn();

// Financial calculations and summaries
$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type = 'income' AND (category IS NULL OR category NOT IN ('Pengembalian Kasbon', 'Pengembalian Dana Perusahaan'))");
$stmtTmp->execute([currentCompanyId()]);
$total_income = $stmtTmp->fetchColumn() ?: 0;
$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type = 'expense' AND category = 'Prive'");
$stmtTmp->execute([currentCompanyId()]);
$total_prive = $stmtTmp->fetchColumn() ?: 0;
$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type = 'expense' AND category = 'Company Withdrawal'");
$stmtTmp->execute([currentCompanyId()]);
$total_company_withdrawal = $stmtTmp->fetchColumn() ?: 0;

$stmt_internal = $pdo->prepare("SELECT id FROM projects WHERE company_id = ? AND client_name = 'INTERNAL' AND service_name = 'KEUANGAN PERUSAHAAN'");
$stmt_internal->execute([currentCompanyId()]);
$internal_project = $stmt_internal->fetch();
$internal_pid = $internal_project ? $internal_project['id'] : 0;

$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type = 'expense' AND project_id != ? AND (category NOT IN ('Prive', 'Company Withdrawal', 'Company Expense', 'Kasbon') OR category IS NULL)");
$stmtTmp->execute([currentCompanyId(), $internal_pid]);
$total_expense_operational = $stmtTmp->fetchColumn() ?: 0;
$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type = 'expense' AND project_id = ? AND category NOT IN ('Prive', 'Company Withdrawal', 'Company Expense', 'Kasbon')");
$stmtTmp->execute([currentCompanyId(), $internal_pid]);
$total_ops_kantor = $stmtTmp->fetchColumn() ?: 0;
$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type = 'expense' AND (status IS NULL OR status = 'paid')");
$stmtTmp->execute([currentCompanyId()]);
$total_expense_paid = $stmtTmp->fetchColumn() ?: 0;

$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND category = 'Kasbon' AND type='expense'");
$stmtTmp->execute([currentCompanyId()]);
$total_kasbon_out = $stmtTmp->fetchColumn() ?: 0;
$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND category = 'Pengembalian Kasbon' AND type='income'");
$stmtTmp->execute([currentCompanyId()]);
$total_kasbon_in = $stmtTmp->fetchColumn() ?: 0;
$total_kasbon = $total_kasbon_out - $total_kasbon_in;

$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND category = 'Company Withdrawal' AND type='expense'");
$stmtTmp->execute([currentCompanyId()]);
$total_company_debt_out = $stmtTmp->fetchColumn() ?: 0;
$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND category = 'Pengembalian Dana Perusahaan' AND type='income'");
$stmtTmp->execute([currentCompanyId()]);
$total_company_in = $stmtTmp->fetchColumn() ?: 0;
$total_company_debt_net = $total_company_debt_out - $total_company_in;
$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND category = 'Company Expense' AND type='expense'");
$stmtTmp->execute([currentCompanyId()]);
$total_company_expense = $stmtTmp->fetchColumn() ?: 0;
$total_company_usage_net = $total_company_debt_net + $total_company_expense;
$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type = 'income' AND category IN ('Pengembalian Kasbon', 'Pengembalian Dana Perusahaan')");
$stmtTmp->execute([currentCompanyId()]);
$total_repayment = $stmtTmp->fetchColumn() ?: 0;
$cash_balance = ($total_income + $total_repayment) - $total_expense_paid;
$net_profit = $total_income - $total_expense_operational - $total_ops_kantor;
$share_company = ($net_profit > 0) ? $net_profit * 0.40 : 0;
$company_available = $share_company - $total_company_usage_net;
$target_company_balance = 100000000;
$company_progress_percent = ($company_available > 0) ? ($company_available / $target_company_balance) * 100 : 0;
$company_progress_percent = min(100, max(0, $company_progress_percent));
$share_owner = ($net_profit > 0) ? $net_profit * 0.60 : 0;
$owner_available = $share_owner - $total_prive;

$current_month = date('m');
$current_year = date('Y');
$start_date_m = "$current_year-$current_month-01";
$end_date_m = date("Y-m-t", strtotime($start_date_m));

$income_this_month = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?");
$income_this_month->execute([currentCompanyId(), $start_date_m, $end_date_m]);
$income_this_month = $income_this_month->fetchColumn() ?: 0;

$expense_this_month = $pdo->prepare("
    SELECT SUM(t.amount) 
    FROM transactions t
    WHERE t.company_id = ? AND t.type = 'expense' 
    AND t.category NOT IN ('Prive', 'Company Withdrawal', 'Company Expense', 'Kasbon', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan')
    AND t.transaction_date BETWEEN ? AND ?
");
$expense_this_month->execute([currentCompanyId(), $start_date_m, $end_date_m]);
$expense_this_month = $expense_this_month->fetchColumn() ?: 0;
$net_this_month = $income_this_month - $expense_this_month;

$stmtTopExp = $pdo->prepare("
    SELECT COALESCE(category, 'Lainnya') as category, SUM(amount) as total
    FROM transactions
    WHERE type = 'expense'
      AND category NOT IN ('Prive','Company Withdrawal','Company Expense','Kasbon','Pengembalian Kasbon','Pengembalian Dana Perusahaan')
      AND transaction_date BETWEEN ? AND ?
    GROUP BY COALESCE(category, 'Lainnya')
    ORDER BY total DESC
    LIMIT 5
");
$stmtTopExp->execute([$start_date_m, $end_date_m]);
$top_expenses = $stmtTopExp->fetchAll();

$trend = [];
$max_abs = 0;
for ($i = 0; $i < 12; $i++) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime($month_start));
    $stmtInc = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type='income' AND transaction_date BETWEEN ? AND ?");
    $stmtInc->execute([currentCompanyId(), $month_start, $month_end]);
    $inc = $stmtInc->fetchColumn() ?: 0;
    $stmtExp = $pdo->prepare("
        SELECT SUM(amount) FROM transactions 
        WHERE company_id = ? AND type='expense' 
          AND category NOT IN ('Prive','Company Withdrawal','Company Expense','Kasbon','Pengembalian Kasbon','Pengembalian Dana Perusahaan')
          AND transaction_date BETWEEN ? AND ?
    ");
    $stmtExp->execute([currentCompanyId(), $month_start, $month_end]);
    $exp = $stmtExp->fetchColumn() ?: 0;
    $net = $inc - $exp;
    $max_abs = max($max_abs, abs($net));
    $trend[] = [
        'label' => date('M y', strtotime($month_start)),
        'net' => $net,
        'income' => $inc,
        'expense' => $exp
    ];
}
$trend = array_reverse($trend);

$total_kasbon = $pdo->query("SELECT SUM(amount) FROM transactions WHERE category = 'Kasbon' AND type='expense'")->fetchColumn() ?: 0;
$share_owner_calc = ($net_profit > 0) ? $net_profit * 0.60 : 0;
$overdraft_prive = max(0, $total_prive - $share_owner_calc);
$total_hutang_owner_display = $total_kasbon + $total_company_debt_net + $overdraft_prive;

$stmtReceivable = $pdo->prepare("
    SELECT SUM(p.total_price - COALESCE(paid_sum.paid, 0)) 
    FROM projects p
    LEFT JOIN (
        SELECT project_id, SUM(amount) as paid
        FROM transactions
        WHERE type = 'income'
        GROUP BY project_id
    ) paid_sum ON p.id = paid_sum.project_id
    WHERE p.company_id = ? AND p.client_name != 'INTERNAL' 
    AND (p.total_price - COALESCE(paid_sum.paid, 0)) > 0
");
$stmtReceivable->execute([currentCompanyId()]);
$total_receivable = $stmtReceivable->fetchColumn() ?: 0;

$aging_receivable = [
    'current' => 0,
    'no_due' => 0,
    'over_0_30' => 0,
    'over_31_60' => 0,
    'over_61_90' => 0,
    'over_90_plus' => 0
];
$stmtAging = $pdo->prepare("
    SELECT p.*, 
    (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income') AS paid
    FROM projects p
    WHERE p.company_id = ? AND p.client_name != 'INTERNAL'
");
$stmtAging->execute([currentCompanyId()]);
$rowsAging = $stmtAging->fetchAll();
foreach ($rowsAging as $p) {
    $paid = $p['paid'] ?? 0;
    $remaining = ($p['total_price'] - $paid);
    if ($remaining > 0) {
        if (empty($p['due_date'])) {
            $aging_receivable['no_due'] += $remaining;
        } else {
            $days = (int)floor((time() - strtotime($p['due_date'])) / 86400);
            if ($days <= 0) {
                $aging_receivable['current'] += $remaining;
            } elseif ($days <= 30) {
                $aging_receivable['over_0_30'] += $remaining;
            } elseif ($days <= 60) {
                $aging_receivable['over_31_60'] += $remaining;
            } elseif ($days <= 90) {
                $aging_receivable['over_61_90'] += $remaining;
            } else {
                $aging_receivable['over_90_plus'] += $remaining;
            }
        }
    }
}
$stmtTopClients = $pdo->prepare("
    SELECT p.client_id, p.client_name, SUM(t.amount) as total_revenue
    FROM transactions t
    JOIN projects p ON t.project_id = p.id
    WHERE t.company_id = ? AND t.type = 'income'
    GROUP BY p.client_id, p.client_name
    ORDER BY total_revenue DESC
    LIMIT 5
");
$stmtTopClients->execute([currentCompanyId()]);
$top_clients = $stmtTopClients->fetchAll();

$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type = 'expense' AND status = 'unpaid'");
$stmtTmp->execute([currentCompanyId()]);
$total_payable = $stmtTmp->fetchColumn() ?: 0;
$stmtTmp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE company_id = ? AND type = 'expense' AND status = 'unpaid' AND note LIKE '%[UANG TERPAKAI]%'");
$stmtTmp->execute([currentCompanyId()]);
$total_payable_used = $stmtTmp->fetchColumn() ?: 0;
$total_payable_pure = $total_payable - $total_payable_used;

$stmt = $pdo->prepare("
    SELECT p.*, 
    (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income') as paid,
    (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'expense' AND category NOT IN ('Kasbon', 'Prive', 'Company Withdrawal', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan', 'Operasional Kantor')) as cost,
    (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'expense' AND status = 'unpaid' AND category NOT IN ('Kasbon', 'Prive', 'Company Withdrawal', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan', 'Operasional Kantor')) as unpaid_vendor
    FROM projects p 
    WHERE p.company_id = ? " . ($whereSql ? ' AND ' . preg_replace('/^WHERE\s+/','', $whereSql) : '') . "
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute(array_merge([currentCompanyId()], $params));
$projects = $stmt->fetchAll();
if ($only_unpaid) {
    $projects = array_filter($projects, function($p) {
        $paid = $p['paid'] ?? 0;
        $commission = $p['partner_commission'] ?? 0;
        return ($p['total_price'] - $paid - $commission) > 0;
    });
}
$total_projects = count($projects);

$pageTitle = "Dashboard - LegaVoice";
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">Dashboard</h3>
        <p class="text-muted mb-0 small">Ringkasan performa dan kesehatan keuangan</p>
    </div>
    <a href="project_form.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-2"></i>Proyek Baru</a>
<?php /* close header row div after button */ ?>
</div>

<!-- KEY METRICS ROW -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100 border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Saldo Kas</small>
                        <h3 class="fw-bold mb-0 text-primary mt-1"><?= formatRupiah($cash_balance) ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-2 rounded-circle">
                        <i class="bi bi-wallet2 text-primary fs-5"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted" style="font-size: 0.8rem;"><i class="bi bi-info-circle me-1"></i>Uang Tunai Riil</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Profit Bulan Ini</small>
                        <h3 class="fw-bold mb-0 text-success mt-1"><?= formatRupiah($net_this_month) ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 p-2 rounded-circle">
                        <i class="bi bi-graph-up-arrow text-success fs-5"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="d-flex justify-content-between small">
                         <span class="text-muted">Income:</span>
                         <span class="text-success"><?= formatRupiah($income_this_month) ?></span>
                    </div>
                     <div class="d-flex justify-content-between small">
                         <span class="text-muted">Expense:</span>
                         <span class="text-danger"><?= formatRupiah($expense_this_month) ?></span>
                    </div>
                    <div class="mt-2 pt-2 border-top">
                         <small class="text-secondary d-block" style="font-size: 0.75rem;">
                            <i class="bi bi-graph-up me-1"></i>Profit All Time: <?= formatRupiah($net_profit) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Hutang Vendor</small>
                        <h3 class="fw-bold mb-0 text-danger mt-1"><?= formatRupiah($total_payable) ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 p-2 rounded-circle">
                        <i class="bi bi-exclamation-triangle text-danger fs-5"></i>
                    </div>
                </div>
                <div class="mt-2">
                     <?php if($total_payable_used > 0): ?>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Murni:</span>
                            <span class="fw-bold"><?= formatRupiah($total_payable_pure) ?></span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-warning">Terpakai Owner:</span>
                            <span class="fw-bold text-warning"><?= formatRupiah($total_payable_used) ?></span>
                        </div>
                    <?php else: ?>
                        <small class="text-muted" style="font-size: 0.8rem;">Tagihan belum dibayar</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <a href="receivables.php" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm rounded-3 hover-shadow transition">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Piutang Klien</small>
                            <h3 class="fw-bold mb-0 text-warning mt-1"><?= formatRupiah($total_receivable) ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-2 rounded-circle">
                            <i class="bi bi-hourglass-split text-warning fs-5"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted" style="font-size: 0.8rem;">Tagihan belum lunas</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Belum Jatuh Tempo</small>
                <h6 class="fw-bold text-success mb-0"><?= formatRupiah($aging_receivable['current']) ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Tanpa Due Date</small>
                <h6 class="fw-bold text-secondary mb-0"><?= formatRupiah($aging_receivable['no_due']) ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Lewat 1–30</small>
                <h6 class="fw-bold text-warning mb-0"><?= formatRupiah($aging_receivable['over_0_30']) ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Lewat 31–60</small>
                <h6 class="fw-bold text-warning mb-0"><?= formatRupiah($aging_receivable['over_31_60']) ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Lewat 61–90</small>
                <h6 class="fw-bold text-danger mb-0"><?= formatRupiah($aging_receivable['over_61_90']) ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Lewat >90</small>
                <h6 class="fw-bold text-danger mb-0"><?= formatRupiah($aging_receivable['over_90_plus']) ?></h6>
            </div>
        </div>
    </div>
    </div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-primary mb-0"><i class="bi bi-building me-2"></i>Hak Perusahaan (40%)</h6>
                    <a href="company_withdrawal_form.php" class="btn btn-sm btn-outline-primary py-0" style="font-size: 0.75rem;">Kelola</a>
                </div>
            </div>
            <div class="card-body">
                <div class="text-center py-3">
                    <h2 class="fw-bold text-dark mb-0"><?= formatRupiah($company_available) ?></h2>
                    <small class="text-muted">Saldo Tersedia</small>
                </div>
                <div class="bg-light rounded p-3 mb-3">
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Total Bagian:</span>
                        <span class="fw-bold"><?= formatRupiah($share_company) ?></span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Sudah Diambil:</span>
                        <span class="text-danger fw-bold">-<?= formatRupiah($total_company_usage_net) ?></span>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="fw-bold text-secondary" style="font-size: 0.7rem;">TARGET 100 JUTA</small>
                        <small class="fw-bold text-primary"><?= number_format($company_progress_percent, 1) ?>%</small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $company_progress_percent ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-success mb-0"><i class="bi bi-person-check me-2"></i>Hak Owner (60%)</h6>
                    <a href="prive_form.php" class="btn btn-sm btn-outline-success py-0" style="font-size: 0.75rem;">Ambil Prive</a>
                </div>
            </div>
            <div class="card-body">
                 <div class="text-center py-3">
                    <h2 class="fw-bold <?= $owner_available < 0 ? 'text-danger' : 'text-success' ?> mb-0"><?= formatRupiah($owner_available) ?></h2>
                    <small class="text-muted">Sisa Jatah Prive</small>
                </div>
                <div class="bg-light rounded p-3 mb-3">
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Total Bagian:</span>
                        <span class="fw-bold"><?= formatRupiah($share_owner) ?></span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Sudah Diambil (Prive):</span>
                        <span class="text-danger fw-bold">-<?= formatRupiah($total_prive) ?></span>
                    </div>
                </div>
                 <?php if($total_hutang_owner_display > 0): ?>
                <div class="alert alert-warning border-0 d-flex align-items-center p-2 mb-0">
                    <i class="bi bi-exclamation-circle text-warning fs-4 me-2"></i>
                    <div class="flex-grow-1 lh-1">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Total Kewajiban (Hutang)</small>
                        <span class="fw-bold text-dark"><?= formatRupiah($total_hutang_owner_display) ?></span>
                    </div>
                    <a href="owner_debt.php" class="btn btn-sm btn-warning text-dark fw-bold" style="font-size: 0.7rem;">Detail</a>
                </div>
                 <div class="mt-2 ps-1">
                     <?php if(($total_kasbon_out - $total_kasbon_in) > 0): ?>
                     <div class="d-flex justify-content-between small text-muted">
                        <span>• Kasbon Vendor:</span>
                        <span><?= formatRupiah($total_kasbon_out - $total_kasbon_in) ?></span>
                     </div>
                     <?php endif; ?>
                     <?php if($total_company_debt_net > 0): ?>
                     <div class="d-flex justify-content-between small text-muted">
                        <span>• Pinjam Dana PT:</span>
                        <span><?= formatRupiah($total_company_debt_net) ?></span>
                     </div>
                     <?php endif; ?>
                 </div>
                <?php endif; ?>
<?php /* end card body */ ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 border-bottom-0">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-bar-chart-line me-2"></i>Tren Profit 12 Bulan</h6>
    </div>
    <div class="card-body">
        <canvas id="profitChart" style="max-height: 300px; width: 100%;"></canvas>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('profitChart').getContext('2d');
    const trendData = <?= json_encode($trend) ?>;
    const labels = trendData.map(item => item.label);
    const incomeData = trendData.map(item => item.income);
    const expenseData = trendData.map(item => item.expense);
    const netData = trendData.map(item => item.net);
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Pemasukan',
                    data: incomeData,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    order: 2
                },
                {
                    label: 'Pengeluaran',
                    data: expenseData,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    order: 3
                },
                {
                    label: 'Net Profit',
                    data: netData,
                    type: 'line',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgba(13, 110, 253, 1)',
                    pointRadius: 4,
                    tension: 0.3,
                    fill: false,
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'top', labels: { boxWidth: 12, usePointStyle: true } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f0f0f0' },
                    ticks: {
                        font: { size: 10 },
                        callback: function(value) {
                            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumSignificantDigits: 2 }).format(value);
                        }
                    }
                },
                x: { grid: { display: false }, ticks: { font: { size: 10 } } }
            }
        }
    });
});
</script>

<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
        <h5 class="mb-0 fw-bold text-dark">Proyek Terbaru</h5>
        <a href="projects.php" class="btn btn-sm btn-outline-primary">Lihat Semua Proyek</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-3 py-3 border-bottom-0 text-secondary small text-uppercase">Klien & Tanggal</th>
                    <th class="py-3 border-bottom-0 text-secondary small text-uppercase">Layanan</th>
                    <th class="py-3 border-bottom-0 text-secondary small text-uppercase">Nilai Proyek</th>
                    <th class="py-3 border-bottom-0 text-secondary small text-uppercase">Pembayaran</th>
                    <th class="py-3 border-bottom-0 text-secondary small text-uppercase">Biaya Ops</th>
                    <th class="py-3 border-bottom-0 text-secondary small text-uppercase">Status</th>
                    <th class="pe-3 py-3 border-bottom-0 text-end text-secondary small text-uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($projects)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        Belum ada data proyek
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach($projects as $p): 
                    $balance = $p['total_price'] - $p['paid'] - $p['partner_commission'];
                    $progress = ($p['total_price'] > 0) ? ($p['paid'] / $p['total_price']) * 100 : 0;
                    $statusClass = $balance <= 0 ? 'bg-success' : 'bg-warning text-dark';
                    $statusText = $balance <= 0 ? 'Lunas' : 'Belum Lunas';
                ?>
                <tr>
                    <td class="ps-3">
                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['client_name'] ?? '') ?></div>
                        <small class="text-muted"><?= date('d M Y', strtotime($p['created_at'])) ?></small>
                    </td>
                    <td>
                        <span class="badge bg-primary bg-opacity-10 text-primary"><?= htmlspecialchars($p['service_name'] ?? '') ?></span>
                    </td>
                    <td>
                        <div class="fw-bold"><?= formatRupiah($p['total_price']) ?></div>
                        <?php if($p['discount'] > 0): ?>
                            <small class="text-danger text-decoration-line-through" style="font-size: 0.75rem;"><?= formatRupiah($p['original_price']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="small mb-1">Paid: <span class="text-success fw-bold"><?= formatRupiah($p['paid']) ?></span></div>
                        <div class="progress" style="height: 4px; width: 100px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <div class="text-danger"><?= formatRupiah($p['cost']) ?></div>
                        <?php if($p['unpaid_vendor'] > 0): ?>
                            <small class="text-warning fw-bold" style="font-size: 0.7rem;"><i class="bi bi-exclamation-circle me-1"></i>Hutang: <?= formatRupiah($p['unpaid_vendor']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $statusClass ?> rounded-pill"><?= $statusText ?></span>
                    </td>
                    <td class="text-end pe-3">
                        <a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Detail</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; 