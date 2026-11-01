<?php
$root = '../';
$pageTitle = "System Health Check";
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../config/db.php';
if ($_SESSION['role'] !== 'admin') {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Akses Ditolak.</div></div>";
    require __DIR__ . '/../includes/footer.php';
    exit;
}
$issues = [];
$warnings = [];
$info = [];
$tables = ['projects', 'clients', 'services', 'transactions', 'partners', 'settings', 'users', 'activity_logs'];
foreach ($tables as $table) {
    try {
        $pdo->query("SELECT 1 FROM $table LIMIT 1");
        $info[] = "Table '$table' exists and is accessible.";
    } catch (Exception $e) {
        $issues[] = "Table '$table' is missing or corrupt: " . $e->getMessage();
    }
}
$total_income = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income'")->fetchColumn() ?: 0;
$total_expense = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense'")->fetchColumn() ?: 0;
$calculated_balance = $total_income - $total_expense;
$info[] = "Calculated System Balance: " . formatRupiah($calculated_balance);
$orphans = $pdo->query("SELECT COUNT(*) FROM transactions WHERE project_id NOT IN (SELECT id FROM projects)")->fetchColumn();
if ($orphans > 0) {
    $issues[] = "Found $orphans transactions linked to non-existent projects. (Orphaned Data)";
} else {
    $info[] = "No orphaned transactions found.";
}
$negatives = $pdo->query("SELECT COUNT(*) FROM transactions WHERE amount < 0")->fetchColumn();
if ($negatives > 0) {
    $warnings[] = "Found $negatives transactions with negative amounts. Transactions should generally be positive, with 'type' determining flow.";
}
$zeros = $pdo->query("SELECT COUNT(*) FROM transactions WHERE amount = 0")->fetchColumn();
if ($zeros > 0) {
    $warnings[] = "Found $zeros transactions with 0 amount. Please verify if these are intentional.";
}
$unpaid_done = $pdo->query("
    SELECT p.client_name || ' - ' || p.service_name as title 
    FROM projects p 
    JOIN transactions t ON p.id = t.project_id 
    WHERE p.status = 'done' AND t.status = 'unpaid'
    GROUP BY p.id
")->fetchAll(PDO::FETCH_COLUMN);
if (!empty($unpaid_done)) {
    $warnings[] = "Projects marked 'Done' but have unpaid transactions: " . implode(", ", $unpaid_done);
}
$internal = $pdo->query("SELECT id FROM projects WHERE client_name = 'INTERNAL'")->fetch();
if (!$internal) {
    $warnings[] = "Internal Project (client_name='INTERNAL') missing. It is used for system adjustments.";
}
?>
<div class="container mt-5 mb-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">System Health Check Report</h4>
        </div>
        <div class="card-body">
            <?php if (!empty($issues)): ?>
                <div class="alert alert-danger">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Critical Issues Found:</h5>
                    <ul>
                        <?php foreach ($issues as $issue): ?>
                            <li><?= $issue ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <h5 class="alert-heading"><i class="bi bi-check-circle-fill"></i> No Critical Issues Found.</h5>
                </div>
            <?php endif; ?>
            <?php if (!empty($warnings)): ?>
                <div class="alert alert-warning">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-circle"></i> Warnings (Check Data):</h5>
                    <ul>
                        <?php foreach ($warnings as $warning): ?>
                            <li><?= $warning ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="card mt-3">
                <div class="card-header bg-light fw-bold">System Information</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($info as $msg): ?>
                        <li class="list-group-item"><?= $msg ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="mt-4 text-center">
                <a href="index.php" class="btn btn-primary btn-lg">Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; 