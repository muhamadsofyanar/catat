<?php
require_once __DIR__ . '/../includes/auth.php';
ob_start(); // Start buffering
require __DIR__ . '/../config/db.php';
// Clean any previous output (whitespace/newlines from includes)
ob_clean(); 

$id = $_GET['id'] ?? null;
if (!$id) die("ID Invalid");
$stmt = $pdo->prepare("SELECT p.*, c.address as client_address, c.phone as client_phone 
                       FROM projects p 
                       LEFT JOIN clients c ON p.client_id = c.id 
                       WHERE p.id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) die("Proyek tidak ditemukan");
if (empty($project['client_address'])) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE name = ?");
    $stmt->execute([$project['client_name']]);
    $c = $stmt->fetch();
    if ($c) {
        $project['client_address'] = $c['address'];
        $project['client_phone'] = $c['phone'];
    }
}
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE project_id = ? AND type = 'income'");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();
$stmtFees = $pdo->prepare("SELECT * FROM project_fees WHERE project_id = ?");
$stmtFees->execute([$id]);
$fees = $stmtFees->fetchAll();
$partner = null;
if (!empty($project['partner_id'])) {
    $stmtP = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
    $stmtP->execute([$project['partner_id']]);
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
$total_paid = array_sum(array_column($payments, 'amount'));
$remaining = $project['total_price'] - $total_paid;
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
$today = tanggal_indo(date('Y-m-d'));
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= $project['id'] ?></title>
    
    <!-- Bootstrap removed to prevent print layout conflicts -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; } /* Reset globally */
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            background: #525659;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        #content {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15mm;
            background: #fff;
            box-shadow: 0 0 10mm rgba(0,0,0,0.2);
        }
        .header { display: flex; align-items: center; margin-bottom: 15px; }
        .header .logo { width: 80px; height: 80px; border-radius: 6px; overflow: hidden; margin-right: 15px; background: #f1f1f1; display: flex; align-items: center; justify-content: center; }
        .header .company { flex: 1; }
        .header .company h1 { margin: 0; font-size: 22px; }
        .header .company p { margin: 0; color: #666; font-size: 13px; }
        .divider { border-top: 2px dashed #ddd; margin: 10px 0; }
        .invoice-title { display: flex; justify-content: space-between; align-items: center; margin: 10px 0 20px; }
        .invoice-title h2 { margin: 0; font-size: 18px; }
        .meta { font-size: 13px; color: #333; }
        .meta .label { color: #666; }
        .section-title { margin: 20px 0 8px; font-size: 14px; text-transform: uppercase; color: #888; }
        .box { border: 1px solid #eee; padding: 10px; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; border-bottom: 1px solid #eee; font-size: 13px; }
        th { background: #fafafa; text-align: left; }
        .text-right { text-align: right; }
        .summary { margin-top: 15px; }
        .summary .line { display: flex; justify-content: space-between; padding: 6px 0; }
        .summary .line.total { font-weight: bold; font-size: 16px; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; text-align: center; }
        .btn-print { margin-top: 10px; display: inline-block; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; background: #f8f8f8; color: #333; text-decoration: none; }
        
        @page {
            size: A4 portrait;
            margin: 0;
        }
        
        @media print {
            html, body {
                width: 210mm;
                height: auto;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible;
            }
            body { 
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            #content { 
                width: 100%;
                margin: 0 !important;
                border: none;
                box-shadow: none; 
                padding: 10mm 15mm !important;
                position: relative;
                top: 0;
                left: 0;
            }
            .header {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>
    <div id="content">
        <div class="header">
            <div class="logo">
                <?php if (!empty($logoUrl)): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="max-width: 100%; max-height: 100%;">
                <?php else: ?>
                    <span>LOGO</span>
                <?php endif; ?>
            </div>
            <div class="company">
                <h1><?= htmlspecialchars($partner['name'] ?? 'Perusahaan') ?></h1>
                <p><?= htmlspecialchars($partner['address'] ?? '-') ?></p>
                <p>Telp: <?= htmlspecialchars($partner['phone'] ?? '-') ?></p>
            </div>
        </div>
        <div class="invoice-title">
            <h2>INVOICE / TAGIHAN</h2>
            <div class="meta">
                <div><span class="label">Tanggal:</span> <?= $today['tanggal'] ?></div>
                <div><span class="label">No. Proyek:</span> #<?= $project['id'] ?></div>
            </div>
        </div>
        <div class="divider"></div>
        <div class="section-title">Kepada</div>
        <div class="box">
            <div><strong><?= htmlspecialchars($project['client_name'] ?? '') ?></strong></div>
            <div><?= htmlspecialchars($project['client_address'] ?? '-') ?></div>
            <div>Telp: <?= htmlspecialchars($project['client_phone'] ?? '-') ?></div>
        </div>
        <div class="section-title">Detail Proyek</div>
        <div class="box">
            <div><strong>Layanan:</strong> <?= htmlspecialchars($project['service_name'] ?? '') ?></div>
            <div><strong>Keterangan:</strong> <?= htmlspecialchars($project['description'] ?? '-') ?></div>
            <div><strong>Total Nilai:</strong> Rp <?= number_format($project['total_price'], 0, ',', '.') ?></div>
        </div>
        <div class="section-title">Pembayaran</div>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $pay): ?>
                <tr>
                    <td><?= htmlspecialchars($pay['transaction_date'] ?? '') ?></td>
                    <td><?= htmlspecialchars($pay['note'] ?? '') ?></td>
                    <td class="text-right">Rp <?= number_format($pay['amount'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                <tr><td colspan="3" class="text-right">Belum ada pembayaran</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="summary">
            <div class="line"><span>Total Pembayaran</span><span>Rp <?= number_format($total_paid, 0, ',', '.') ?></span></div>
            <div class="line"><span>Sisa Tagihan</span><span>Rp <?= number_format($remaining, 0, ',', '.') ?></span></div>
            <div class="line total"><span>Total Tagihan</span><span>Rp <?= number_format($project['total_price'], 0, ',', '.') ?></span></div>
        </div>
        <div class="divider"></div>
        <div class="section-title">Informasi Pembayaran</div>
        <div class="box">
            <div><strong>Bank:</strong> <?= htmlspecialchars($partner['bank_name'] ?? '-') ?></div>
            <div><strong>No. Rekening:</strong> <?= htmlspecialchars($partner['bank_account'] ?? '-') ?></div>
            <div><strong>Atas Nama:</strong> <?= htmlspecialchars($partner['bank_holder'] ?? '-') ?></div>
        </div>
        <div class="footer">
            <p>Terima kasih atas kepercayaan Anda.</p>
            <a href="#" onclick="window.print()" class="btn-print">Cetak Invoice</a>
            <a href="#" id="savePdfBtn" class="btn-print">Simpan PDF</a>
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
                    filename:     'invoice_<?= $project['id'] ?>.pdf',
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
