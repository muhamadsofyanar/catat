<?php
$root = '../';
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$total_income = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income'")->fetchColumn() ?: 0;
$total_expense_paid = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND (status = 'paid' OR status IS NULL)")->fetchColumn() ?: 0;
$current_balance = $total_income - $total_expense_paid;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adjustment_amount = $_POST['adjustment_amount'];
    $category = $_POST['category'];
    $note = $_POST['note'];
    $date = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE client_name = 'INTERNAL' LIMIT 1");
    $stmt->execute();
    $project = $stmt->fetch();
    if ($project) {
        $project_id = $project['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO projects (client_name, service_name, status) VALUES ('INTERNAL', 'KEUANGAN', 'internal')");
        $stmt->execute();
        $project_id = $pdo->lastInsertId();
    }
    $stmt = $pdo->prepare("INSERT INTO transactions (project_id, type, category, amount, note, transaction_date, status) VALUES (?, 'expense', ?, ?, ?, ?, 'paid')");
    $stmt->execute([$project_id, $category, $adjustment_amount, $note, $date]);
    logActivity($pdo, 'ADJUST_BALANCE', "Adjusted balance by " . formatRupiah($adjustment_amount) . " ($category)");
    header("Location: index.php");
    exit;
}
$pageTitle = "Penyesuaian Saldo Kas";
require __DIR__ . '/../includes/header.php';
?>
<div class="container mt-5">
    <div class="card border-0">
        <div class="card-header bg-white border-bottom-0 py-3">
            <h4 class="mb-0 fw-bold text-warning">Penyesuaian Saldo Kas (Zero Out)</h4>
        </div>
        <div class="card-body">
            <div class="alert alert-info">Saldo Kas Tercatat Saat Ini: <strong>Rp <?= number_format($current_balance) ?></strong></div>
            <p>Gunakan form ini jika uang fisik sebenarnya <strong>Rp 0</strong> atau Anda ingin memindahkan sisa saldo tercatat ke Hutang Owner (Kasbon/Prive).</p>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nominal yang akan "dihabiskan" (Agar saldo jadi 0)</label>
                    <input type="number" name="adjustment_amount" class="form-control" value="<?= $current_balance ?>" readonly>
                    <div class="form-text">Otomatis diisi dengan saldo saat ini.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kategori Penyesuaian</label>
                    <select name="category" class="form-select" required>
                        <option value="Kasbon" selected>Kasbon (Masuk Hutang Owner - Harus Dikembalikan)</option>
                        <option value="Prive">Prive (Ambil Untung - Tidak Harus Dikembalikan)</option>
                        <option value="Company Withdrawal">Dana Taktis Perusahaan (Disimpan Sendiri)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Catatan</label>
                    <textarea name="note" class="form-control" rows="2">Penyesuaian Saldo Akhir (Uang sudah terpakai)</textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">Simpan Penyesuaian</button>
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
    </div>
<?php require __DIR__ . '/../includes/footer.php'; 