<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
$stmt = $pdo->prepare('SELECT role, company_id FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$actor = $stmt->fetch();
$stmt->closeCursor();
if (!$actor || $actor['role'] !== 'admin' || (int)$actor['company_id'] !== 1) {
    http_response_code(403);
    exit('Akses ditolak. Import hanya tersedia untuk admin perusahaan utama.');
}
header('Cache-Control: private, no-store');
$importer = new \Core\DataImporter($pdo);
if (isset($_GET['backup'])) {
    try {
        $file = $importer->backupPath((int)$_GET['backup']);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="before-import.sqlite"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
    } catch (RuntimeException $e) { http_response_code(404); echo 'Backup tidak ditemukan.'; }
    exit;
}
$_SESSION['import_csrf'] = $_SESSION['import_csrf'] ?? bin2hex(random_bytes(32));
$staging = $importer->stagingDirectory();
function pendingImportPath(string $directory): ?string {
    $pending = $_SESSION['pending_import'] ?? null;
    if (!is_array($pending) || !preg_match('/^[a-f0-9]{32}\.json$/D', $pending['file'] ?? '')) return null;
    return $directory . '/' . $pending['file'];
}
function discardPendingImport(string $directory): void {
    $file = pendingImportPath($directory);
    if ($file && is_file($file)) unlink($file);
    unset($_SESSION['pending_import']);
}
$error = '';
$flash = $_SESSION['import_flash'] ?? null;
unset($_SESSION['import_flash']);
if (isset($_SESSION['pending_import']) && time() - ($_SESSION['pending_import']['created_at'] ?? 0) > 1800) discardPendingImport($staging);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf = $_POST['csrf'] ?? null;
        if (!is_string($csrf) || !hash_equals($_SESSION['import_csrf'], $csrf)) {
            http_response_code(403);
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'cancel') {
            discardPendingImport($staging);
            header('Location: import_data.php'); exit;
        }
        if ($action === 'preview') {
            $importer->requireEmptyTarget();
            $upload = $_FILES['import_file'] ?? null;
            if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > \Core\DataImporter::MAX_BYTES || !is_uploaded_file($upload['tmp_name'])) throw new InvalidArgumentException('Pilih file JSON import, maksimal 20 MB. Jika unggahan gagal, periksa batas upload pada server.');
            discardPendingImport($staging);
            $name = bin2hex(random_bytes(16)) . '.json';
            $path = $staging . '/' . $name;
            if (!move_uploaded_file($upload['tmp_name'], $path)) throw new RuntimeException('File import tidak dapat disimpan.');
            chmod($path, 0600);
            try { $summary = $importer->inspect($path); }
            catch (\Throwable $e) { unlink($path); throw $e; }
            $_SESSION['pending_import'] = ['file' => $name, 'created_at' => time(), 'hash' => $summary['package_hash']];
            header('Location: import_data.php'); exit;
        }
        if ($action !== 'import') throw new InvalidArgumentException('Aksi tidak valid.');
        $path = pendingImportPath($staging);
        if (!$path || !is_file($path)) throw new InvalidArgumentException('Pilih file dan periksa ringkasannya terlebih dahulu.');
        if (($_POST['confirmation'] ?? '') !== 'IMPORT DATA LAMA') throw new InvalidArgumentException('Ketik IMPORT DATA LAMA untuk melanjutkan.');
        if (!hash_equals($_SESSION['pending_import']['hash'], hash_file('sha256', $path))) throw new InvalidArgumentException('File import berubah. Unggah kembali file yang benar.');
        $result = $importer->apply($path, $_SESSION['username']);
        $_SESSION['import_flash'] = $result['counts'];
        discardPendingImport($staging);
        header('Location: import_data.php'); exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        error_log('Data import failed: ' . $e->getMessage());
        $error = $e instanceof PDOException ? 'Import gagal pada database. Data tidak diterapkan; periksa log aplikasi.' : $e->getMessage();
    }
}
$preview = null;
$path = pendingImportPath($staging);
if ($path && is_file($path)) {
    try { $preview = $importer->inspect($path); }
    catch (\Throwable $e) { $error = 'File import tidak lagi valid. Unggah ulang file Anda.'; discardPendingImport($staging); }
}
$blocked = '';
try { $importer->requireEmptyTarget(); } catch (RuntimeException $e) { $blocked = $e->getMessage(); }
$targetState = $importer->targetState();
$history = $importer->history();
$labels = ['companies' => 'Perusahaan', 'clients' => 'Klien', 'partners' => 'Mitra', 'services' => 'Layanan', 'service_costs' => 'Biaya standar layanan', 'projects' => 'Proyek', 'project_fees' => 'Biaya tambahan proyek', 'transactions' => 'Transaksi', 'project_documents' => 'Dokumen tercatat', 'settings' => 'Pengaturan'];
$pageTitle = 'Import Data Lama';
require __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8">
    <h3 class="fw-bold mb-3">Import Data Lama</h3>
    <p>Unggah file <strong>legavoice-data-lama.json</strong> untuk memindahkan data lama ke aplikasi baru. Akun dan password login Anda tetap digunakan.</p>
    <?php if ($flash): ?>
    <div class="alert alert-success">Import berhasil: <strong><?= (int)$flash['projects'] ?> proyek, <?= (int)$flash['transactions'] ?> transaksi, <?= (int)$flash['clients'] ?> klien</strong>. <a href="index.php">Buka Dashboard</a>.</div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if (!$blocked && $targetState['empty_internal_ids']): ?>
    <div class="alert alert-info">Terdapat <?= count($targetState['empty_internal_ids']) ?> proyek internal kosong bawaan aplikasi. Proyek kosong ini tidak menghalangi import dan akan digantikan oleh data lama setelah backup dibuat.</div>
    <?php endif; ?>
    <?php if ($blocked): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($blocked, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="card border-0 mb-4"><div class="card-body">
        <h5>Data yang terdeteksi pada aplikasi ini</h5>
        <table class="table table-sm"><thead><tr><th>Jenis data</th><th class="text-end">Jumlah</th></tr></thead><tbody>
        <?php foreach ($targetState['business_counts'] as $table => $count): ?>
        <tr><td><?= $table === 'projects' ? 'Proyek bisnis' : $labels[$table] ?></td><td class="text-end"><?= (int)$count ?></td></tr>
        <?php endforeach; ?>
        <tr><td>Proyek internal kosong bawaan</td><td class="text-end"><?= count($targetState['empty_internal_ids']) ?></td></tr>
        </tbody></table>
        <p>Import tidak menimpa data bisnis yang sudah ada. Periksa daftar di atas terlebih dahulu; jangan hapus data yang masih diperlukan.</p>
        <a class="btn btn-outline-primary" href="projects.php?status=all">Lihat Proyek</a>
        <a class="btn btn-outline-secondary" href="clients.php">Lihat Klien</a>
    </div></div>
    <?php elseif ($preview): ?>
    <div class="card border-0 mb-4"><div class="card-body">
        <h5>2. Periksa data sebelum import</h5>
        <table class="table table-sm"><thead><tr><th>Data</th><th class="text-end">Jumlah</th></tr></thead><tbody>
        <?php foreach ($preview['counts'] as $table => $count): ?><tr><td><?= $labels[$table] ?></td><td class="text-end"><?= (int)$count ?></td></tr><?php endforeach; ?>
        <tr><td>Berkas unggahan dan logo</td><td class="text-end"><?= (int)$preview['file_count'] ?></td></tr>
        </tbody></table>
        <?php foreach ($preview['notes'] as $note): ?><p class="small text-muted mb-2"><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?>
        <p>Aplikasi membuat backup sebelum import. Import mengganti identitas perusahaan, mitra bawaan, dan pengaturan dengan data lama. Proses ini hanya berjalan bila aplikasi belum berisi data bisnis.</p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['import_csrf']) ?>">
            <label for="confirmation" class="form-label">Ketik <strong>IMPORT DATA LAMA</strong> untuk menerapkan data.</label>
            <input id="confirmation" name="confirmation" class="form-control mb-3" required autocomplete="off">
            <button name="action" value="import" class="btn btn-primary">Import Sekarang</button>
            <button name="action" value="cancel" class="btn btn-outline-secondary" formnovalidate>Batal</button>
        </form>
    </div></div>
    <?php else: ?>
    <div class="card border-0 mb-4"><div class="card-body">
        <h5>1. Pilih file data lama</h5>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['import_csrf']) ?>">
            <input type="hidden" name="action" value="preview">
            <input type="file" name="import_file" class="form-control mb-3" accept=".json,application/json" required>
            <p class="small text-muted">Maksimal 20 MB. Simpan file data ini secara pribadi; jangan unggah ke repository GitHub.</p>
            <button class="btn btn-primary">Periksa File</button>
        </form>
    </div></div>
    <?php endif; ?>
    <?php if ($history): ?>
    <div class="card border-0"><div class="card-body"><h5>Riwayat import</h5>
    <?php foreach ($history as $item): ?>
        <div class="d-flex flex-wrap justify-content-between gap-2 border-bottom py-2">
            <span><?= htmlspecialchars($item['created_at']) ?> — <?= htmlspecialchars($item['username']) ?></span>
            <a href="import_data.php?backup=<?= (int)$item['id'] ?>">Unduh backup sebelum import</a>
        </div>
    <?php endforeach; ?>
    <p class="small text-muted mt-3 mb-0">Backup sebelum import berisi database tujuan sebelum data lama dimasukkan. Simpan ZIP sumber asli sebagai cadangan data lama.</p>
    </div></div>
    <?php endif; ?>
</div></div>
<?php require __DIR__ . '/../includes/footer.php';
