<?php
error_reporting(E_ALL);
set_error_handler(function ($n, $m, $f, $l) { if (error_reporting() & $n) throw new ErrorException($m, 0, $n, $f, $l); });
$root = dirname(__DIR__);
if (!is_dir($root . '/tmp')) mkdir($root . '/tmp', 0750, true);
$work = $root . '/tmp/import-test-' . bin2hex(random_bytes(5));
mkdir($work, 0750, true);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $work . '/app.sqlite');
putenv('ADMIN_PASSWORD=import-test-password-123');
require $root . '/config/db.php';
$_SESSION['user_id'] = 1; $_SESSION['username'] = 'admin'; $_SESSION['role'] = 'admin'; $_SESSION['company_id'] = 1;
$checks = 0;
function verifyImport($condition, $label) {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $GLOBALS['checks']++;
    echo 'PASS: ' . $label . "\n";
}
function rejectedImport($operation, $label, $expected = null) {
    try { $operation(); } catch (InvalidArgumentException | RuntimeException $e) { if ($expected !== null && !str_contains($e->getMessage(), $expected)) throw $e; verifyImport(true, $label); return; }
    throw new Exception('FAIL: ' . $label);
}
$empty = array_fill_keys(\Core\DataImporter::TABLES, []);
$tables = $empty;
$tables['companies'] = [['id' => 1, 'name' => 'Imported Company']];
$tables['clients'] = [['id' => 4, 'company_id' => 1, 'name' => 'Imported client', 'created_at' => '2025-01-01 00:00:00']];
$tables['projects'] = [['id' => 7, 'company_id' => 1, 'client_id' => 4, 'client_name' => 'Imported client', 'service_name' => 'Service', 'original_price' => 1000, 'total_price' => 1000, 'status' => 'completed']];
$tables['transactions'] = [['id' => 9, 'company_id' => 1, 'project_id' => 7, 'type' => 'income', 'amount' => 250, 'transaction_date' => '2025-01-01']];
$tables['project_documents'] = [['id' => 2, 'project_id' => 7, 'category' => 'other', 'file_name' => 'Test.pdf', 'file_path' => 'uploads/projects/7/test.pdf']];
$content = '%PDF-1.4 import test';
$package = ['format' => 'legavoice-data', 'version' => 1, 'tables' => $tables, 'files' => [['path' => 'uploads/projects/7/test.pdf', 'sha256' => hash('sha256', $content), 'content_base64' => base64_encode($content)]]];
$file = $work . '/data.json';
$write = function ($data) use ($file) { file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR)); };
$write($package);
$importer = new \Core\DataImporter($pdo, $work);
$preview = $importer->inspect($file);
verifyImport($preview['counts']['projects'] === 1 && $preview['counts']['transactions'] === 1, 'preview lists business records');
verifyImport($preview['file_count'] === 1, 'preview validates bundled document');
// Opening general_expenses.php creates this bookkeeping row before any business data exists.
$pdo->exec("INSERT INTO projects (id, client_name, service_name, status) VALUES (7, 'INTERNAL', 'KEUANGAN PERUSAHAAN', 'internal')");
$importer->requireEmptyTarget();
verifyImport(true, 'empty automatic internal project does not block import');
verifyImport($importer->targetState()['business_counts']['projects'] === 0 && $importer->targetState()['empty_internal_ids'] === [7], 'diagnostics distinguish empty internal and business projects');
$pdo->exec('UPDATE projects SET total_price = 1 WHERE id = 7');
rejectedImport(fn() => $importer->requireEmptyTarget(), 'internal project with value is protected', '1 proyek bisnis');
$pdo->exec("UPDATE projects SET total_price = 0, description = 'User content' WHERE id = 7");
rejectedImport(fn() => $importer->requireEmptyTarget(), 'internal project with description is protected', '1 proyek bisnis');
$pdo->exec('UPDATE projects SET description = NULL WHERE id = 7');
$pdo->exec("INSERT INTO projects (id, client_name, service_name, status) VALUES (8, 'Real client', 'Real service', 'ongoing')");
rejectedImport(fn() => $importer->apply($file, 'admin'), 'zero-value business project is not deleted', '1 proyek bisnis');
verifyImport($pdo->query('SELECT COUNT(*) FROM projects WHERE id = 8')->fetchColumn() == 1, 'rejected import preserves business project');
$pdo->exec('DELETE FROM projects WHERE id = 8');
$pdo->exec("INSERT INTO transactions (project_id, type, amount) VALUES (7, 'expense', 0)");
rejectedImport(fn() => $importer->apply($file, 'admin'), 'internal project with a zero-value transaction is protected', '1 transaksi');
verifyImport($pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn() == 1, 'rejected import preserves linked transaction');
$pdo->exec('DELETE FROM transactions');
$pdo->exec("INSERT INTO clients (id, name) VALUES (99, 'Existing client')");
rejectedImport(fn() => $importer->requireEmptyTarget(), 'existing client is diagnosed', '1 klien');
$pdo->exec('DELETE FROM clients WHERE id = 99');


