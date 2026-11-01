<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$channel = isset($_GET['channel']) ? strtolower($_GET['channel']) : 'wa';

function normalizePhone($phone) {
    $digits = preg_replace('/\D+/', '', $phone ?? '');
    if ($digits === '') return '';
    if (strpos($digits, '62') === 0) return $digits;
    if (strpos($digits, '0') === 0) return '62' . substr($digits, 1);
    return $digits;
}

if ($project_id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, c.phone, c.email
    FROM projects p
    LEFT JOIN clients c ON c.id = p.client_id
    WHERE p.id = ?
");
$stmt->execute([$project_id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    header("Location: index.php");
    exit;
}

$stmtPaid = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE project_id = ? AND type = 'income'");
$stmtPaid->execute([$project_id]);
$paid = (float)($stmtPaid->fetchColumn() ?: 0);
$stmtTermin = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE project_id = ? AND type = 'income'");
$stmtTermin->execute([$project_id]);
$termin_no = (int)($stmtTermin->fetchColumn() ?: 0);
$remaining = (float)($p['total_price'] - $paid);
$due = $p['due_date'] ?: '-';
$settingsRow = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$partnerBank = ['bank_name' => '', 'bank_account' => '', 'bank_holder' => ''];
if (!empty($p['partner_id'])) {
    $stmtPB = $pdo->prepare("SELECT bank_name, bank_account, bank_holder FROM partners WHERE id = ?");
    $stmtPB->execute([$p['partner_id']]);
    $partnerBank = $stmtPB->fetch(PDO::FETCH_ASSOC) ?: $partnerBank;
}
if (empty($partnerBank['bank_name'])) {
    $stmtDef = $pdo->query("SELECT bank_name, bank_account, bank_holder FROM partners WHERE is_default = 1 LIMIT 1");
    $def = $stmtDef->fetch(PDO::FETCH_ASSOC);
    if ($def) $partnerBank = $def;
}
if (empty($partnerBank['bank_name'])) {
    $partnerBank['bank_name'] = $settingsRow['bank_name'] ?? '';
    $partnerBank['bank_account'] = $settingsRow['bank_account'] ?? '';
    $partnerBank['bank_holder'] = $settingsRow['bank_holder'] ?? '';
}
function tpl($text, $vars) {
    foreach ($vars as $k => $v) {
        $text = str_replace('{{'.$k.'}}', $v, $text);
    }
    return $text;
}
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$invoice_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/print_invoice.php?id=' . $project_id;
$vars = [
    'client_name' => $p['client_name'] ?? '',
    'service_name' => $p['service_name'] ?? '',
    'remaining' => formatRupiah($remaining),
    'due_date' => $due,
    'bank_name' => $partnerBank['bank_name'] ?? ($settingsRow['bank_name'] ?? ''),
    'bank_account' => $partnerBank['bank_account'] ?? ($settingsRow['bank_account'] ?? ''),
    'bank_holder' => $partnerBank['bank_holder'] ?? ($settingsRow['bank_holder'] ?? ''),
    'company_name' => $settingsRow['company_name'] ?? '',
    'company_phone' => $settingsRow['company_phone'] ?? '',
    'invoice_url' => $invoice_url,
    'payment_link' => (!empty($p['payment_link']) ? $p['payment_link'] : $invoice_url),
    'termin' => 'Termin ke-' . max(1, $termin_no)
];

if ($channel === 'wa') {
    $wa_phone = normalizePhone($p['phone']);
    $defaultWa = "Halo {{client_name}}, kami mengingatkan tagihan layanan {{service_name}} sebesar {{remaining}}. Jatuh tempo: {{due_date}}.\n\nPembayaran: Bank {{bank_name}}, Rekening {{bank_account}} a/n {{bank_holder}}.\nDetail Invoice: {{invoice_url}}\nTerima kasih.";
    $msg = $settingsRow && !empty($settingsRow['reminder_wa_template']) ? tpl($settingsRow['reminder_wa_template'], $vars) : tpl($defaultWa, $vars);
    logActivity($pdo, 'REMINDER_WA', "PID: " . $project_id . " WA to " . ($p['client_name'] ?? '') . " (" . ($wa_phone ?: 'no phone') . ") Remaining: " . formatRupiah($remaining));
    if (!$wa_phone) {
        header("Location: project_detail.php?id=" . $project_id);
        exit;
    }
    $link = "https://wa.me/" . $wa_phone . "?text=" . urlencode($msg);
    header("Location: " . $link);
    exit;
} elseif ($channel === 'email') {
    $email = $p['email'] ?? '';
    $subjectTpl = $settingsRow && !empty($settingsRow['reminder_email_subject']) ? $settingsRow['reminder_email_subject'] : "Pengingat Tagihan: {{service_name}}";
    $defaultEmail = "Halo {{client_name}},\n\nKami mengingatkan tagihan sebesar {{remaining}} untuk layanan {{service_name}}.\nJatuh tempo: {{due_date}}.\n\nPembayaran: Bank {{bank_name}}, Rekening {{bank_account}} a/n {{bank_holder}}.\nDetail Invoice: {{invoice_url}}\n\nTerima kasih.";
    $bodyTpl = $settingsRow && !empty($settingsRow['reminder_email_body']) ? $settingsRow['reminder_email_body'] : $defaultEmail;
    $subject = tpl($subjectTpl, $vars);
    $bodyPlain = tpl($bodyTpl, $vars);
    $body = str_replace("\n", "%0D%0A", urlencode($bodyPlain));
    logActivity($pdo, 'REMINDER_EMAIL', "PID: " . $project_id . " Email to " . ($p['client_name'] ?? '') . " (" . ($email ?: 'no email') . ") Remaining: " . formatRupiah($remaining));
    if (!$email) {
        header("Location: project_detail.php?id=" . $project_id);
        exit;
    }
    $link = "mailto:" . $email . "?subject=" . urlencode($subject) . "&body=" . $body;
    header("Location: " . $link);
    exit;
} else {
    header("Location: project_detail.php?id=" . $project_id);
    exit;
}
