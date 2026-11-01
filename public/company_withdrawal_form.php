<?php
require __DIR__ . '/../config/db.php';
// Calculate current Company Balance
$total_income = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND (category IS NULL OR category NOT IN ('Pengembalian Kasbon', 'Pengembalian Dana Perusahaan'))")->fetchColumn() ?: 0;
// Total Company Withdrawals (Penggunaan Dana Perusahaan non-operasional) - NET (Out - In)
$total_company_out = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND category = 'Company Withdrawal'")->fetchColumn() ?: 0;
$total_company_in = $pdo->query("SELECT SUM(amount) FROM transactions WHERE category = 'Pengembalian Dana Perusahaan' AND type='income'")->fetchColumn() ?: 0;
$total_company_withdrawal = $total_company_out - $total_company_in;
$total_expense_operational = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND (category NOT IN ('Prive', 'Company Withdrawal', 'Kasbon', 'Operasional Kantor') OR category IS NULL)")->fetchColumn() ?: 0;
$net_profit = $total_income - $total_expense_operational;
$share_company = ($net_profit > 0) ? $net_profit * 0.40 : 0;
$company_available = $share_company - $total_company_withdrawal;
$pageTitle = "Gunakan Dana Perusahaan";
require __DIR__ . '/../includes/header.php';
?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h4 class="mb-0 fw-bold text-primary">Gunakan Dana Perusahaan</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6 class="alert-heading fw-bold">Status Dana Perusahaan (40% Laba):</h6>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span>Total Hak Perusahaan:</span>
                                <span class="fw-bold"><?= formatRupiah($share_company) ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-danger">
                                <span>Sudah Terpakai:</span>
                                <span>- <?= formatRupiah($total_company_withdrawal) ?></span>
                            </div>
                            <?php if ($company_available < 0): ?>
                            <div class="d-flex justify-content-between text-danger small fst-italic mt-1">
                                <span>Overdraft (Masuk Hutang Owner):</span>
                                <span><?= formatRupiah($company_available) ?></span>
                            </div>
                            <?php endif; ?>
                            <hr>
                            <div class="d-flex justify-content-between fs-5 fw-bold text-primary">
                                <span>Tersedia Saat Ini:</span>
                                <span><?= formatRupiah($company_available) ?></span>
                            </div>
                        </div>
                        <form action="save_company_withdrawal.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Jenis Penggunaan</label>
                                <div class="card p-3 bg-light border-0">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="usage_type" id="typeExpense" value="expense" checked>
                                        <label class="form-check-label" for="typeExpense">
                                            <strong>Keperluan PT</strong> (Beli Aset, Investasi, dll)
                                            <div class="small text-muted">Mengurangi Jatah PT, <span class="text-success fw-bold">Bukan Hutang</span></div>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="usage_type" id="typeLoan" value="loan">
                                        <label class="form-check-label" for="typeLoan">
                                            <strong>Dipinjam Owner</strong> (Keperluan Pribadi)
                                            <div class="small text-muted">Mengurangi Jatah PT, <span class="text-danger fw-bold">Dicatat Hutang Owner</span></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Jumlah Penggunaan (Rp)</label>
                                <input type="number" name="amount" class="form-control form-control-lg" required max="<?= $company_available > 0 ? $company_available : 0 ?>" placeholder="0">
                                <div class="form-text">Maksimal sesuai dana tersedia.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal</label>
                                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Keperluan</label>
                                <input type="text" name="note" class="form-control" placeholder="Contoh: Beli Aset, Investasi, Terpakai Owner (Kasbon PT)">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold">Simpan Penggunaan</button>
                                <a href="index.php" class="btn btn-outline-secondary">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php require __DIR__ . '/../includes/footer.php'; 