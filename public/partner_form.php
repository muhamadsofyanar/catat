<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';

$id = $_GET['id'] ?? null;
$partner = null;

if ($id) {
$stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND company_id = ?");
$stmt->execute([$id, currentCompanyId()]);
    $partner = $stmt->fetch();
    if (!$partner) die("Mitra tidak ditemukan");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $bank_name = trim($_POST['bank_name']);
    $bank_account = trim($_POST['bank_account']);
    $bank_holder = trim($_POST['bank_holder']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Handle Logo Upload
    $logoName = $partner['logo'] ?? null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/logos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $newName = 'logo_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $newName)) {
                // Delete old logo if exists
                if ($logoName && file_exists($uploadDir . $logoName)) {
                    @unlink($uploadDir . $logoName);
                }
                $logoName = $newName;
            }
        }
    }

    if (empty($name)) {
        $error = "Nama Mitra wajib diisi";
    } else {
        try {
            // If setting as default, unset others first
            if ($is_default) {
                $stmtReset = $pdo->prepare("UPDATE partners SET is_default = 0 WHERE company_id = ?");
                $stmtReset->execute([currentCompanyId()]);
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE partners SET name=?, address=?, phone=?, bank_name=?, bank_account=?, bank_holder=?, is_default=?, logo=?, company_id=? WHERE id=?");
                $stmt->execute([$name, $address, $phone, $bank_name, $bank_account, $bank_holder, $is_default, $logoName, currentCompanyId(), $id]);
                logActivity($pdo, 'UPDATE_PARTNER', "Updated Partner: $name");
            } else {
                $stmt = $pdo->prepare("INSERT INTO partners (name, address, phone, bank_name, bank_account, bank_holder, is_default, logo, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $address, $phone, $bank_name, $bank_account, $bank_holder, $is_default, $logoName, currentCompanyId()]);
                logActivity($pdo, 'ADD_PARTNER', "Added Partner: $name");
            }
            header("Location: partners.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

$pageTitle = $id ? "Edit Mitra" : "Tambah Mitra";
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark"><?= $pageTitle ?></h3>
        <p class="text-muted mb-0 small"><?= $id ? 'Edit data mitra' : 'Tambahkan mitra baru' ?></p>
    </div>
    <a href="partners.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Nama Mitra / Perusahaan <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($partner['name'] ?? $_POST['name'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($partner['address'] ?? $_POST['address'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($partner['phone'] ?? $_POST['phone'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Logo</label>
                        <?php if (!empty($partner['logo'])): ?>
                            <div class="mb-2">
                                <img src="../uploads/logos/<?= htmlspecialchars($partner['logo'] ?? '') ?>" alt="Logo" class="img-thumbnail" style="max-height: 100px">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <div class="form-text">Format: JPG, PNG. Max 2MB.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_default" id="is_default" value="1" <?= (!empty($partner['is_default']) || (!empty($_POST['is_default']))) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_default">Jadikan Default</label>
                        </div>
                        <div class="form-text">Mitra default akan otomatis terpilih saat membuat proyek baru.</div>
                    </div>
                </div>
            </div>

            <hr class="my-4">
            <h5 class="mb-3">Informasi Rekening Bank</h5>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nama Bank</label>
                    <input type="text" name="bank_name" class="form-control" placeholder="Contoh: BCA" value="<?= htmlspecialchars($partner['bank_name'] ?? $_POST['bank_name'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nomor Rekening</label>
                    <input type="text" name="bank_account" class="form-control" value="<?= htmlspecialchars($partner['bank_account'] ?? $_POST['bank_account'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Atas Nama</label>
                    <input type="text" name="bank_holder" class="form-control" value="<?= htmlspecialchars($partner['bank_holder'] ?? $_POST['bank_holder'] ?? '') ?>">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="partners.php" class="btn btn-light">Batal</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; 