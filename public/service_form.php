<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';

$id = $_GET['id'] ?? null;
$service = null;

if ($id) {
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND company_id = ?");
$stmt->execute([$id, currentCompanyId()]);
    $service = $stmt->fetch();
    if (!$service) die("Layanan tidak ditemukan");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $default_price = $_POST['default_price'] ?? 0;

    if (empty($name)) {
        $error = "Nama Layanan wajib diisi";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE services SET name=?, category=?, default_price=?, company_id=? WHERE id=?");
                $stmt->execute([$name, $category, $default_price, currentCompanyId(), $id]);
                logActivity($pdo, 'UPDATE_SERVICE', "Updated Service: $name");
            } else {
                $stmt = $pdo->prepare("INSERT INTO services (name, category, default_price, company_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $category, $default_price, currentCompanyId()]);
                logActivity($pdo, 'ADD_SERVICE', "Added Service: $name");
            }
            header("Location: services.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

$pageTitle = $id ? "Edit Layanan" : "Tambah Layanan";
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark"><?= $pageTitle ?></h3>
        <p class="text-muted mb-0 small"><?= $id ? 'Edit data layanan' : 'Tambahkan layanan baru' ?></p>
    </div>
    <a href="services.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nama Layanan <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($service['name'] ?? $_POST['name'] ?? '') ?>">
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Kategori</label>
                    <select name="category" class="form-select">
                        <option value="Pendirian Badan Usaha" <?= ($service['category'] ?? '') === 'Pendirian Badan Usaha' ? 'selected' : '' ?>>Pendirian Badan Usaha</option>
                        <option value="Perizinan" <?= ($service['category'] ?? '') === 'Perizinan' ? 'selected' : '' ?>>Perizinan</option>
                        <option value="Lainnya" <?= ($service['category'] ?? '') === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Harga Dasar (Rp)</label>
                    <input type="number" name="default_price" class="form-control" value="<?= $service['default_price'] ?? $_POST['default_price'] ?? 0 ?>">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="services.php" class="btn btn-light">Batal</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; 