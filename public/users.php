<?php
$root = '../';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('Tidak dapat menghapus akun sendiri!'); window.location='users.php';</script>";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($pdo, 'DELETE_USER', "Deleted user ID: $id");
        header("Location: users.php");
        exit;
    }
}
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$sql = "SELECT * FROM users";
$params = [];
$conditions = [];
if ($q) {
    $conditions[] = "username LIKE ?";
    $params[] = "%$q%";
}
if ($role_filter) {
    $conditions[] = "role = ?";
    $params[] = $role_filter;
}
if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
$pageTitle = "Manajemen Pengguna";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">Manajemen Pengguna</h3>
        <p class="text-muted mb-0 small">Kelola akses login aplikasi</p>
    </div>
    <div>
        <a href="<?= $base ?>export_data.php?type=users&q=<?= urlencode($q) ?>&role=<?= $role_filter ?>" class="btn btn-outline-success me-2"><i class="bi bi-file-earmark-excel"></i> Export CSV</a>
        <a href="<?= $base ?>user_form.php" class="btn btn-primary"><i class="bi bi-person-plus-fill me-2"></i>User Baru</a>
    </div>
</div>
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-2">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari username..." value="<?= htmlspecialchars($q) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">Semua Role</option>
                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="staff" <?= $role_filter === 'staff' ? 'selected' : '' ?>>Staff</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Cari</button>
            </div>
            <?php if($q || $role_filter): ?>
            <div class="col-md-2">
                <a href="users.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Username</th>
                        <th>Role</th>
                        <th>Dibuat Pada</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="ps-4 fw-medium">
                            <?= htmlspecialchars($u['username'] ?? '') ?>
                            <?php if($u['id'] == $_SESSION['user_id']): ?>
                                <span class="badge bg-success ms-2">You</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($u['role'] === 'admin'): ?>
                                <span class="badge bg-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Staff</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= date('d M Y H:i', strtotime($u['created_at'])) ?></td>
                        <td class="text-end pe-4">
                            <a href="<?= $base ?>user_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                            <a href="users.php?delete=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus user ini?')"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 