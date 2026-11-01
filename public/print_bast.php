<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$id = $_GET['id'] ?? null;
if (!$id) die("ID Invalid");
$stmt = $pdo->prepare("SELECT p.*, c.address as client_address, c.phone as client_phone FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) die("Proyek tidak ditemukan");
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
            'logo' => $settings['company_logo']
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
    return "$dayDate $monthName $year";
}
$today = tanggal_indo(date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BAST Proyek #<?= $project['id'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 20px; background: #525659; line-height: 1.5; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        #content { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 15mm; background: #fff; box-shadow: 0 0 10mm rgba(0,0,0,0.2); }
        .header { display: flex; align-items: center; margin-bottom: 15px; }
        .logo { width: 80px; height: 80px; border-radius: 6px; overflow: hidden; margin-right: 15px; background: #f1f1f1; display: flex; align-items: center; justify-content: center; }
        .company { flex: 1; }
        .company h1 { margin: 0; font-size: 20px; }
        .company p { margin: 0; color: #666; font-size: 13px; }
        .title { text-align: center; margin: 10px 0 20px; }
        .title h2 { margin: 0; font-size: 18px; }
        .meta { font-size: 13px; color: #333; margin-bottom: 10px; }
        .box { border: 1px solid #eee; padding: 10px; border-radius: 6px; margin-bottom: 12px; }
        .section-title { margin: 16px 0 8px; font-size: 14px; text-transform: uppercase; color: #888; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; border-bottom: 1px solid #eee; font-size: 13px; text-align: left; }
        th { background: #fafafa; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; text-align: center; }
        .btn-print { margin-top: 10px; display: inline-block; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; background: #f8f8f8; color: #333; text-decoration: none; }
        @media print { body { background: #fff; padding: 0; } #content { box-shadow: none; } .btn-print { display: none; } }
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
        <div class="title">
            <h2>BERITA ACARA SERAH TERIMA PEKERJAAN</h2>
            <div class="meta">Tanggal: <?= $today ?> • Proyek #<?= $project['id'] ?></div>
        </div>
        <div class="section-title">Pihak-pihak</div>
        <div class="box">
            <div><strong>Pemberi Pekerjaan:</strong> <?= htmlspecialchars($project['client_name'] ?? '') ?>, <?= htmlspecialchars($project['client_address'] ?? '-') ?>, Telp: <?= htmlspecialchars($project['client_phone'] ?? '-') ?></div>
            <div><strong>Penerima Pekerjaan:</strong> <?= htmlspecialchars($partner['name'] ?? 'Perusahaan') ?></div>
        </div>
        <div class="section-title">Detail Pekerjaan</div>
        <div class="box">
            <div><strong>Layanan:</strong> <?= htmlspecialchars($project['service_name'] ?? '') ?></div>
            <div><strong>Keterangan:</strong> <?= htmlspecialchars($project['description'] ?? '-') ?></div>
            <div><strong>Total Nilai:</strong> Rp <?= number_format($project['total_price'], 0, ',', '.') ?></div>
        </div>
        <div class="section-title">Pernyataan</div>
        <div class="box">
            <p>Dengan ini dinyatakan bahwa pekerjaan telah diserahterimakan dari Pemberi Pekerjaan kepada Penerima Pekerjaan dengan kondisi baik dan sesuai kesepakatan.</p>
        </div>
        <div class="section-title">Tanda Tangan</div>
        <table>
            <thead><tr><th style="width:50%;">Pemberi Pekerjaan</th><th>Penerima Pekerjaan</th></tr></thead>
            <tbody>
                <tr>
                    <td style="height:120px; vertical-align:bottom;"><?= htmlspecialchars($project['client_name'] ?? '') ?></td>
                    <td style="height:120px; vertical-align:bottom;"><?= htmlspecialchars($partner['bank_holder'] ?? $partner['name'] ?? 'Penanggung Jawab') ?></td>
                </tr>
            </tbody>
        </table>
        <div class="footer">
            <p>Dokumen ini dibuat secara elektronik.</p>
            <a href="#" onclick="window.print()" class="btn-print">Cetak BAST</a>
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
                    filename:     'bast_<?= $project['id'] ?>.pdf',
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
