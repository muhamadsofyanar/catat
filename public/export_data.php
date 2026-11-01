<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
$type = $_GET['type'] ?? 'transactions';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$partner_id = $_GET['partner_id'] ?? '';
$service_id = $_GET['service_id'] ?? '';
$client_id = $_GET['client_id'] ?? '';
$start_date_override = $_GET['start_date'] ?? null;
$end_date_override = $_GET['end_date'] ?? null;
$filter_cf = $_GET['filter'] ?? 'all';
$category_cf = isset($_GET['category']) ? trim($_GET['category']) : '';
$start_date = "$year-$month-01";
$end_date = date("Y-m-t", strtotime($start_date));
if ($start_date_override && $end_date_override) {
    $start_date = $start_date_override;
    $end_date = $end_date_override;
}
$filename_date = "$year-$month";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="legavoice_export_' . $type . '_' . $filename_date . '.csv"');
$output = fopen('php://output', 'w');
$admin_only_types = ['general_expenses', 'payables', 'receivables', 'partner_payments', 'users', 'owner_debt', 'activity_logs', 'summary', 'cashflow', 'project_pl'];
if (in_array($type, $admin_only_types)) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        fputcsv($output, ['ERROR: Access Denied. Admin privileges required.']);
        fclose($output);
        exit;
    }
}
if ($type === 'transactions') {
    fputcsv($output, ['ID', 'Tanggal', 'Tipe', 'Kategori', 'Jumlah', 'Deskripsi', 'Klien/Project', 'Status']);
    $sql = "
        SELECT t.id, t.transaction_date, t.type, t.category, t.amount, t.note, 
               p.client_name, p.service_name, t.status
        FROM transactions t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.transaction_date BETWEEN ? AND ?
        ORDER BY t.transaction_date ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $project_info = $row['client_name'] ? $row['client_name'] . " - " . $row['service_name'] : '-';
        fputcsv($output, [
            $row['id'],
            $row['transaction_date'],
            strtoupper($row['type']),
            $row['category'],
            $row['amount'],
            $row['note'],
            $project_info,
            $row['status']
        ]);
    }
} elseif ($type === 'cashflow') {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    fputcsv($output, ['Tanggal', 'Keterangan', 'Masuk (Debet)', 'Keluar (Kredit)', 'Klien', 'Layanan']);
    $conditions = ["t.transaction_date BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];
    if ($filter_cf === 'income') $conditions[] = "t.type = 'income'";
    if ($filter_cf === 'expense') $conditions[] = "t.type = 'expense'";
    if ($filter_cf === 'prive') $conditions[] = "t.type = 'expense' AND t.category = 'Prive'";
    if ($category_cf !== '') {
        $conditions[] = "t.category = ?";
        $params[] = $category_cf;
    }
    if ($partner_id !== '') {
        $conditions[] = "p.partner_id = ?";
        $params[] = $partner_id;
    }
    if ($service_id !== '') {
        $conditions[] = "p.service_id = ?";
        $params[] = $service_id;
    }
    if ($client_id !== '') {
        $conditions[] = "p.client_id = ?";
        $params[] = $client_id;
    }
    if ($q !== '') {
        $conditions[] = "(t.note LIKE ? OR t.category LIKE ? OR p.client_name LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $whereSql = "WHERE " . implode(" AND ", $conditions);
    $sql = "
        SELECT t.*, p.client_name, p.service_name
        FROM transactions t
        LEFT JOIN projects p ON t.project_id = p.id
        $whereSql
        ORDER BY t.transaction_date ASC, t.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['type'] === 'expense' && $row['status'] === 'unpaid') continue;
        $debit = ($row['type'] === 'income') ? $row['amount'] : 0;
        $credit = ($row['type'] === 'expense') ? $row['amount'] : 0;
        fputcsv($output, [
            $row['transaction_date'],
            ($row['note'] ?? '') . " (" . ($row['category'] ?? '-') . ")",
            $debit,
            $credit,
            $row['client_name'] ?: '-',
            $row['service_name'] ?: '-'
        ]);
    }
} elseif ($type === 'summary') {
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
    $gross_profit = $income - $expense_project;
    $net_profit = $income - ($expense_project + $expense_operational);
    fputcsv($output, ['LAPORAN LABA RUGI']);
    fputcsv($output, ['Periode', "$month/$year"]);
    fputcsv($output, []);
    fputcsv($output, ['Keterangan', 'Nilai']);
    fputcsv($output, ['Pendapatan (Revenue)', $income]);
    fputcsv($output, ['Biaya Langsung Proyek (HPP)', $expense_project]);
    fputcsv($output, ['Laba Kotor (Gross Profit)', $gross_profit]);
    fputcsv($output, ['Biaya Operasional (Opex)', $expense_operational]);
    fputcsv($output, ['Laba Bersih (Net Profit)', $net_profit]);
} elseif ($type === 'project_pl') {
    fputcsv($output, ['LAPORAN LABA RUGI PER PROYEK']);
    fputcsv($output, ['Periode', "$month/$year"]);
    fputcsv($output, []);
    fputcsv($output, ['Project ID', 'Client', 'Service', 'Income', 'Expense', 'Partner Commission', 'Net Cash', 'Margin After Commission']);
    $conditions = ["p.client_name != 'INTERNAL'"];
    $params = [$start_date, $end_date, $start_date, $end_date];
    if ($partner_id !== '') $conditions[] = "p.partner_id = ?";
    if ($service_id !== '') $conditions[] = "p.service_id = ?";
    $whereSql = "WHERE " . implode(" AND ", $conditions);
    $sql = "
        SELECT p.*,
            (SELECT SUM(t.amount) FROM transactions t WHERE t.project_id = p.id AND t.type='income' AND t.transaction_date BETWEEN ? AND ?) as income_period,
            (SELECT SUM(t.amount) FROM transactions t WHERE t.project_id = p.id AND t.type='expense' AND t.transaction_date BETWEEN ? AND ? AND t.category NOT IN ('Prive','Company Withdrawal','Kasbon','Pengembalian Kasbon','Pengembalian Dana Perusahaan')) as expense_period
        FROM projects p
        $whereSql
        ORDER BY p.created_at DESC
    ";
    $bind = $params;
    if ($partner_id !== '') $bind[] = $partner_id;
    if ($service_id !== '') $bind[] = $service_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $inc = $p['income_period'] ?: 0;
        $exp = $p['expense_period'] ?: 0;
        $comm = $p['partner_commission'] ?: 0;
        $net = $inc - $exp;
        $margin = $net - $comm;
        fputcsv($output, [
            $p['id'],
            $p['client_name'],
            $p['service_name'],
            $inc,
            $exp,
            $comm,
            $net,
            $margin
        ]);
    }
} elseif ($type === 'receivables') {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $only_overdue = isset($_GET['only_overdue']) && $_GET['only_overdue'] === '1';
    $partner_id = isset($_GET['partner_id']) ? $_GET['partner_id'] : '';
    fputcsv($output, ['Klien', 'Layanan', 'Jatuh Tempo', 'Nilai Proyek', 'Sudah Dibayar', 'Sisa Tagihan', 'Status', 'Aging Bucket']);
    $conditions = ["p.client_name != 'INTERNAL'"];
    $params = [];
    if ($q !== '') {
        $conditions[] = "(p.client_name LIKE ? OR p.service_name LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($partner_id !== '') {
        $conditions[] = "p.partner_id = ?";
        $params[] = $partner_id;
    }
    $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $stmt = $pdo->prepare("
        SELECT p.*,
        (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income') AS paid
        FROM projects p
        $whereSql
        ORDER BY COALESCE(p.due_date, '9999-12-31') ASC, p.created_at DESC
    ");
    $stmt->execute($params);
    while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paid = $p['paid'] ?? 0;
        $remaining = ($p['total_price'] - $paid);
        if ($remaining > 0) {
            $is_overdue = !empty($p['due_date']) && strtotime($p['due_date']) < strtotime(date('Y-m-d'));
            if ($only_overdue && !$is_overdue) continue;
            $bucket = '-';
            if (empty($p['due_date'])) {
                $bucket = 'NO_DUE';
            } else {
                $days = (int)floor((time() - strtotime($p['due_date'])) / 86400);
                if ($days <= 0) {
                    $bucket = 'CURRENT';
                } elseif ($days <= 30) {
                    $bucket = 'OVER_0_30';
                } elseif ($days <= 60) {
                    $bucket = 'OVER_31_60';
                } elseif ($days <= 90) {
                    $bucket = 'OVER_61_90';
                } else {
                    $bucket = 'OVER_90_PLUS';
                }
            }
            fputcsv($output, [
                $p['client_name'],
                $p['service_name'],
                $p['due_date'] ? $p['due_date'] : '-',
                $p['total_price'],
                $paid,
                $remaining,
                $is_overdue ? 'OVERDUE' : 'Active',
                $bucket
            ]);
        }
    }
} elseif ($type === 'receivables_reminders') {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $only_overdue = isset($_GET['only_overdue']) && $_GET['only_overdue'] === '1';
    $partner_id = isset($_GET['partner_id']) ? $_GET['partner_id'] : '';
    fputcsv($output, ['Client', 'Service', 'Due Date', 'Remaining', 'WA Phone', 'WA Message', 'Email', 'Email Subject', 'Email Body', 'Payment Link']);
    $settingsRow = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $conditions = ["p.client_name != 'INTERNAL'"];
    $params = [];
    if ($q !== '') {
        $conditions[] = "(p.client_name LIKE ? OR p.service_name LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($partner_id !== '') {
        $conditions[] = "p.partner_id = ?";
        $params[] = $partner_id;
    }
    $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income') AS paid,
               c.phone, c.email,
               part.bank_name as partner_bank_name,
               part.bank_account as partner_bank_account,
               part.bank_holder as partner_bank_holder
        FROM projects p
        LEFT JOIN clients c ON c.id = p.client_id
        LEFT JOIN partners part ON part.id = p.partner_id
        $whereSql
        ORDER BY COALESCE(p.due_date, '9999-12-31') ASC, p.created_at DESC
    ");
    $stmt->execute($params);
    while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paid = $p['paid'] ?? 0;
        $remaining = ($p['total_price'] - $paid);
        if ($remaining > 0) {
            $is_overdue = !empty($p['due_date']) && strtotime($p['due_date']) < strtotime(date('Y-m-d'));
            if ($only_overdue && !$is_overdue) continue;
            $phone = preg_replace('/\D+/', '', $p['phone'] ?? '');
            if ($phone !== '') {
                if (strpos($phone, '62') === 0) {
                    $wa_phone = $phone;
                } elseif (strpos($phone, '0') === 0) {
                    $wa_phone = '62' . substr($phone, 1);
                } else {
                    $wa_phone = $phone;
                }
            } else {
                $wa_phone = '';
            }
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
            $invoice_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/print_invoice.php?id=' . $p['id'];
            $bank_name = $p['partner_bank_name'] ?: ($settingsRow['bank_name'] ?? '');
            $bank_account = $p['partner_bank_account'] ?: ($settingsRow['bank_account'] ?? '');
            $bank_holder = $p['partner_bank_holder'] ?: ($settingsRow['bank_holder'] ?? '');
            $stmtTermin = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE project_id = ? AND type = 'income'");
            $stmtTermin->execute([$p['id']]);
            $termin_no = (int)($stmtTermin->fetchColumn() ?: 0);
            $vars = [
                '{{client_name}}' => ($p['client_name'] ?? ''),
                '{{service_name}}' => ($p['service_name'] ?? ''),
                '{{remaining}}' => formatRupiah($remaining),
                '{{due_date}}' => ($p['due_date'] ?: '-'),
                '{{bank_name}}' => $bank_name,
                '{{bank_account}}' => $bank_account,
                '{{bank_holder}}' => $bank_holder,
                '{{company_name}}' => ($settingsRow['company_name'] ?? ''),
                '{{company_phone}}' => ($settingsRow['company_phone'] ?? ''),
                '{{invoice_url}}' => $invoice_url,
                '{{payment_link}}' => !empty($p['payment_link']) ? $p['payment_link'] : ($settingsRow['payment_link'] ?? ''),
                '{{termin}}' => 'Termin ke-' . max(1, $termin_no)
            ];
            $waTpl = $settingsRow && !empty($settingsRow['reminder_wa_template']) ? $settingsRow['reminder_wa_template'] : "Halo {{client_name}}, kami mengingatkan tagihan layanan {{service_name}} sebesar {{remaining}}. Jatuh tempo: {{due_date}}.\n\nPembayaran: Bank {{bank_name}}, Rekening {{bank_account}} a/n {{bank_holder}}.\nDetail Invoice: {{invoice_url}}\nTerima kasih.";
            $emailSubTpl = $settingsRow && !empty($settingsRow['reminder_email_subject']) ? $settingsRow['reminder_email_subject'] : "Pengingat Tagihan: {{service_name}}";
            $emailBodyTpl = $settingsRow && !empty($settingsRow['reminder_email_body']) ? $settingsRow['reminder_email_body'] : "Halo {{client_name}},\n\nKami mengingatkan tagihan sebesar {{remaining}} untuk layanan {{service_name}}.\nJatuh tempo: {{due_date}}.\n\nPembayaran: Bank {{bank_name}}, Rekening {{bank_account}} a/n {{bank_holder}}.\nDetail Invoice: {{invoice_url}}\n\nTerima kasih.";
            $wa_message = strtr($waTpl, $vars);
            $email_subject = strtr($emailSubTpl, $vars);
            $email_body = strtr($emailBodyTpl, $vars);
            fputcsv($output, [
                $p['client_name'],
                $p['service_name'],
                $p['due_date'] ? $p['due_date'] : '-',
                formatRupiah($remaining),
                $wa_phone,
                $wa_message,
                $p['email'] ?? '',
                $email_subject,
                $email_body,
                (!empty($p['payment_link']) ? $p['payment_link'] : ($settingsRow['payment_link'] ?? ''))
            ]);
        }
    }
} elseif ($type === 'projects') {
    fputcsv($output, ['ID', 'Tanggal', 'Klien', 'Layanan', 'Mitra', 'Status', 'Nilai Proyek', 'Terbayar', 'Biaya Ops', 'Hutang Vendor', 'Margin', 'Tanggal Selesai']);
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $client_id = isset($_GET['client_id']) ? $_GET['client_id'] : '';
    $partner_id = isset($_GET['partner_id']) ? $_GET['partner_id'] : '';
    $service_id = isset($_GET['service_id']) ? $_GET['service_id'] : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $conditions = [];
    $params = [];
    if ($q !== '') {
        $conditions[] = "(p.client_name LIKE ? OR p.service_name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($client_id !== '') {
        $conditions[] = "p.client_id = ?";
        $params[] = $client_id;
    }
    if ($partner_id !== '') {
        $conditions[] = "p.partner_id = ?";
        $params[] = $partner_id;
    }
    if ($service_id !== '') {
        $conditions[] = "p.service_id = ?";
        $params[] = $service_id;
    }
    if ($status_filter === 'active') {
        $conditions[] = "p.status != 'done' AND p.client_name != 'INTERNAL'";
    } elseif ($status_filter === 'done') {
        $conditions[] = "p.status = 'done'";
    } elseif ($status_filter === 'internal') {
        $conditions[] = "p.client_name = 'INTERNAL'";
    }
    if ($start_date !== '' && $end_date !== '') {
        $conditions[] = "date(p.created_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $sql = "
        SELECT p.*, 
        (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income') as paid,
        (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'expense' AND category NOT IN ('Kasbon', 'Prive', 'Company Withdrawal', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan', 'Operasional Kantor')) as cost,
        (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'expense' AND status = 'unpaid' AND category NOT IN ('Kasbon', 'Prive', 'Company Withdrawal', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan', 'Operasional Kantor')) as unpaid_vendor,
        part.name as partner_name
        FROM projects p
        LEFT JOIN partners part ON p.partner_id = part.id
        $whereSql
        ORDER BY p.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paid = $p['paid'] ?? 0;
        $cost = $p['cost'] ?? 0;
        $margin = $paid - $cost;
        fputcsv($output, [
            $p['id'],
            $p['created_at'],
            $p['client_name'],
            $p['service_name'],
            $p['partner_name'] ?? '-',
            $p['status'],
            $p['total_price'],
            $paid,
            $cost,
            $p['unpaid_vendor'] ?? 0,
            $margin,
            $p['due_date']
        ]);
    }
} elseif ($type === 'project_statement') {
    $project_id = $_GET['project_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT p.*, part.name as partner_name FROM projects p LEFT JOIN partners part ON p.partner_id = part.id WHERE p.id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    if ($project) {
        fputcsv($output, ['PROJECT STATEMENT']);
        fputcsv($output, ['Project ID', '#' . $project['id']]);
        fputcsv($output, ['Client', $project['client_name']]);
        fputcsv($output, ['Service', $project['service_name']]);
        fputcsv($output, ['Partner', $project['partner_name'] ?? '-']);
        fputcsv($output, ['Status', strtoupper($project['status'])]);
        fputcsv($output, ['Total Value', $project['total_price']]);
        fputcsv($output, []);
        fputcsv($output, ['Tanggal', 'Tipe', 'Kategori', 'Keterangan', 'Status', 'Jumlah']);
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE project_id = ? ORDER BY transaction_date ASC, id ASC");
        $stmt->execute([$project_id]);
        $total_income = 0;
        $total_expense = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['type'] == 'income') $total_income += $row['amount'];
            if ($row['type'] == 'expense') $total_expense += $row['amount'];
            fputcsv($output, [
                $row['transaction_date'],
                strtoupper($row['type']),
                $row['category'],
                $row['note'],
                $row['status'],
                $row['amount']
            ]);
        }
        fputcsv($output, []);
        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Total Paid (Income)', $total_income]);
        fputcsv($output, ['Total Cost (Expense)', $total_expense]);
        fputcsv($output, ['Net Cash', $total_income - $total_expense]);
    } else {
        fputcsv($output, ['Project Not Found']);
    }
} elseif ($type === 'payables') {
    fputcsv($output, ['Tanggal', 'Kategori/Vendor', 'Mitra', 'Klien', 'Layanan', 'Catatan', 'Jumlah']);
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $partner_id = isset($_GET['partner_id']) ? $_GET['partner_id'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $conditions = [];
    $params = [];
    $conditions[] = "t.type = 'expense'";
    $conditions[] = "t.status = 'unpaid'";
    if ($partner_id !== '') {
        $conditions[] = "p.partner_id = ?";
        $params[] = $partner_id;
    }
    if ($start_date !== '' && $end_date !== '') {
        $conditions[] = "t.transaction_date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    if ($q !== '') {
        $conditions[] = "(p.client_name LIKE ? OR p.service_name LIKE ? OR t.note LIKE ? OR t.category LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $stmt = $pdo->prepare("
        SELECT t.*, p.client_name, p.service_name,
        part.name as partner_name
        FROM transactions t
        LEFT JOIN projects p ON t.project_id = p.id
        LEFT JOIN partners part ON p.partner_id = part.id
        $whereSql
        ORDER BY t.transaction_date ASC
    ");
    $stmt->execute($params);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $r['transaction_date'],
            $r['category'],
            $r['partner_name'] ?? '-',
            $r['client_name'] ?? 'Internal',
            $r['service_name'] ?? '-',
            $r['note'],
            $r['amount']
        ]);
    }
} elseif ($type === 'partner_payments') {
    fputcsv($output, ['Tanggal', 'Mitra', 'Vendor/Kategori', 'Klien', 'Layanan', 'Keterangan', 'Jumlah']);
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
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $r['transaction_date'],
            $r['partner_name'] ?? '-',
            $r['category'],
            $r['client_name'] ?? 'Operasional',
            $r['service_name'] ?? '-',
            $r['note'],
            $r['amount']
        ]);
    }
} elseif ($type === 'clients') {
    fputcsv($output, ['Nama', 'No. HP', 'Email', 'Alamat']);
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $params = [];
    $sql = "SELECT * FROM clients";
    if ($q !== '') {
        $sql .= " WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $sql .= " ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $r['name'],
            $r['phone'],
            $r['email'],
            $r['address']
        ]);
    }
} elseif ($type === 'partners') {
    fputcsv($output, ['Nama', 'No. HP', 'Alamat', 'Bank', 'No. Rekening', 'Atas Nama']);
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $params = [];
    $sql = "SELECT * FROM partners";
    if ($q !== '') {
        $sql .= " WHERE name LIKE ? OR phone LIKE ? OR bank_name LIKE ?";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $sql .= " ORDER BY is_default DESC, name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $r['name'],
            $r['phone'],
            $r['address'],
            $r['bank_name'],
            $r['bank_account'],
            $r['bank_holder']
        ]);
    }
} elseif ($type === 'services') {
    fputcsv($output, ['Kategori', 'Nama Layanan', 'Harga Jual', 'Total Modal (HPP)', 'Potensi Laba']);
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
    $conditions = [];
    $params = [];
    $sql = "
        SELECT s*, 
        COALESCE((SELECT SUM(amount) FROM service_costs WHERE service_id = s.id), 0) as total_cost
        FROM services s
    ";
    if ($q !== '') {
        $conditions[] = "(s.name LIKE ? OR s.category LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($category_filter !== '') {
        $conditions[] = "s.category = ?";
        $params[] = $category_filter;
    }
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY s.category, s.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $profit = $r['default_price'] - $r['total_cost'];
        fputcsv($output, [
            $r['category'],
            $r['name'],
            $r['default_price'],
            $r['total_cost'],
            $profit
        ]);
    }
} elseif ($type === 'general_expenses') {
    fputcsv($output, ['Tanggal', 'Tipe', 'Kategori', 'Keterangan', 'Status', 'Jumlah']);
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE client_name = 'INTERNAL' AND service_name = 'KEUANGAN PERUSAHAAN'");
    $stmt->execute();
    $proj = $stmt->fetch();
    $project_id = $proj ? $proj['id'] : 0;
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
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $r['transaction_date'],
            $r['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran',
            $r['category'],
            $r['note'],
            $r['status'] === 'paid' ? 'Lunas' : 'Belum Dibayar',
            $r['amount']
        ]);
    }
} elseif ($type === 'users') {
    fputcsv($output, ['ID', 'Username', 'Role', 'Status', 'Tanggal Dibuat']);
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $role_filter = isset($_GET['role']) ? $_GET['role'] : '';
    $conditions = [];
    $params = [];
    if ($q) {
        $conditions[] = "username LIKE ?";
        $params[] = "%$q%";
    }
    if ($role_filter) {
        $conditions[] = "role = ?";
        $params[] = $role_filter;
    }
    $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $sql = "SELECT * FROM users $whereSql ORDER BY username ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $r['id'],
            $r['username'],
            ucfirst($r['role']),
            $r['is_active'] ? 'Active' : 'Inactive',
            $r['created_at']
        ]);
    }
} elseif ($type === 'activity_logs') {
    fputcsv($output, ['Waktu', 'User', 'Aksi', 'Deskripsi']);
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $action_filter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';
    $conditions = [];
    $params = [];
    if ($q) {
        $conditions[] = "(description LIKE ? OR username LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($action_filter) {
        $conditions[] = "action = ?";
        $params[] = $action_filter;
    }
    $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $sql = "SELECT * FROM activity_logs $whereSql ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $r['created_at'],
            $r['username'],
            $r['action'],
            $r['description']
        ]);
    }
} elseif ($type === 'owner_debt') {
    fputcsv($output, ['Tanggal', 'Kategori', 'Keterangan', 'Tipe', 'Jumlah']);
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $conditions = [
        "category IN ('Kasbon', 'Pengembalian Kasbon', 'Company Withdrawal', 'Pengembalian Dana Perusahaan')",
        "transaction_date BETWEEN ? AND ?"
    ];
    $params = [$start_date, $end_date];
    if ($q) {
        $conditions[] = "note LIKE ?";
        $params[] = "%$q%";
    }
    $whereSql = "WHERE " . implode(" AND ", $conditions);
    $sql = "SELECT * FROM transactions $whereSql ORDER BY transaction_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type_label = ($r['type'] === 'income') ? 'Pemasukan (Pengembalian)' : 'Pengeluaran (Hutang)';
        fputcsv($output, [
            $r['transaction_date'],
            $r['category'],
            $r['note'],
            $type_label,
            $r['amount']
        ]);
    }
}
fclose($output);
exit;
