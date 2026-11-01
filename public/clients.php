<?php
$root = '../';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmtName = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
    $stmtName->execute([$id]);
    $clientName = $stmtName->fetchColumn();
    $check = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE client_id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        echo "<script>alert('Gagal hapus: Klien ini memiliki data proyek aktif.'); window.location='clients.php';</script>";
    } else {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($pdo, 'DELETE_CLIENT', "Deleted Client: $clientName");
        header("Location: clients.php");
        exit;
    }
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$params = [];
$sql = "SELECT * FROM clients";
if ($q !== '') {
    $sql .= " WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$sql .= " ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$exportUrl = "export_data.php?type=clients&q=" . urlencode($q);
$pageTitle = "Manajemen Klien";
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">Daftar Klien</h3>
        <p class="text-muted mb-0 small">Kelola data klien dan kontak</p>
    </div>
    <a href="client_form.php" class="btn btn-primary"><i class="bi bi-person-plus-fill me-2"></i>Tambah Klien</a>
</div>
<div class="card border-0 mb-4">
    <div class="card-body bg-light rounded-3">
        <form method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="q" class="form-control" placeholder="Cari nama, no hp, atau email..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Cari</button>
            </div>
        </form>
         <div class="mt-2 text-end">
             <a href="<?= $exportUrl ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Export CSV</a>
        </div>
    </div>
</div>
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Nama</th>
                        <th>No. HP</th>
                        <th>Email</th>
                        <th>Alamat</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clients) > 0): ?>
                        <?php foreach ($clients as $c): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= htmlspecialchars($c['name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                            <td><small><?= htmlspecialchars($c['address'] ?? '') ?></small></td>
                            <td class="text-end pe-4">
                                <a href="client_detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-info me-1" title="Lihat Detail"><i class="bi bi-eye"></i></a>
                                <a href="client_form.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
                                <a href="clients.php?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus klien ini?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada data klien.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 