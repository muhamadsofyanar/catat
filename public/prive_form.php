<?php
require __DIR__ . '/../config/db.php';
$total_income = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND (category IS NULL OR category NOT IN ('Pengembalian Kasbon', 'Pengembalian Dana Perusahaan'))")->fetchColumn() ?: 0;
$total_prive = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND category = 'Prive'")->fetchColumn() ?: 0;
$total_expense_operational = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND (category NOT IN ('Prive', 'Company Withdrawal', 'Kasbon', 'Operasional Kantor') OR category IS NULL)")->fetchColumn() ?: 0;
$total_kasbon_out = $pdo->query("SELECT SUM(amount) FROM transactions WHERE category = 'Kasbon' AND type='expense'")->fetchColumn() ?: 0;
$total_kasbon_in = $pdo->query("SELECT SUM(amount) FROM transactions WHERE category = 'Pengembalian Kasbon' AND type='income'")->fetchColumn() ?: 0;
$total_kasbon = $total_kasbon_out - $total_kasbon_in;
$net_profit = $total_income - $total_expense_operational;
$share_owner = ($net_profit > 0) ? $net_profit * 0.60 : 0;
$owner_available = $share_owner - $total_prive;
$pageTitle = "Tarik Prive (Withdrawal)";
require __DIR__ . '/../includes/header.php';
?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h4 class="mb-0 fw-bold text_warning">Tarik Prive (Ambil Keuntungan)</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6 class="alert-heading fw-bold">Status Jatah Owner (60% Laba):</h6>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span>Total Hak Owner:</span>
                                <span class="fw-bold"><?= formatRupiah($share_owner) ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-danger">
                                <span>Sudah Diambil (Prive):</span>
                                <span>- <?= formatRupiah($total_prive) ?></span>
                            </div>
                            <div class="ps-3 mb-2 small border-start border-2 border-dark text-muted">
                                <div class="d-flex justify-content-between">
                                    <span>Prive (Mengurangi Jatah):</span>
                                    <span><?= formatRupiah($total_prive) ?></span>
                                </div>
                                <div class="d-flex justify-content-between text-info">
                                    <span>Info Kasbon (Hutang):</span>
                                    <span><?= formatRupiah($total_kasbon) ?></span>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fs-5 fw-bold <?= $owner_available < 0 ? 'text-danger' : 'text-success' ?>">
                                <span><?= $owner_available < 0 ? 'Overdraft (Hutang):' : 'Boleh Diambil Saat Ini:' ?></span>
                                <span><?= formatRupiah($owner_available) ?></span>
                            </div>
                        </div>
                        <form action="save_prive.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Jumlah Penarikan (Rp)</label>
                                <input type="number" name="amount" class="form-control form-control-lg" required max="<?= $owner_available > 0 ? $owner_available : 0 ?>" placeholder="0">
                                <div class="form-text">Maksimal penarikan sesuai sisa jatah owner. Jangan ambil jatah perusahaan!</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal</label>
                                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catatan</label>
                                <input type="text" name="note" class="form-control" placeholder="Contoh: Keperluan Pribadi, Belanja Rumah">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning btn-lg fw-bold">Tarik Dana</button>
                                <a href="index.php" class="btn btn-outline-secondary">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php require __DIR__ . '/../includes/footer.php'; 