<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$id]);
$transaction = $stmt->fetch();
if (!$transaction) {
    die("Transaksi tidak ditemukan.");
}
$project = null;
if ($transaction['project_id']) {
    $stmtP = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmtP->execute([$transaction['project_id']]);
    $project = $stmtP->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = str_replace('.', '', $_POST['amount']);
    $transaction_date = $_POST['transaction_date'];
    $category = $_POST['category'];
    $note = $_POST['note'];
    $status = $_POST['status'] ?? 'paid';
    if ($amount <= 0) {
        $error = "Jumlah harus lebih dari 0";
    } else {
        $restricted_categories = ['Prive', 'Kasbon', 'Company Withdrawal'];
        if (in_array($transaction['category'], $restricted_categories) && $_SESSION['role'] !== 'admin') {
            die("Akses Ditolak. Transaksi kategori " . $transaction['category'] . " hanya dapat diedit oleh Admin.");
        }
        if (in_array($category, $restricted_categories) && $_SESSION['role'] !== 'admin') {
            die("Akses Ditolak. Hanya Admin yang dapat memilih kategori " . $category . ".");
        }
        $sql = "UPDATE transactions SET amount = ?, transaction_date = ?, category = ?, note = ?, status = ? WHERE id = ?";
        $stmtU = $pdo->prepare($sql);
        $stmtU->execute([$amount, $transaction_date, $category, $note, $status, $id]);
        logActivity($pdo, 'EDIT_TRANSACTION', "Edited transaction ID: $id ($category - " . formatRupiah($amount) . ")");
        if (isset($_POST['redirect_url'])) {
            header("Location: " . $_POST['redirect_url']);
        } else {
            if ($project) {
                header("Location: project_detail.php?id=" . $project['id']);
            } else {
                header("Location: cash_flow.php");
            }
        }
        exit;
    }
}
$pageTitle = "Edit Transaksi - LegaVoice";
require __DIR__ . '/../includes/header.php';
$redirect_url = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
} else {
    $redirect_url = $_POST['redirect_url'] ?? 'index.php';
}
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Transaksi</h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($redirect_url) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Transaksi</label>
                        <input type="date" name="transaction_date" class="form-control" value="<?= $transaction['transaction_date'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tipe</label>
                        <input type="text" class="form-control bg-light" value="<?= $transaction['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran' ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kategori</label>
                        <?php if($transaction['category'] == 'Prive' || $transaction['category'] == 'Kasbon'): ?>
                            <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($transaction['category'] ?? '') ?>" readonly>
                            <div class="form-text text-warning">Kategori Prive/Kasbon tidak dapat diubah di sini.</div>
                        <?php else: ?>
                            <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($transaction['category'] ?? '') ?>" list="categoryList" required>
                            <datalist id="categoryList">
                                <option value="Operasional Kantor">
                                <option value="Gaji Karyawan">
                                <option value="Listrik & Internet">
                                <option value="Sewa Tempat">
                                <option value="Marketing">
                                <option value="Entertainment">
                                <option value="Transportasi">
                                <option value="Maintenance Aset">
                                <option value="Konsumsi">
                            </datalist>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Jumlah (Rp)</label>
                        <input type="number" name="amount" class="form-control" value="<?= $transaction['amount'] ?>" required>
                    </div>
                    <?php if($transaction['type'] === 'expense'): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Status Pembayaran</label>
                        <select name="status" class="form-select">
                            <option value="paid" <?= $transaction['status'] === 'paid' ? 'selected' : '' ?>>Lunas (Paid)</option>
                            <option value="unpaid" <?= $transaction['status'] === 'unpaid' ? 'selected' : '' ?>>Belum Lunas (Unpaid/Hutang)</option>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="status" value="paid">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Catatan / Keterangan</label>
                        <textarea name="note" class="form-control" rows="3"><?= htmlspecialchars($transaction['note'] ?? '') ?></textarea>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?= htmlspecialchars($redirect_url) ?>" class="btn btn-light border">Batal</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>
<?php require __DIR__ . '/../includes/footer.php'; 