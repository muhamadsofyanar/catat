<?php
$root = '../';
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: clients.php");
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) {
    die("Client not found.");
}
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE client_id = ?");
$stmtCount->execute([$id]);
$total_projects = $stmtCount->fetchColumn();
$stmtRev = $pdo->prepare("
    SELECT SUM(t.amount) 
    FROM transactions t 
    JOIN projects p ON t.project_id = p.id 
    WHERE p.client_id = ? AND t.type = 'income'
");
$stmtRev->execute([$id]);
$total_revenue = $stmtRev->fetchColumn() ?: 0;
$stmtReceivable = $pdo->prepare("
    SELECT SUM(p.total_price - COALESCE(paid_sum.paid, 0)) 
    FROM projects p
    LEFT JOIN (
        SELECT project_id, SUM(amount) as paid
        FROM transactions
        WHERE type = 'income'
        GROUP BY project_id
    ) paid_sum ON p.id = paid_sum.project_id
    WHERE p.client_id = ? 
    AND (p.total_price - COALESCE(paid_sum.paid, 0)) > 0
");
$stmtReceivable->execute([$id]);
$total_receivable = $stmtReceivable->fetchColumn() ?: 0;
$stmtProjects = $pdo->prepare("
    SELECT p.*, 
    (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income') as paid
    FROM projects p 
    WHERE p.client_id = ? 
    ORDER BY created_at DESC
");
$stmtProjects->execute([$id]);
$projects = $stmtProjects->fetchAll();
$stmtStatus = $pdo->prepare("SELECT status, COUNT(*) as count FROM projects WHERE client_id = ? GROUP BY status");
$stmtStatus->execute([$id]);
$status_counts = $stmtStatus->fetchAll(PDO::FETCH_KEY_PAIR);
$statuses = ['ongoing', 'completed', 'cancelled'];
$status_data = [];
foreach ($statuses as $s) {
    $status_data[] = $status_counts[$s] ?? 0;
}
$pageTitle = "Detail Klien: " . $client['name'];
require __DIR__ . '/../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($client['name'] ?? '') ?></h3>
        <p class="text-muted mb-0 small">Detail profil dan riwayat proyek</p>
    </div>
    <div>
        <a href="export_data.php?type=projects&client_id=<?= $client['id'] ?>" class="btn btn-outline-success me-2"><i class="bi bi-file-earmark-excel me-1"></i> Export Proyek</a>
        <a href="client_form.php?id=<?= $client['id'] ?>" class="btn btn-outline-primary me-2"><i class="bi bi-pencil me-1"></i> Edit Profil</a>
        <a href="clients.php" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>
    </div>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom-0">
        <ul class="nav nav-tabs card-header-tabs" id="clientTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab" aria-selected="true">Ringkasan</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="visual-tab" data-bs-toggle="tab" data-bs-target="#visual" type="button" role="tab" aria-selected="false">Visualisasi Data</button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4">
        <div class="tab-content" id="clientTabsContent">
            <div class="tab-pane fade show active" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 h-100 bg-light">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted fw-bold border-bottom pb-2">INFORMASI KONTAK</h6>
                                <div class="mb-3">
                                    <small class="text-muted d-block">No. Handphone / WA</small>
                                    <span class="fs-6"><?= htmlspecialchars($client['phone'] ?: '-') ?></span>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Email</small>
                                    <span class="fs-6"><?= htmlspecialchars($client['email'] ?: '-') ?></span>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Alamat</small>
                                    <span class="fs-6"><?= htmlspecialchars($client['address'] ?: '-') ?></span>
                                </div>
                                <div class="mb-0">
                                    <small class="text-muted d-block">NPWP</small>
                                    <span class="fs-6"><?= htmlspecialchars($client['npwp'] ?: '-') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-3 h-100">
                            <div class="col-md-4">
                                <div class="card border-0 bg-primary bg-opacity-10 h-100 text-center">
                                    <div class="card-body d-flex flex-column justify-content-center">
                                        <h2 class="fw-bold text-primary mb-0"><?= $total_projects ?></h2>
                                        <small class="text-muted text-uppercase fw-bold">Total Proyek</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-success bg-opacity-10 h-100 text-center">
                                    <div class="card-body d-flex flex-column justify-content-center">
                                        <h4 class="fw-bold text-success mb-0"><?= formatRupiah($total_revenue) ?></h4>
                                        <small class="text-muted text-uppercase fw-bold">Total Pembayaran (Masuk)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-danger bg-opacity-10 h-100 text-center">
                                    <div class="card-body d-flex flex-column justify-content-center">
                                        <h4 class="fw-bold text-danger mb-0"><?= formatRupiah($total_receivable) ?></h4>
                                        <small class="text-muted text-uppercase fw-bold">Sisa Piutang (Belum Bayar)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Riwayat Proyek</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Tanggal</th>
                                        <th>Layanan</th>
                                        <th>Status</th>
                                        <th class="text-end">Nilai Proyek</th>
                                        <th class="text-end">Sudah Bayar</th>
                                        <th class="text-end">Sisa Tagihan</th>
                                        <th class="text-end pe-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($projects) > 0): ?>
                                        <?php foreach ($projects as $p): 
                                            $paid = $p['paid'] ?? 0;
                                            $balance = $p['total_price'] - $paid;
                                            $statusBadge = 'bg-secondary';
                                            if ($p['status'] == 'ongoing') $statusBadge = 'bg-primary';
                                            if ($p['status'] == 'completed') $statusBadge = 'bg-success';
                                            if ($p['status'] == 'cancelled') $statusBadge = 'bg-danger';
                                        ?>
                                        <tr>
                                            <td class="ps-4 text-muted small"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($p['service_name'] ?? '') ?></td>
                                            <td><span class="badge <?= $statusBadge ?> rounded-pill"><?= strtoupper($p['status']) ?></span></td>
                                            <td class="text-end"><?= formatRupiah($p['total_price']) ?></td>
                                            <td class="text-end text-success"><?= formatRupiah($paid) ?></td>
                                            <td class="text-end fw-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
                                                <?= formatRupiah($balance) ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Detail
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada riwayat proyek.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="visual" role="tabpanel" aria-labelledby="visual-tab">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold">Distribusi Status Proyek</div>
                            <div class="card-body">
                                <div style="height: 300px;">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold">Overview Keuangan Klien</div>
                            <div class="card-body">
                                <div style="height: 300px;">
                                    <canvas id="financeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['Ongoing', 'Completed', 'Cancelled'],
            datasets: [{
                data: <?= json_encode($status_data) ?>,
                backgroundColor: ['#4e73df','#1cc88a','#e74a3b'],
                hoverBackgroundColor: ['#2e59d9','#17a673','#2c9faf'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: { maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom' } }, cutout: '70%' },
    });
    const ctxFinance = document.getElementById('financeChart').getContext('2d');
    new Chart(ctxFinance, {
        type: 'bar',
        data: {
            labels: ['Total Pembayaran', 'Sisa Piutang'],
            datasets: [{
                label: 'Jumlah (Rp)',
                data: [<?= $total_revenue ?>, <?= $total_receivable ?>],
                backgroundColor: ['rgba(28, 200, 138, 0.7)','rgba(231, 74, 59, 0.7)'],
                borderColor: ['rgba(28, 200, 138, 1)','rgba(231, 74, 59, 1)'],
                borderWidth: 1, borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) {
                let label = context.dataset.label || '';
                if (label) { label += ': '; }
                if (context.parsed.y !== null) {
                    label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
                }
                return label;
            }}}},
            scales: { y: { beginAtZero: true, ticks: { callback: function(value) {
                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumSignificantDigits: 2 }).format(value);
            }}}}
        }
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; 