$wrong = $package; $wrong['tables']['users'] = [['id' => 1, 'role' => 'admin']];
$write($wrong); rejectedImport(fn() => $importer->inspect($file), 'rejects account table import');
$wrong = $package; $wrong['files'][0]['path'] = 'uploads/../config/db.php';
$write($wrong); rejectedImport(fn() => $importer->inspect($file), 'rejects path traversal');
$wrong = $package; $wrong['files'][0]['path'] = 'uploads/payload.php';
$write($wrong); rejectedImport(fn() => $importer->inspect($file), 'rejects executable uploads');
$wrong = $package; $wrong['files'][0]['sha256'] = str_repeat('0', 64);
$write($wrong); rejectedImport(fn() => $importer->inspect($file), 'rejects corrupt file checksum');
$wrong = $package; $wrong['tables']['transactions'][0]['project_id'] = 999;
$write($wrong); rejectedImport(fn() => $importer->inspect($file), 'rejects broken record relationship');
$wrong = $package; $wrong['tables']['clients'][0]['company_id'] = 2;
$write($wrong); rejectedImport(fn() => $importer->inspect($file), 'rejects a different company');
$write($package);
// Force an actual SQL failure after files have been created. All effects must roll back.
$pdo->exec("CREATE TRIGGER fail_import BEFORE INSERT ON transactions BEGIN SELECT RAISE(ABORT, 'test failure'); END");
rejectedImport(fn() => $importer->apply($file, 'admin'), 'SQL failure rejects import', 'test failure');
verifyImport($pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn() == 1, 'failed import restores original empty internal project');
verifyImport(!is_file($work . '/uploads/projects/7/test.pdf'), 'failed import removes newly copied document');
verifyImport($pdo->query('SELECT COUNT(*) FROM data_imports')->fetchColumn() == 0, 'failed import does not mark success');
$pdo->exec('DROP TRIGGER fail_import');
$passwordBefore = $pdo->query('SELECT password FROM users WHERE id = 1')->fetchColumn();
$result = $importer->apply($file, 'admin');
verifyImport($pdo->query('SELECT total_price FROM projects WHERE id = 7')->fetchColumn() == 1000, 'imports project values');
verifyImport($pdo->query('SELECT SUM(amount) FROM transactions')->fetchColumn() == 250, 'imports cash transactions');
verifyImport($pdo->query('SELECT status FROM projects WHERE id = 7')->fetchColumn() === 'done', 'normalizes completed status');
verifyImport($pdo->query('SELECT password FROM users WHERE id = 1')->fetchColumn() === $passwordBefore, 'preserves current login');
verifyImport(file_get_contents($work . '/uploads/projects/7/test.pdf') === $content, 'restores exact document bytes');
$backup = new PDO('sqlite:' . $result['backup_path']);
verifyImport($backup->query('SELECT COUNT(*) FROM projects')->fetchColumn() == 1, 'backup preserves pre-import internal project');
verifyImport($pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok' && !$pdo->query('PRAGMA foreign_key_check')->fetchAll(), 'imported database integrity');
rejectedImport(fn() => $importer->apply($file, 'admin'), 'duplicate import is blocked');
verifyImport($pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn() == 1, 'duplicate attempt leaves existing data intact');
echo 'OK: ' . $checks . " import checks\n";
if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
$backup = null;
// Test data lives only below the unique work directory.
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($files as $item) { if ($item->isDir()) rmdir($item->getPathname()); else unlink($item->getPathname()); }
rmdir($work);
