<?php
$root = '../';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if ($_SESSION['role'] !== 'admin') {
    die("Akses Ditolak: Hanya Admin yang dapat mengubah pengaturan sistem.");
}
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch();
if (!$settings) {
        $settings = [
        'id' => null,
        'company_name' => '',
        'company_address' => '',
        'company_phone' => '',
        'company_logo' => '',
        'bank_name' => '',
        'bank_account' => '',
        'bank_holder' => '',
            'reminder_wa_template' => "Halo {{client_name}}, kami mengingatkan tagihan layanan {{service_name}} sebesar {{remaining}}. Jatuh tempo: {{due_date}}.\n\nPembayaran: Bank {{bank_name}}, Rekening {{bank_account}} a/n {{bank_holder}}.\nTerima kasih.",
        'reminder_email_subject' => 'Pengingat Tagihan: {{service_name}}',
            'reminder_email_body' => "Halo {{client_name}},\n\nKami mengingatkan tagihan sebesar {{remaining}} untuk layanan {{service_name}}.\nJatuh tempo: {{due_date}}.\n\nPembayaran: Bank {{bank_name}}, Rekening {{bank_account}} a/n {{bank_holder}}.\n\nTerima kasih."
    ];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = $_POST['company_name'];
    $company_address = $_POST['company_address'];
    $company_phone = $_POST['company_phone'];
    $bank_name = $_POST['bank_name'];
    $bank_account = $_POST['bank_account'];
    $bank_holder = $_POST['bank_holder'];
    $reminder_wa_template = $_POST['reminder_wa_template'] ?? $settings['reminder_wa_template'];
    $reminder_email_subject = $_POST['reminder_email_subject'] ?? $settings['reminder_email_subject'];
    $reminder_email_body = $_POST['reminder_email_body'] ?? $settings['reminder_email_body'];
    $reminder_min_interval_days = isset($_POST['reminder_min_interval_days']) ? (int)$_POST['reminder_min_interval_days'] : (int)($settings['reminder_min_interval_days'] ?? 7);
    $payment_link = $_POST['payment_link'] ?? ($settings['payment_link'] ?? '');
    $company_logo = $settings['company_logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
            $logoDir = __DIR__ . '/../uploads/logos';
            if (!is_dir($logoDir)) mkdir($logoDir, 0750, true);
            $target = $logoDir . '/company.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $company_logo = '../uploads/logos/company.' . $ext;
            }
        }
    }
    if ($settings['id']) {
        $stmt = $pdo->prepare("UPDATE settings SET company_name = ?, company_address = ?, company_phone = ?, company_logo = ?, bank_name = ?, bank_account = ?, bank_holder = ?, reminder_wa_template = ?, reminder_email_subject = ?, reminder_email_body = ?, reminder_min_interval_days = ?, payment_link = ? WHERE id = ?");
        $stmt->execute([$company_name, $company_address, $company_phone, $company_logo, $bank_name, $bank_account, $bank_holder, $reminder_wa_template, $reminder_email_subject, $reminder_email_body, $reminder_min_interval_days, $payment_link, $settings['id']]);
        logActivity($pdo, 'UPDATE_SETTINGS', "Updated System Settings");
    } else {
        $stmt = $pdo->prepare("INSERT INTO settings (company_name, company_address, company_phone, company_logo, bank_name, bank_account, bank_holder, reminder_wa_template, reminder_email_subject, reminder_email_body, reminder_min_interval_days, payment_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$company_name, $company_address, $company_phone, $company_logo, $bank_name, $bank_account, $bank_holder, $reminder_wa_template, $reminder_email_subject, $reminder_email_body, $reminder_min_interval_days, $payment_link]);
        logActivity($pdo, 'INIT_SETTINGS', "Initialized System Settings");
    }
    header("Location: settings.php?saved=1");
    exit;
}
$pageTitle = "Pengaturan Perusahaan";
require __DIR__ . '/../includes/header.php';
?>
<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0">
                <div class="card-header bg-white border-bottom-0 py-3 fw-bold">
                    <i class="bi bi-gear me-2"></i>Pengaturan Identitas Perusahaan
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-end mb-3">
                        <a href="services.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-list-check"></i> Manage Services & Biaya Baku
                        </a>
                    </div>
                    <?php if(isset($_GET['saved'])): ?>
                        <div class="alert alert-success">Pengaturan berhasil disimpan!</div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <h6 class="mb-3 text-primary">Identitas Umum</h6>
                        <div class="mb-3">
                            <label class="form-label">Nama Perusahaan / Brand</label>
                            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat Lengkap</label>
                            <textarea name="company_address" class="form-control" rows="2"><?= htmlspecialchars($settings['company_address'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Logo Perusahaan</label>
                            <div class="d-flex align-items-center gap-3">
                                <?php if(!empty($settings['company_logo'])): ?>
                                    <img src="<?= $settings['company_logo'] ?>?t=<?= time() ?>" alt="Logo" style="height: 50px;">
                                <?php endif; ?>
                                <input type="file" name="logo" class="form-control form-control-sm">
                            </div>
                            <small class="text-muted">Format: PNG/JPG. Nama file akan otomatis diubah menjadi logo.png/jpg</small>
                        </div>
                        <hr>
                        <h6 class="mb-3 text-primary">Info Rekening (Untuk Invoice)</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Nama Bank</label>
                                <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($settings['bank_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nomor Rekening</label>
                                <input type="text" name="bank_account" class="form-control" value="<?= htmlspecialchars($settings['bank_account'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Atas Nama</label>
                                <input type="text" name="bank_holder" class="form-control" value="<?= htmlspecialchars($settings['bank_holder'] ?? '') ?>">
                            </div>
                        </div>
                        <hr>
                        <h6 class="mb-3 text-primary">Pemeliharaan Sistem</h6>
                        <div class="alert alert-light border d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-dark"><i class="bi bi-database me-2"></i>Backup Database</strong><br>
                                <small class="text-muted">Download salinan database sistem (SQLite) untuk cadangan keamanan.</small>
                            </div>
                            <a href="backup_db.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-2"></i>Download Backup</a>
                        </div>
                        <hr>
                        <h6 class="mb-3 text-primary">Template Pengingat Tagihan</h6>
                        <div class="mb-3">
                            <label class="form-label">WA Message Template</label>
                            <textarea name="reminder_wa_template" class="form-control" rows="3"><?= htmlspecialchars($settings['reminder_wa_template'] ?? '') ?></textarea>
                            <small class="text-muted">Gunakan placeholder: {{client_name}}, {{service_name}}, {{remaining}}, {{due_date}}, {{bank_name}}, {{bank_account}}, {{bank_holder}}, {{invoice_url}}, {{payment_link}}, {{termin}}</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Subject Template</label>
                            <input type="text" name="reminder_email_subject" class="form-control" value="<?= htmlspecialchars($settings['reminder_email_subject'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Body Template</label>
                            <textarea name="reminder_email_body" class="form-control" rows="4"><?= htmlspecialchars($settings['reminder_email_body'] ?? '') ?></textarea>
                            <small class="text-muted">Placeholder sama seperti WA</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jeda Minimal Pengingat (hari)</label>
                            <input type="number" min="1" name="reminder_min_interval_days" class="form-control" value="<?= htmlspecialchars($settings['reminder_min_interval_days'] ?? 7) ?>">
                            <small class="text-muted">Sistem hanya menampilkan kandidat pengingat jika jeda minimal ini terpenuhi.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Default Payment Link</label>
                            <input type="text" name="payment_link" class="form-control" value="<?= htmlspecialchars($settings['payment_link'] ?? '') ?>" placeholder="https://pembayaran.example/invoice">
                            <small class="text-muted">Digunakan untuk placeholder {{payment_link}} jika tidak ada link spesifik proyek.</small>
                        </div>
                        <?php
                        $previewVars = [
                            '{{client_name}}' => 'Contoh Klien',
                            '{{service_name}}' => 'Pendirian PT',
                            '{{remaining}}' => formatRupiah(15000000),
                            '{{due_date}}' => date('Y-m-d', strtotime('+7 days')),
                            '{{bank_name}}' => $settings['bank_name'] ?? 'Bank Contoh',
                            '{{bank_account}}' => $settings['bank_account'] ?? '1234567890',
                            '{{bank_holder}}' => $settings['bank_holder'] ?? 'Nama Pemilik',
                            '{{invoice_url}}' => 'http://localhost/legavoice/public/print_invoice.php?id=123',
                            '{{payment_link}}' => $settings['payment_link'] ?? 'https://pembayaran.example/invoice/123',
                            '{{termin}}' => '-'
                        ];
                        $waPreview = strtr($settings['reminder_wa_template'] ?? '', $previewVars);
                        $emailSubjectPreview = strtr($settings['reminder_email_subject'] ?? '', $previewVars);
                        $emailBodyPreview = strtr($settings['reminder_email_body'] ?? '', $previewVars);
                        ?>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white py-2"><strong>Preview WA</strong></div>
                            <div class="card-body">
                                <pre class="bg-light p-3 rounded" style="white-space: pre-wrap;"><?= htmlspecialchars($waPreview) ?></pre>
                            </div>
                        </div>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white py-2"><strong>Preview Email</strong></div>
                            <div class="card-body">
                                <div class="mb-2"><strong>Subject:</strong> <?= htmlspecialchars($emailSubjectPreview) ?></div>
                                <pre class="bg-light p-3 rounded" style="white-space: pre-wrap;"><?= htmlspecialchars($emailBodyPreview) ?></pre>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Pengaturan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 