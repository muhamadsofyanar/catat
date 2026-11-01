<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';

$id = $_GET['id'] ?? null;
$client = null;

if ($id) {
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND company_id = ?");
$stmt->execute([$id, currentCompanyId()]);
    $client = $stmt->fetch();
    if (!$client) {
        die("Klien tidak ditemukan");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $npwp = trim($_POST['npwp'] ?? '');

    if (empty($name)) {
        $error = "Nama klien wajib diisi";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE clients SET name = ?, phone = ?, email = ?, address = ?, npwp = ?, company_id = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $email, $address, $npwp, currentCompanyId(), $id]);
                logActivity($pdo, 'UPDATE_CLIENT', "Updated Client: $name");
            } else {
                $stmt = $pdo->prepare("INSERT INTO clients (name, phone, email, address, npwp, company_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $email, $address, $npwp, currentCompanyId()]);
                logActivity($pdo, 'ADD_CLIENT', "Added Client: $name");
            }
            header("Location: clients.php");
            exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false || $e->getCode() == 23000) {
                $error = "Nama klien sudah terdaftar";
            } else {
                $error = "Terjadi kesalahan database: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = $id ? "Edit Klien" : "Tambah Klien";
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark"><?= $pageTitle ?></h3>
        <p class="text-muted mb-0 small"><?= $id ? 'Edit data klien' : 'Tambahkan klien baru' ?></p>
    </div>
    <a href="clients.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nama Klien <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($client['name'] ?? $_POST['name'] ?? '') ?>">
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">No. Telepon / HP</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($client['phone'] ?? $_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($client['email'] ?? $_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Alamat</label>
                <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($client['address'] ?? $_POST['address'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">NPWP (Opsional)</label>
                <input type="text" name="npwp" class="form-control" value="<?= htmlspecialchars($client['npwp'] ?? $_POST['npwp'] ?? '') ?>">
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="clients.php" class="btn btn-light">Batal</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; 