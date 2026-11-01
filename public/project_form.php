<?php
$root = '../';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';

$project = null;
$project_fees = [];
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize variables safely
    $client_name = '';
    $service_name = '';

    $original_price = $_POST['original_price'];
    $discount = $_POST['discount'];
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'ongoing';
    $due_date = $_POST['due_date'] ?? null;
    $partner_id = $_POST['partner_id'] ?? null;
    $partner_commission = $_POST['partner_commission'] ?? 0;
    $payment_link = $_POST['payment_link'] ?? '';
    
    $service_id = $_POST['service_id'] ?? null;
    $service_data = null;
    
    if ($service_id && $service_id !== 'custom') {
        $stmtS = $pdo->prepare("SELECT * FROM services WHERE id = ?");
        $stmtS->execute([$service_id]);
        $service_data = $stmtS->fetch();
        if ($service_data) {
            $service_name = $service_data['name'];
        }
    }
    
    // If service name not found from ID (or custom), take from text input
    if (empty($service_name)) {
        $service_name = $_POST['service_name_text'] ?? '';
        // If it was 'custom', set ID to null for storage
        if ($service_id === 'custom') {
            $service_id = null;
        }
    }
    $auto_expense = isset($_POST['auto_expense']) && $_POST['auto_expense'] === '1';
    $fee_labels = $_POST['fee_labels'] ?? [];
    $fee_amounts = $_POST['fee_amounts'] ?? [];
    $total_fees = 0;
    for($i=0; $i<count($fee_amounts); $i++) {
        $total_fees += (float)$fee_amounts[$i];
    }
    $name_fee = $total_fees;
    $additional_fee_label = 'Biaya Tambahan';
    $total_price = ($original_price + $total_fees) - $discount;
    $client_select = $_POST['client_id_select'] ?? '';
    if ($client_select && $client_select !== 'new') {
        $client_id = $client_select;
        $stmtC = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
        $stmtC->execute([$client_id]);
        $cData = $stmtC->fetch();
        $client_name = $cData['name'];
    } else {
        $client_name = isset($_POST['client_name_new']) ? trim($_POST['client_name_new']) : '';
        
        // Find existing client by name or create new
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = ?");
        $stmt->execute([$client_name]);
        $client_data = $stmt->fetch();
        if ($client_data) {
            $client_id = $client_data['id'];
        } else {
            // Only insert if name is not empty
            if (!empty($client_name)) {
                $stmt = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
                $stmt->execute([$client_name]);
                $client_id = $pdo->lastInsertId();
            } else {
                // Handle case where no client name provided
                $client_id = null;
            }
        }
    }
    $project_id_save = $_POST['id'] ?? null;
    if (!empty($project_id_save)) {
        $stmt = $pdo->prepare("UPDATE projects SET client_id=?, client_name=?, service_name=?, original_price=?, name_fee=?, discount=?, description=?, total_price=?, status=?, due_date=?, additional_fee_label=?, partner_id=?, partner_commission=?, service_id=?, payment_link=?, company_id=? WHERE id=?");
        $stmt->execute([$client_id, $client_name, $service_name, $original_price, $name_fee, $discount, $description, $total_price, $status, $due_date, $additional_fee_label, $partner_id, $partner_commission, $service_id, $payment_link, currentCompanyId(), $project_id_save]);
        logActivity($pdo, 'UPDATE_PROJECT', "Updated project #$project_id_save ($client_name - $service_name)");
    } else {
        $stmt = $pdo->prepare("INSERT INTO projects (client_id, client_name, service_name, original_price, name_fee, discount, description, total_price, status, due_date, additional_fee_label, partner_id, partner_commission, service_id, payment_link, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $client_name, $service_name, $original_price, $name_fee, $discount, $description, $total_price, $status, $due_date, $additional_fee_label, $partner_id, $partner_commission, $service_id, $payment_link, currentCompanyId()]);
        $project_id_save = $pdo->lastInsertId();
        logActivity($pdo, 'CREATE_PROJECT', "Created new project #$project_id_save ($client_name - $service_name)");
    }
    $pdo->prepare("DELETE FROM project_fees WHERE project_id = ?")->execute([$project_id_save]);
    $stmtFee = $pdo->prepare("INSERT INTO project_fees (project_id, label, amount) VALUES (?, ?, ?)");
    for($i=0; $i<count($fee_labels); $i++) {
        if (!empty($fee_labels[$i]) || !empty($fee_amounts[$i])) {
            $label = $fee_labels[$i] ?: 'Biaya Tambahan';
            $amount = (float)$fee_amounts[$i];
            $stmtFee->execute([$project_id_save, $label, $amount]);
        }
    }
    if ($auto_expense && $service_id && is_numeric($service_id)) {
        $stmtCosts = $pdo->prepare("SELECT * FROM service_costs WHERE service_id = ?");
        $stmtCosts->execute([$service_id]);
        $costs = $stmtCosts->fetchAll();
        $stmtTrans = $pdo->prepare("INSERT INTO transactions (project_id, type, category, amount, note, status, transaction_date, company_id) VALUES (?, 'expense', ?, ?, ?, 'unpaid', CURRENT_DATE, ?)");
        $stmtCheck = $pdo->prepare("SELECT id FROM transactions WHERE project_id = ? AND type = 'expense' AND category = ? AND amount = ?");
        foreach($costs as $cost) {
            if ($cost['amount'] <= 0) continue;
            $stmtCheck->execute([$project_id_save, $cost['description'], $cost['amount']]);
            if ($stmtCheck->fetch()) continue;
            $stmtTrans->execute([$project_id_save, $cost['description'], $cost['amount'], 'Auto-generated from Service Standard', currentCompanyId()]);
            logActivity($pdo, 'AUTO_EXPENSE', "Generated Expense: " . $cost['description'] . " (" . formatRupiah($cost['amount']) . ") for Project #$project_id_save");
        }
    }
    header("Location: projects.php");
    exit;
}
$stmtClients = $pdo->prepare("SELECT * FROM clients WHERE company_id = ? ORDER BY name ASC");
$stmtClients->execute([currentCompanyId()]);
$clients = $stmtClients->fetchAll();
$stmtPartners = $pdo->prepare("SELECT * FROM partners WHERE company_id = ? ORDER BY is_default DESC, name ASC");
$stmtPartners->execute([currentCompanyId()]);
$partners = $stmtPartners->fetchAll();
$stmtServices = $pdo->prepare("SELECT * FROM services WHERE company_id = ? ORDER BY category, name ASC");
$stmtServices->execute([currentCompanyId()]);
$services = $stmtServices->fetchAll();
if ($id) {
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND company_id = ?");
$stmt->execute([$id, currentCompanyId()]);
    $project = $stmt->fetch();
    if ($project) {
        $stmtFees = $pdo->prepare("SELECT * FROM project_fees WHERE project_id = ?");
        $stmtFees->execute([$id]);
        $project_fees = $stmtFees->fetchAll();
    }
}
$pageTitle = ($project ? 'Edit' : 'Tambah') . " Proyek";
require __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center mt-4">
    <div class="col-lg-8">
        <div class="card border-0">
            <div class="card-header bg-white border-bottom-0 pb-0">
                <h4 class="mb-0 fw-bold"><?= $project ? 'Edit' : 'Tambah' ?> Proyek</h4>
            </div>
            <div class="card-body">
            <form method="POST">
                <?php if ($project): ?>
                    <input type="hidden" name="id" value="<?= $project['id'] ?>">
                <?php endif; ?>
                <div class="mb-3 bg-light p-2 rounded border">
                    <label class="form-label small text-muted text-uppercase fw-bold">Mitra / Brand Pelaksana (White Label)</label>
                    <select name="partner_id" class="form-select form-select-sm mb-2">
                        <?php foreach($partners as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($project && $project['partner_id'] == $p['id']) || (!$project && $p['is_default']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name'] ?? '') ?> <?= $p['is_default'] ? '(Default)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="row align-items-center">
                        <div class="col-8">
                            <label class="form-label small text-muted">Komisi / Potongan Khusus Mitra (Rp)</label>
                            <input type="number" name="partner_commission" class="form-control form-control-sm" value="<?= $project['partner_commission'] ?? 0 ?>" placeholder="Contoh: 500000">
                        </div>
                        <div class="col-4">
                             <small class="text-muted" style="font-size: 0.7em;">Nilai ini akan mengurangi jumlah yang harus disetor Mitra, tapi tidak muncul di Invoice Klien.</small>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Klien</label>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <select name="client_id_select" id="client_id_select" class="form-select" onchange="toggleClientInput()">
                                <option value="">-- Pilih Klien --</option>
                                <?php foreach($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($project && $project['client_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                                <option value="new" <?= (!$project || ($project && !$project['client_id'])) ? 'selected' : '' ?>>+ Klien Baru</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="new_client_div" style="display: <?= (!$project || ($project && !$project['client_id'])) ? 'block' : 'none' ?>;">
                            <input type="text" name="client_name_new" id="client_name_new" class="form-control" placeholder="Nama Klien Baru" value="<?= ($project && !$project['client_id']) ? htmlspecialchars($project['client_name'] ?? '') : '' ?>">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Layanan</label>
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Pilih layanan baku atau custom</small>
                        <a href="services.php" target="_blank" class="text-decoration-none small"><i class="bi bi-gear"></i> Atur Biaya Baku</a>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                             <select name="service_id" id="service_id" class="form-select" onchange="toggleServiceInput()">
                                <option value="">-- Pilih Layanan Baku --</option>
                                <?php foreach($services as $s): ?>
                                    <option value="<?= $s['id'] ?>" 
                                        data-name="<?= htmlspecialchars($s['name'] ?? '') ?>" 
                                        data-price="<?= $s['default_price'] ?>"
                                        data-category="<?= htmlspecialchars($s['category'] ?? '') ?>"
                                        <?= ($project && $project['service_id'] == $s['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['name'] ?? '') ?> (<?= htmlspecialchars($s['category'] ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom" <?= ($project && empty($project['service_id'])) ? 'selected' : '' ?>>Lainnya (Custom)</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="custom_service_div" style="display: <?= ($project && empty($project['service_id'])) ? 'block' : 'none' ?>;">
                             <input type="text" name="service_name_text" id="service_name_text" class="form-control" placeholder="Nama Layanan Custom" value="<?= htmlspecialchars($project['service_name'] ?? '') ?>">
                        </div>
                    </div>
                    <div id="service_costs_preview" class="mt-2 p-2 bg-light border rounded" style="display: none;">
                        <small class="fw-bold d-block text-muted mb-1">Rincian Biaya Modal (Estimasi):</small>
                        <ul id="cost_list" class="list-unstyled small mb-2 text-muted ps-2" style="border-left: 3px solid #ddd;"></ul>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="auto_expense" value="1" id="auto_expense" checked>
                            <label class="form-check-label small" for="auto_expense">
                                Otomatis catat sebagai <b>Pengeluaran (Unpaid)</b> agar tercatat sebagai Uang Vendor/Hutang.
                            </label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jatuh Tempo Tagihan</label>
                        <input type="date" name="due_date" class="form-control" value="<?= $project['due_date'] ?? '' ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status Proyek</label>
                        <select name="status" class="form-select">
                            <option value="ongoing" <?= ($project && $project['status'] === 'ongoing') ? 'selected' : '' ?>>Ongoing (Berjalan)</option>
                            <option value="done" <?= ($project && $project['status'] === 'done') ? 'selected' : '' ?>>Completed (Selesai)</option>
                            <option value="cancelled" <?= ($project && $project['status'] === 'cancelled') ? 'selected' : '' ?>>Cancelled (Batal)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Link (Opsional)</label>
                    <input type="text" name="payment_link" class="form-control" value="<?= htmlspecialchars($project['payment_link'] ?? '') ?>" placeholder="https://pembayaran.example/invoice/ABC123">
                    <small class="text-muted">Jika diisi, {{payment_link}} pada template pengingat akan menggunakan link ini.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Item yang Didapat (Deskripsi)</label>
                    <textarea name="description" id="description" class="form-control" rows="3"><?= $project['description'] ?? '' ?></textarea>
                    <small class="text-muted">Otomatis terisi jika layanan mengandung kata "Pendirian"</small>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Harga Asli (Rp)</label>
                        <input type="number" id="price" name="original_price" class="form-control" required value="<?= $project['original_price'] ?? ($project['initial_price'] ?? 0) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Diskon (Rp)</label>
                        <input type="number" id="discount" name="discount" class="form-control" value="<?= $project['discount'] ?? 0 ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Rincian Biaya Tambahan</label>
                    <div id="fees-container" class="border p-3 rounded bg-white">
                        <?php if (empty($project_fees)): ?>
                             <div class="row g-2 mb-2 fee-row align-items-center">
                                <div class="col-7">
                                    <input type="text" name="fee_labels[]" class="form-control" placeholder="Label (misal: Ongkir)" value="Biaya Pesan Nama">
                                </div>
                                <div class="col-4">
                                    <input type="number" name="fee_amounts[]" class="form-control fee-amount" placeholder="Jumlah" value="0" oninput="calculate()">
                                </div>
                                <div class="col-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeFee(this)"><i class="bi bi-trash"></i></button>
                                </div>
                             </div>
                        <?php else: ?>
                            <?php foreach($project_fees as $fee): ?>
                                <div class="row g-2 mb-2 fee-row align-items-center">
                                    <div class="col-7">
                                        <input type="text" name="fee_labels[]" class="form-control" value="<?= htmlspecialchars($fee['label'] ?? '') ?>">
                                    </div>
                                    <div class="col-4">
                                        <input type="number" name="fee_amounts[]" class="form-control fee-amount" value="<?= $fee['amount'] ?>" oninput="calculate()">
                                    </div>
                                    <div class="col-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeFee(this)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addFee()"><i class="bi bi-plus-lg"></i> Tambah Item Biaya</button>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Total Tagihan: <span id="total_display" class="text-primary">Rp 0</span></label>
                    <input type="hidden" name="total_price" id="total_price">
                </div>
                <?php if ($project): ?>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="ongoing" <?= $project['status'] == 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                        <option value="done" <?= $project['status'] == 'done' ? 'selected' : '' ?>>Selesai</option>
                        <option value="cancelled" <?= $project['status'] == 'cancelled' ? 'selected' : '' ?>>Batal</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between">
                    <a href="projects.php" class="btn btn-secondary">Kembali</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    const serviceSelect = document.getElementById('service_id');
    const customServiceDiv = document.getElementById('custom_service_div');
    const descriptionInput = document.getElementById('description');
    const priceInput = document.getElementById('price');
    const discountInput = document.getElementById('discount');
    const totalDisplay = document.getElementById('total_display');
    const costsPreview = document.getElementById('service_costs_preview');
    const costList = document.getElementById('cost_list');
    const serviceCosts = {};
    <?php 
    $allCosts = $pdo->query("SELECT * FROM service_costs")->fetchAll();
    foreach($allCosts as $ac) {
        echo "if(!serviceCosts[{$ac['service_id']}]) serviceCosts[{$ac['service_id']}] = [];\n";
        echo "serviceCosts[{$ac['service_id']}].push({desc: '".addslashes($ac['description'])."', amount: {$ac['amount']}});\n";
    }
    ?>
    function toggleServiceInput() {
        const val = serviceSelect.value;
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        if (val === 'custom') {
            customServiceDiv.style.display = 'block';
            costsPreview.style.display = 'none';
        } else {
            customServiceDiv.style.display = 'none';
            if (!val) {
                costsPreview.style.display = 'none';
                return;
            }
            const price = selectedOption.getAttribute('data-price');
            if(price && price > 0) {
                 if(!priceInput.value || priceInput.value == 0) {
                     priceInput.value = price;
                     calculate();
                 }
            }
            const name = selectedOption.getAttribute('data-name').toLowerCase();
            if (name.includes('pendirian') && !descriptionInput.value.trim()) {
                descriptionInput.value = "Akta, SK, NPWP, NIB";
            }
            if (serviceCosts[val] && serviceCosts[val].length > 0) {
                costsPreview.style.display = 'block';
                costList.innerHTML = '';
                let totalCost = 0;
                serviceCosts[val].forEach(c => {
                    totalCost += c.amount;
                    const li = document.createElement('li');
                    li.innerHTML = `${c.desc}: <b>Rp ${new Intl.NumberFormat('id-ID').format(c.amount)}</b>`;
                    costList.appendChild(li);
                });
                const liTotal = document.createElement('li');
                liTotal.className = 'mt-1 fw-bold text-dark';
                liTotal.innerHTML = `Total Uang Vendor: Rp ${new Intl.NumberFormat('id-ID').format(totalCost)}`;
                costList.appendChild(liTotal);
            } else {
                costsPreview.style.display = 'none';
            }
        }
    }
    toggleServiceInput();
    function toggleClientInput() {
        const clientSelect = document.getElementById('client_id_select');
        const newClientDiv = document.getElementById('new_client_div');
        if (clientSelect.value === 'new') {
            newClientDiv.style.display = 'block';
            document.getElementById('client_name_new').required = true;
        } else {
            newClientDiv.style.display = 'none';
            document.getElementById('client_name_new').required = false;
        }
    }
    toggleClientInput();
    function calculate() {
        const price = parseFloat(priceInput.value) || 0;
        const discount = parseFloat(discountInput.value) || 0;
        let totalFees = 0;
        const feeInputs = document.querySelectorAll('.fee-amount');
        feeInputs.forEach(input => {
            totalFees += parseFloat(input.value) || 0;
        });
        const total = (price + totalFees) - discount;
        totalDisplay.innerText = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(total);
    }
    function addFee() {
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 fee-row align-items-center';
        div.innerHTML = `
            <div class="col-7">
                <input type="text" name="fee_labels[]" class="form-control" placeholder="Label">
            </div>
            <div class="col-4">
                <input type="number" name="fee_amounts[]" class="form-control fee-amount" placeholder="0" value="0" oninput="calculate()">
            </div>
            <div class="col-1">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeFee(this)"><i class="bi bi-trash"></i></button>
            </div>
        `;
        document.getElementById('fees-container').appendChild(div);
    }
    function removeFee(btn) {
        const rows = document.querySelectorAll('.fee-row');
        btn.closest('.fee-row').remove();
        calculate();
    }
    priceInput.addEventListener('input', calculate);
    discountInput.addEventListener('input', calculate);
    calculate();
</script>
<?php require __DIR__ . '/../includes/footer.php'; 