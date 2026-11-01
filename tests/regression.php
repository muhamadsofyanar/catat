<?php
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$root = dirname(__DIR__);
if (!is_dir($root . '/tmp')) mkdir($root . '/tmp', 0750, true);
$database = $root . '/tmp/regression-' . bin2hex(random_bytes(6)) . '.sqlite';
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $database);
putenv('ADMIN_PASSWORD=regression-password-123');
putenv('JWT_SECRET=regression-secret-with-at-least-32-characters');
require $root . '/config/db.php';
$_SESSION['company_id'] = 1;
$_SESSION['username'] = 'regression';
$_SESSION['user_id'] = 1;
function check($condition, $label) {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $GLOBALS['checks'][] = $label;
}
try {
    check($pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'fresh database integrity');
    check(password_verify('regression-password-123', $pdo->query('SELECT password FROM users LIMIT 1')->fetchColumn()), 'admin password from environment');
    check((bool)$pdo->query("SELECT name FROM pragma_table_info('projects') WHERE name = 'original_price'")->fetchColumn(), 'fresh schema contains original_price');
    check((bool)$pdo->query("SELECT name FROM pragma_table_info('settings') WHERE name = 'reminder_min_interval_days'")->fetchColumn(), 'settings reminder interval on first request');
    $pdo->exec("INSERT INTO projects (client_name, service_name, original_price, total_price, company_id) VALUES ('Regression Client', 'Service', 1000, 1000, 1)");
    $id = (int)$pdo->lastInsertId();
    $projectModel = new \Modules\Projects\Models\Project();
    check(count($projectModel->getAll(['status' => 'all'])) === 1, 'project listing without filters');
    check(count($projectModel->getAll(['q' => 'Regression', 'status' => 'all'])) === 1, 'project listing with bound filters');
    $_SESSION['company_id'] = 2;
    check(count($projectModel->getAll(['status' => 'all'])) === 0, 'project listing company filter');
    $_SESSION['company_id'] = 1;
    $transaction = new \Modules\Finance\Models\Transaction();
    $transaction->create(['project_id' => $id, 'type' => 'income', 'amount' => 1000, 'category' => 'Payment', 'status' => 'paid', 'transaction_date' => '2026-01-01']);
    $tid = $pdo->lastInsertId();
    check(is_array($transaction->find($tid)) && $transaction->find($tid)['amount'] == 1000, 'transaction find returns a row');
    check($transaction->update($tid, ['note' => 'changed']), 'transaction update');
    check($transaction->find($tid)['note'] === 'changed', 'transaction update persists');
    $stmt = $pdo->prepare('INSERT INTO project_documents (project_id, file_name, file_path, category, uploaded_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
    $stmt->execute([$id, 'test.pdf', 'uploads/projects/test.pdf', 'other']);
    check((bool)$pdo->query('SELECT uploaded_at FROM project_documents')->fetchColumn(), 'document timestamp compatible with SQLite');
    require $root . '/api/JWT.php';
    $payload = ['exp' => time() + 60, 'data' => ['username' => 'pengguna-✓']];
    $token = JWT::encode($payload);
    check(JWT::decode($token) === $payload, 'JWT base64url round trip including unicode');
    check(JWT::decode($token . 'x') === null, 'JWT tamper rejection');
    check(JWT::decode(JWT::encode(['exp' => time() - 1, 'data' => []])) === null, 'JWT expired token rejection');
    check(JWT::decode('bad.token') === null, 'JWT malformed token rejection');
    $funds = new \Modules\Finance\Models\OwnerFunds();
    check($funds->available() == 600, 'owner allocation follows existing 60 percent formula');
    $funds->save('prive', 100, '2026-01-02', 'Regression withdrawal');
    check($funds->available() == 500, 'prive handler persists withdrawal');
    $before = $pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
    try { $funds->save('prive', 501, '2026-01-02', 'Excess'); throw new RuntimeException('Overspending accepted'); }
    catch (InvalidArgumentException $e) {}
    check($pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn() == $before, 'excess withdrawal rolls back');
    $transaction->create(['project_id' => $id, 'type' => 'expense', 'amount' => 200, 'category' => 'Kasbon', 'status' => 'paid', 'transaction_date' => '2026-01-01']);
    $funds->save('kasbon', 50, '2026-01-02', 'Regression repayment');
    check($pdo->query("SELECT SUM(amount) FROM transactions WHERE category = 'Pengembalian Kasbon'")->fetchColumn() == 50, 'repayment handler persists income');
    // Simulate an older schema, then run migrations independently of connection creation.
    $pdo->exec('ALTER TABLE projects DROP COLUMN original_price');
    $pdo->exec("UPDATE projects SET status = 'completed' WHERE id = $id");
    require $root . '/config/schema.php';
    check((bool)$pdo->query("SELECT name FROM pragma_table_info('projects') WHERE name = 'original_price'")->fetchColumn(), 'existing database migration restores missing column');
    check($pdo->query("SELECT status FROM projects WHERE id = $id")->fetchColumn() === 'done', 'completed status normalizes to done');
    check($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() == 1, 'migration does not duplicate admin');
    putenv('REGRESSION_ZERO=0');
    check(\Core\Env::get('REGRESSION_ZERO', 9) === '0', 'environment preserves zero values');
    echo implode("\n", array_map(fn($label) => 'PASS: ' . $label, $GLOBALS['checks'])) . "\n";
    echo 'OK: ' . count($GLOBALS['checks']) . " checks\n";
} finally {
    @unlink($database);
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}
