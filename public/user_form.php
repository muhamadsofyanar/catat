<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}
$id = $_GET['id'] ?? null;
$user = null;
$error = '';
$success = '';
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        header("Location: users.php");
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    if (empty($username)) {
        $error = "Username tidak boleh kosong.";
    } else {
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmtCheck->execute([$username, $id ?? 0]);
        if ($stmtCheck->fetch()) {
            $error = "Username sudah digunakan.";
        }
    }
    if (empty($error)) {
        if ($id) {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $hash, $role, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $role, $id]);
            }
            logActivity($pdo, 'UPDATE_USER', "Updated user ID: $id");
            $success = "User berhasil diperbarui.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $role]);
            logActivity($pdo, 'CREATE_USER', "Created user: $username");
            header("Location: users.php");
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
    }
}
$pageTitle = $id ? "Edit User" : "User Baru";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark"><?= $id ? 'Edit User' : 'User Baru' ?></h3>
        <p class="text-muted mb-0 small">Kelola akun pengguna</p>
    </div>
    <div>
        <a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>
<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if(!empty($success)): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="admin" <?= (($user['role'] ?? 'staff') === 'admin') ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= (($user['role'] ?? 'staff') === 'staff') ? 'selected' : '' ?>>Staff</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= $id ? 'Password Baru (opsional)' : 'Password' ?></label>
                    <input type="password" name="password" class="form-control" <?= $id ? '' : 'required' ?>>
                    <?php if($id): ?><small class="text-muted">Kosongkan jika tidak mengganti password.</small><?php endif; ?>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 