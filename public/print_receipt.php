<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$id = $_GET['id'] ?? null;
if (!$id) die("ID Invalid");
$stmt = $pdo->prepare("SELECT t.*, p.client_name, p.service_name, p.total_price, p.partner_commission, p.id as project_id FROM transactions t JOIN projects p ON t.project_id = p.id WHERE t.id = ?");
$stmt->execute([$id]);
$trans = $stmt->fetch();
if (!$trans) die("Data tidak ditemukan");
$stmtSum = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE project_id = ? AND type = 'income'");
$stmtSum->execute([$trans['project_id']]);
$total_paid = $stmtSum->fetchColumn() ?: 0;
$commission = $trans['partner_commission'] ?? 0;
$net_total = $trans['total_price'];
$remaining = $net_total - $total_paid;
$partner = null;
$stmtProj = $pdo->prepare("SELECT partner_id FROM projects WHERE id = ?");
$stmtProj->execute([$trans['project_id']]);
$proj = $stmtProj->fetch();
if ($proj && !empty($proj['partner_id'])) {
    $stmtP = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
    $stmtP->execute([$proj['partner_id']]);
    $partner = $stmtP->fetch();
}
if (!$partner) {
    $stmtP = $pdo->query("SELECT * FROM partners WHERE is_default = 1 LIMIT 1");
    $partner = $stmtP->fetch();
}
if (!$partner) {
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch();
    if ($settings) {
        $partner = [
            'name' => $settings['company_name'],
            'address' => $settings['company_address'],
            'phone' => $settings['company_phone'],
            'logo' => $settings['company_logo'],
            'bank_name' => $settings['bank_name'],
            'bank_account' => $settings['bank_account'],
            'bank_holder' => $settings['bank_holder']
        ];
    }
}
function tanggal_indo($date) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $hari = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    $timestamp = strtotime($date);
    $dayName = $hari[date('l', $timestamp)];
    $dayDate = date('d', $timestamp);
    $monthName = $bulan[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    return [
        'hari' => $dayName,
        'tanggal' => "$dayDate $monthName $year"
    ];
}
$tgl = tanggal_indo($trans['transaction_date']);
$logoUrl = null;
$logoVal = $partner['logo'] ?? '';
if (!empty($logoVal)) {
    if (preg_match('/^https?:\\/\\//i', $logoVal)) {
        $logoUrl = $logoVal;
    } else {
        $candidates = [
            '../uploads/logos/' . ltrim($logoVal, '/'),
            '../upload/logos/' . ltrim($logoVal, '/'),
            '../' . ltrim($logoVal, '/'),
            './' . ltrim($logoVal, '/'),
            '../public/' . ltrim($logoVal, '/')
        ];
        foreach ($candidates as $rel) {
            if (file_exists(__DIR__ . '/' . $rel)) { $logoUrl = $rel; break; }
        }
    }
}
if (empty($logoUrl)) {
    foreach (['logo.png','logo.jpg','logo.jpeg'] as $name) {
        foreach (['../','../public/','../uploads/logos/'] as $dir) {
            if (file_exists(__DIR__ . '/' . $dir . $name)) { $logoUrl = $dir . $name; break 2; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kwitansi #<?= $trans['id'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 20px; background: #525659; line-height: 1.5; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        #content { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 15mm; color: #333; border: none; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.5); position: relative; }
        .header { text-align: center; border-bottom: 2px dashed #ccc; padding-bottom: 20px; margin-bottom: 30px; }
        .header h2 { margin: 0; color: #333; text-transform: uppercase; letter-spacing: 2px; }
        .header p { margin: 5px 0 0; color: #666; font-size: 14px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .info-table td { padding: 10px 0; vertical-align: top; font-size: 14px; }
        .info-label { width: 180px; font-weight: bold; color: #555; }
        .info-sep { width: 20px; text-align: center; }
        .info-value { border-bottom: 1px dotted #ccc; color: #333; }
        .amount-box { margin-top: 30px; background: #f8f9fa; padding: 20px; text-align: center; border: 2px solid #333; border-radius: 4px; }
        .currency { font-size: 20px; vertical-align: top; margin-right: 5px; }
        .value-large { font-size: 32px; font-weight: bold; letter-spacing: 1px; }
        .remaining-info { margin-top: 15px; font-size: 13px; color: #e74c3c; font-style: italic; text-align: right; }
        .footer-section { margin-top: 60px; }
        .signature-box { float: right; width: 250px; text-align: center; }
        .signature-line { margin-top: 80px; border-top: 1px solid #333; padding-top: 10px; font-weight: bold; font-size: 14px; }
        .clearfix::after { content: ""; display: table; clear: both; }
        @page { size: A4; margin: 0; }
        @media print { body { background: #fff; margin: 0; padding: 0; } #content { width: 100%; margin: 0; padding: 15mm; border: none; box-shadow: none; page-break-after: always; } .no-print { display: none; } .amount-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div id="content">
        <div class="header">
            <div style="display:flex; align-items:center; justify-content:center; gap:12px;">
                <div class="logo" style="width:56px; height:56px; border-radius:6px; overflow:hidden; background:#f1f1f1; display:flex; align-items:center; justify-content:center;">
                    <?php if (!empty($logoUrl)): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="max-width: 100%; max-height: 100%;">
                    <?php else: ?>
                        <span>LOGO</span>
                    <?php endif; ?>
                </div>
                <div>
                    <h2 style="margin:0;">Kwitansi Pembayaran</h2>
                    <p style="margin:4px 0 0;"><?= htmlspecialchars($partner['name'] ?? 'Perusahaan') ?> • <?= htmlspecialchars($partner['address'] ?? '-') ?> • Telp: <?= htmlspecialchars($partner['phone'] ?? '-') ?></p>
                </div>
            </div>
        </div>
        <table class="info-table">
            <tr><td class="info-label">Nomor Kwitansi</td><td class="info-sep">:</td><td class="info-value">#<?= $trans['id'] ?></td></tr>
            <tr><td class="info-label">Tanggal</td><td class="info-sep">:</td><td class="info-value"><?= $tgl['tanggal'] ?></td></tr>
            <tr><td class="info-label">Telah diterima dari</td><td class="info-sep">:</td><td class="info-value"><?= htmlspecialchars($trans['client_name'] ?? '') ?></td></tr>
            <tr><td class="info-label">Untuk pembayaran</td><td class="info-sep">:</td><td class="info-value"><?= htmlspecialchars($trans['service_name'] ?? '') ?></td></tr>
            <tr><td class="info-label">Catatan</td><td class="info-sep">:</td><td class="info-value"><?= htmlspecialchars($trans['note'] ?? '') ?></td></tr>
        </table>
        <div class="amount-box">
            <span class="currency">Rp</span>
            <span class="value-large"><?= number_format($trans['amount'], 0, ',', '.') ?></span>
        </div>
        <div class="remaining-info">Sisa tagihan: Rp <?= number_format($remaining, 0, ',', '.') ?></div>
        <div class="footer-section clearfix">
            <div class="signature-box">
                <div><?= htmlspecialchars($partner['name'] ?? 'Perusahaan') ?></div>
                <div class="signature-line"><?= htmlspecialchars($partner['bank_holder'] ?? 'Penanggung Jawab') ?></div>
            </div>
        </div>
        <div class="no-print" style="margin-top: 16px; text-align: center;">
            <button onclick="window.print()" class="btn btn-primary">Cetak</button>
            <button id="savePdfBtn" class="btn btn-outline-secondary">Simpan PDF</button>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    (function(){
        var btn = document.getElementById('savePdfBtn');
        if (btn) {
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var element = document.getElementById('content');
                var opt = {
                    margin:       0,
                    filename:     'kwitansi_<?= $trans['id'] ?>.pdf',
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 2 },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };
                html2pdf().set(opt).from(element).save();
            });
        }
    })();
    </script>
</body>
</html>
