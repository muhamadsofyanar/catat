<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if ($project_id <= 0) {
    header("Location: index.php");
    exit;
}

function normalizePhone($phone) {
    $digits = preg_replace('/\D+/', '', $phone ?? '');
    if ($digits === '') return '';
    if (strpos($digits, '62') === 0) return $digits;
    if (strpos($digits, '0') === 0) return '62' . substr($digits, 1);
    return $digits;
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

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$invoice_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/print_invoice.php?id=' . $project_id;
$vars = [
    '{{client_name}}' => ($p['client_name'] ?? ''),
    '{{service_name}}' => ($p['service_name'] ?? ''),
    '{{remaining}}' => formatRupiah($remaining),
    '{{due_date}}' => $due,
    '{{bank_name}}' => $partnerBank['bank_name'] ?? ($settingsRow['bank_name'] ?? ''),
    '{{bank_account}}' => $partnerBank['bank_account'] ?? ($settingsRow['bank_account'] ?? ''),
    '{{bank_holder}}' => $partnerBank['bank_holder'] ?? ($settingsRow['bank_holder'] ?? ''),
    '{{company_name}}' => $settingsRow['company_name'] ?? '',
    '{{company_phone}}' => $settingsRow['company_phone'] ?? '',
    '{{invoice_url}}' => $invoice_url,
    '{{payment_link}}' => !empty($p['payment_link']) ? $p['payment_link'] : $invoice_url,
    '{{termin}}' => 'Termin ke-' . max(1, $termin_no)
];

$waTpl = $settingsRow && !empty($settingsRow['reminder_wa_template']) ? $settingsRow['reminder_wa_template'] :
    "Halo {{client_name}}, kami mengingatkan tagihan layanan {{service_name}} sebesar {{remaining}}. Jatuh tempo: {{due_date}}.\n\nPembayaran: Bank {{bank_name}}, Rekening {{bank_account}} a/n {{bank_holder}}.\nDetail Invoice: {{invoice_url}}\nTerima kasih.";
$emailSubTpl = $settingsRow && !empty($settingsRow['reminder_email_subject']) ? $settingsRow['reminder_email_subject'] : "Pengingat Tagihan: {{service_name}}";
$emailBodyTpl = $settingsRow && !empty($settingsRow['reminder_email_body']) ? $settingsRow['reminder_email_body'] :
    "Halo {{client_name}},\n\nKami mengingatkan tagihan sebesar {{remaining}} untuk layanan {{service_name}}.\nJatuh tempo: {{due_date}}.\n\nPembayaran: Bank {{bank_name}}, Rekening {{bank_account}} a/n {{bank_holder}}.\nDetail Invoice: {{invoice_url}}\n\nTerima kasih.";
$wa_message = strtr($waTpl, $vars);
$email_subject = strtr($emailSubTpl, $vars);
$email_body = strtr($emailBodyTpl, $vars);
$wa_phone = normalizePhone($p['phone'] ?? '');

$pageTitle = "Preview Pengingat Tagihan";
require __DIR__ . '/../includes/header.php';
?>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Preview Pengingat</h4>
            <small class="text-muted">#<?= $project_id ?> • <?= htmlspecialchars($p['client_name'] ?? '-') ?> • <?= htmlspecialchars($p['service_name'] ?? '-') ?></small>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border">Sisa Tagihan</span>
            <h5 class="fw-bold text-warning mb-0"><?= formatRupiah($remaining) ?></h5>
            <small class="text-muted">Jatuh Tempo: <?= htmlspecialchars($due) ?></small>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-whatsapp text-success me-2"></i><strong>WhatsApp</strong></div>
                    <div class="text-muted small"><?= $wa_phone ? 'Target: '.$wa_phone : 'Nomor WA tidak tersedia' ?></div>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded" style="white-space: pre-wrap;"><?= htmlspecialchars($wa_message) ?></pre>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between">
                    <a href="project_detail.php?id=<?= $project_id ?>" class="btn btn-light border"><i class="bi bi-arrow-left"></i> Kembali</a>
                    <?php if($wa_phone): ?>
                        <a href="send_reminder.php?project_id=<?= $project_id ?>&channel=wa" class="btn btn-success"><i class="bi bi-send"></i> Kirim WA</a>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled><i class="bi bi-send"></i> Kirim WA</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-envelope me-2"></i><strong>Email</strong></div>
                    <div class="text-muted small"><?= !empty($p['email']) ? 'Target: '.htmlspecialchars($p['email'] ?? '') : 'Email tidak tersedia' ?></div>
                </div>
                <div class="card-body">
                    <div class="mb-2"><strong>Subject:</strong> <?= htmlspecialchars($email_subject) ?></div>
                    <pre class="bg-light p-3 rounded" style="white-space: pre-wrap;"><?= htmlspecialchars($email_body) ?></pre>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between">
                    <a href="project_detail.php?id=<?= $project_id ?>" class="btn btn-light border"><i class="bi bi-arrow-left"></i> Kembali</a>
                    <?php if(!empty($p['email'])): ?>
                        <a href="send_reminder.php?project_id=<?= $project_id ?>&channel=email" class="btn btn-outline-secondary"><i class="bi bi-send"></i> Kirim Email</a>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled><i class="bi bi-send"></i> Kirim Email</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-4">
        <div class="alert alert-light border">
            <div class="fw-bold mb-1"><i class="bi bi-file-earmark-text me-2"></i>Invoice</div>
            <div><a href="<?= htmlspecialchars($invoice_url) ?>" target="_blank"><?= htmlspecialchars($invoice_url) ?></a></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 