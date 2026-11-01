<?php
require __DIR__ . '/../config/db.php';
$kasbon_out = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND category = 'Kasbon'")->fetchColumn() ?: 0;
$kasbon_in  = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND category = 'Pengembalian Kasbon'")->fetchColumn() ?: 0;
$debt_kasbon = $kasbon_out - $kasbon_in;
$company_out = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND category = 'Company Withdrawal'")->fetchColumn() ?: 0;
$company_in  = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND category = 'Pengembalian Dana Perusahaan'")->fetchColumn() ?: 0;
$debt_company = $company_out - $company_in;
$pageTitle = "Kembalikan Dana";
require __DIR__ . '/../includes/header.php';
?>
    <div class="container mt-5">
        <div class="row justify-content_center">
            <div class="col-md-6">
                <div class="card border-0">
                    <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-0 text-success"><i class="bi bi-cash-stack me-2"></i>Kembalikan Dana (Bayar Hutang)</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="save_repayment.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Jenis Pengembalian</label>
                                <select name="type" class="form-select" id="repaymentType" required onchange="updateLimit()">
                                    <option value="kasbon">Pengembalian Kasbon (Hutang Uang Vendor) - Sisa Hutang: <?= formatRupiah($debt_kasbon) ?></option>
                                    <option value="company">Pengembalian Dana Perusahaan - Sisa Hutang: <?= formatRupiah($debt_company) ?></option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Jumlah (Rp)</label>
                                <input type="number" name="amount" id="amountInput" class="form-control form-control-lg" required placeholder="0">
                                <div class="form-text text-success">Uang ini akan masuk kembali ke Saldo Kas Real.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal</label>
                                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catatan</label>
                                <input type="text" name="note" class="form-control" placeholder="Contoh: Transfer ke rekening PT">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg fw-bold">Proses Pengembalian</button>
                                <a href="owner_debt.php" class="btn btn-outline_secondary">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const limits = {
            kasbon: <?= $debt_kasbon ?>,
            company: <?= $debt_company ?>
        };
        function updateLimit() {
            const type = document.getElementById('repaymentType').value;
        }
    </script>
<?php require __DIR__ . '/../includes/footer.php'; 