<?php
namespace Core;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Business-data import for a fresh, single-company SQLite installation. */
class DataImporter {
    public const TABLES = ['companies', 'clients', 'partners', 'services', 'service_costs', 'projects', 'project_fees', 'transactions', 'project_documents', 'settings'];
    public const MAX_BYTES = 20 * 1024 * 1024;
    private PDO $db;
    private string $root;

    public function __construct(PDO $db, ?string $root = null) {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') throw new RuntimeException('Import ini memerlukan SQLite.');
        $this->db = $db;
        $this->root = $root ?? dirname(__DIR__);
        $db->exec('CREATE TABLE IF NOT EXISTS data_imports (id INTEGER PRIMARY KEY AUTOINCREMENT, package_hash TEXT UNIQUE NOT NULL, counts_json TEXT NOT NULL, username TEXT NOT NULL, backup_path TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    }

    private function directory(string $path): void {
        if (is_link($path)) throw new RuntimeException('Direktori tujuan tidak boleh berupa symbolic link.');
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) throw new RuntimeException('Direktori penyimpanan tidak dapat dibuat.');
    }

    public function stagingDirectory(): string {
        $path = $this->root . '/storage/imports';
        $this->directory($path);
        return $path;
    }

    private function read(string $file): array {
        if (!is_file($file) || filesize($file) > self::MAX_BYTES) throw new InvalidArgumentException('File import tidak ditemukan atau melebihi 20 MB.');
        try { $data = json_decode(file_get_contents($file), true, 64, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new InvalidArgumentException('File bukan JSON import LegaVoice yang valid.'); }
        if (!is_array($data) || ($data['format'] ?? '') !== 'legavoice-data' || ($data['version'] ?? null) !== 1) throw new InvalidArgumentException('Format atau versi file import tidak didukung.');
        $tables = $data['tables'] ?? null;
        if (!is_array($tables) || array_diff(array_keys($tables), self::TABLES) || array_diff(self::TABLES, array_keys($tables))) throw new InvalidArgumentException('Daftar tabel dalam file import tidak sesuai. Akun pengguna tidak dapat diimport.');
        $ids = []; $totalRows = 0;
        foreach (self::TABLES as $table) {
            if (!is_array($tables[$table]) || !array_is_list($tables[$table])) throw new InvalidArgumentException('Struktur tabel import tidak valid.');
            $columns = $this->db->query('PRAGMA table_info("' . $table . '")')->fetchAll(PDO::FETCH_COLUMN, 1);
            $ids[$table] = [];
            foreach ($tables[$table] as &$row) {
                if (!is_array($row) || !is_int($row['id'] ?? null) || $row['id'] < 1 || isset($ids[$table][$row['id']])) throw new InvalidArgumentException('ID data tidak valid atau berulang pada tabel ' . $table . '.');
                if (array_diff(array_keys($row), $columns)) throw new InvalidArgumentException('Kolom file tidak cocok dengan aplikasi pada tabel ' . $table . '.');
                foreach ($row as $value) {
                    if (!is_null($value) && !is_scalar($value)) throw new InvalidArgumentException('Nilai data harus berupa teks, angka, atau null.');
                }
                if (in_array('company_id', $columns, true)) {
                    $row['company_id'] = $row['company_id'] ?? 1;
                    if ($row['company_id'] !== 1) throw new InvalidArgumentException('Import ini hanya mendukung satu perusahaan dengan ID 1.');
                }
                if ($table === 'projects' && ($row['status'] ?? '') === 'completed') $row['status'] = 'done';
                $ids[$table][$row['id']] = true;
                if (++$totalRows > 100000) throw new InvalidArgumentException('Jumlah record melebihi batas import.');
            }
            unset($row);
        }
        if (count($ids['companies']) !== 1 || !isset($ids['companies'][1])) throw new InvalidArgumentException('Identitas perusahaan dalam file tidak cocok.');
        if (!$ids['projects'] && !$ids['clients']) throw new InvalidArgumentException('File tidak berisi proyek atau klien untuk dimigrasikan.');
        $relations = [
            ['projects', 'client_id', 'clients', false], ['projects', 'service_id', 'services', false], ['projects', 'partner_id', 'partners', false],
            ['transactions', 'project_id', 'projects', true], ['project_fees', 'project_id', 'projects', true],
            ['project_documents', 'project_id', 'projects', true], ['service_costs', 'service_id', 'services', true]
        ];
        foreach ($relations as [$table, $column, $parent, $required]) {
            foreach ($tables[$table] as $row) {
                $id = $row[$column] ?? null;
                if (($required && $id === null) || ($id !== null && (!is_int($id) || !isset($ids[$parent][$id])))) throw new InvalidArgumentException('Relasi ' . $table . '.' . $column . ' tidak valid.');
            }
        }
        $files = []; $totalBytes = 0;
        if (!is_array($data['files'] ?? null) || !array_is_list($data['files'])) throw new InvalidArgumentException('Daftar berkas import tidak valid.');
        foreach ($data['files'] as $entry) {
            if (!is_array($entry)) throw new InvalidArgumentException('Record berkas import tidak valid.');
            $path = $entry['path'] ?? '';
            if (!is_string($path) || !str_starts_with($path, 'uploads/') || preg_match('/[\\\\\x00-\x1f]/', $path)) throw new InvalidArgumentException('Path dokumen tidak valid.');
            foreach (explode('/', $path) as $segment) {
                if ($segment === '' || str_starts_with($segment, '.')) throw new InvalidArgumentException('Path dokumen tidak diizinkan.');
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt'], true)) throw new InvalidArgumentException('Jenis berkas import tidak diizinkan.');
            if (isset($files[$path]) || !is_string($entry['content_base64'] ?? null)) throw new InvalidArgumentException('Berkas import berulang atau tidak valid.');
            $bytes = base64_decode($entry['content_base64'], true);
            if ($bytes === false || !is_string($entry['sha256'] ?? null) || !hash_equals(hash('sha256', $bytes), $entry['sha256'])) throw new InvalidArgumentException('Checksum berkas import tidak cocok.');
            $totalBytes += strlen($bytes);
            if ($totalBytes > self::MAX_BYTES) throw new InvalidArgumentException('Ukuran berkas hasil ekstraksi melebihi batas.');
            $files[$path] = $bytes;
        }
        foreach ($tables['project_documents'] as $doc) {
            if (!isset($files[$doc['file_path'] ?? ''])) throw new InvalidArgumentException('Berkas dokumen yang tercatat tidak ditemukan dalam paket.');
        }
        $notes = $data['notes'] ?? [];
        if (!is_array($notes) || count($notes) > 20) throw new InvalidArgumentException('Catatan paket tidak valid.');
        foreach ($notes as $note) if (!is_string($note) || strlen($note) > 1000) throw new InvalidArgumentException('Catatan paket tidak valid.');
        return ['tables' => $tables, 'files' => $files, 'notes' => $notes];
    }

    public function inspect(string $file): array {
        $data = $this->read($file);
        $counts = [];
        foreach (self::TABLES as $table) $counts[$table] = count($data['tables'][$table]);
        return ['counts' => $counts, 'file_count' => count($data['files']), 'notes' => $data['notes'], 'package_hash' => hash_file('sha256', $file)];
    }

    public function targetState(): array {
        // general_expenses.php can create an INTERNAL row merely by opening the page.
        // Only the exact, unlinked, zero-value bookkeeping row is disposable.
        $ids = $this->db->query("SELECT p.id FROM projects p
            WHERE p.company_id = 1 AND p.client_name = 'INTERNAL'
              AND p.service_name = 'KEUANGAN PERUSAHAAN' AND p.status = 'internal'
              AND COALESCE(p.initial_price, 0) = 0 AND COALESCE(p.original_price, 0) = 0
              AND COALESCE(p.total_price, 0) = 0 AND COALESCE(p.discount, 0) = 0
              AND COALESCE(p.name_fee, 0) = 0 AND COALESCE(p.partner_commission, 0) = 0
              AND COALESCE(p.description, '') = '' AND COALESCE(p.due_date, '') = ''
              AND COALESCE(p.additional_fee_label, '') = '' AND COALESCE(p.payment_link, '') = ''
              AND (p.client_id IS NULL OR p.client_id = '')
              AND (p.service_id IS NULL OR p.service_id = '')
              AND (p.partner_id IS NULL OR p.partner_id = '')
              AND NOT EXISTS (SELECT 1 FROM transactions t WHERE t.project_id = p.id)
              AND NOT EXISTS (SELECT 1 FROM project_fees f WHERE f.project_id = p.id)
              AND NOT EXISTS (SELECT 1 FROM project_documents d WHERE d.project_id = p.id)
        ")->fetchAll(PDO::FETCH_COLUMN);
        $counts = [];
        foreach (['projects', 'transactions', 'clients', 'services', 'service_costs', 'project_fees', 'project_documents'] as $table) {
            $counts[$table] = (int)$this->db->query('SELECT COUNT(*) FROM "' . $table . '"')->fetchColumn();
        }
        $counts['projects'] -= count($ids);
        return ['business_counts' => $counts, 'empty_internal_ids' => array_map('intval', $ids)];
    }

    public function requireEmptyTarget(): void {
        $labels = ['projects' => 'proyek bisnis', 'transactions' => 'transaksi', 'clients' => 'klien', 'services' => 'layanan', 'service_costs' => 'biaya layanan', 'project_fees' => 'biaya tambahan', 'project_documents' => 'dokumen proyek'];
        $found = [];
        foreach ($this->targetState()['business_counts'] as $table => $count) {
            if ($count > 0) $found[] = $count . ' ' . $labels[$table];
        }
        if ($found) throw new RuntimeException('Import belum dapat dilanjutkan: ditemukan ' . implode(', ', $found) . '. Data tersebut tetap dipertahankan.');
        if ($this->db->query('SELECT COUNT(*) FROM data_imports')->fetchColumn() > 0) throw new RuntimeException('Aplikasi ini sudah pernah melakukan import. Periksa Dashboard dan riwayat import.');
        if ($this->db->query('SELECT COUNT(*) FROM partners')->fetchColumn() > 1 || $this->db->query('SELECT COUNT(*) FROM companies')->fetchColumn() != 1 || $this->db->query('SELECT COUNT(*) FROM companies WHERE id = 1')->fetchColumn() != 1 || $this->db->query('SELECT COUNT(*) FROM users WHERE company_id IS NOT 1')->fetchColumn() > 0) throw new RuntimeException('Struktur perusahaan atau mitra tujuan tidak sesuai dengan instalasi baru.');
    }

    public function apply(string $file, string $username): array {
        $data = $this->read($file);
        $hash = hash_file('sha256', $file);
        $this->requireEmptyTarget();
        $backupDir = $this->root . '/storage/import-backups';
        $this->directory($backupDir);
        $backup = $backupDir . '/before-import-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.sqlite';
        // Snapshot on a separate connection, independent of request statements.
        $databaseFile = Env::get('DB_DATABASE', dirname(__DIR__) . '/config/database.sqlite');
        $snapshot = new PDO('sqlite:' . $databaseFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $snapshot->exec('PRAGMA busy_timeout = 5000');
        $snapshot->exec('VACUUM INTO ' . $snapshot->quote($backup));
        $snapshot = null;
        $created = [];
        $counts = [];
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            // Recheck after obtaining a write lock so a concurrent writer cannot be overwritten.
            $this->requireEmptyTarget();
            $check = $this->db->prepare('SELECT id FROM data_imports WHERE package_hash = ?');
            $check->execute([$hash]);
            $alreadyImported = $check->fetchColumn();
            $check->closeCursor();
            if ($alreadyImported) throw new RuntimeException('Paket ini sudah pernah diimport.');
            foreach ($data['files'] as $path => $bytes) {
                $parent = $this->root;
                $segments = explode('/', $path);
                array_pop($segments);
                foreach ($segments as $segment) { $parent .= '/' . $segment; $this->directory($parent); }
                $target = $this->root . '/' . $path;
                if (is_link($target)) throw new RuntimeException('Berkas tujuan berupa symbolic link.');
                if (file_exists($target)) {
                    if (!is_file($target) || !hash_equals(hash('sha256', $bytes), hash_file('sha256', $target))) throw new RuntimeException('Berkas tujuan sudah ada dengan isi berbeda; import dibatalkan.');
                    continue;
                }
                $handle = fopen($target, 'x+b');
                if ($handle === false) throw new RuntimeException('Dokumen tidak dapat disimpan.');
                $created[] = $target;
                try {
                    $written = 0;
                    while ($written < strlen($bytes)) {
                        $part = fwrite($handle, substr($bytes, $written));
                        if ($part === false || $part === 0) throw new RuntimeException('Penulisan dokumen tidak lengkap.');
                        $written += $part;
                    }
                } finally { fclose($handle); }
                chmod($target, 0640);
            }
            $emptyIds = $this->targetState()['empty_internal_ids'];
            if ($emptyIds) {
                $stmt = $this->db->prepare('DELETE FROM projects WHERE id IN (' . implode(', ', array_fill(0, count($emptyIds), '?')) . ')');
                $stmt->execute($emptyIds);
                $stmt->closeCursor();
            }
            $this->db->exec('DELETE FROM settings');
            $this->db->exec('DELETE FROM partners');
            foreach (self::TABLES as $table) {
                $counts[$table] = count($data['tables'][$table]);
                foreach ($data['tables'][$table] as $row) {
                    $columns = array_keys($row);
                    if ($table === 'companies') {
                        unset($row['id']);
                        $assignments = array_map(fn($column) => '"' . $column . '" = ?', array_keys($row));
                        $stmt = $this->db->prepare('UPDATE companies SET ' . implode(', ', $assignments) . ' WHERE id = 1');
                    } else {
                        $quoted = array_map(fn($column) => '"' . $column . '"', $columns);
                        $stmt = $this->db->prepare('INSERT INTO "' . $table . '" (' . implode(', ', $quoted) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
                    }
                    $stmt->execute(array_values($row));
                    $stmt->closeCursor();
                }
            }
            if ($this->db->query('PRAGMA foreign_key_check')->fetch()) throw new RuntimeException('Pemeriksaan relasi database gagal.');
            $stmt = $this->db->prepare('INSERT INTO data_imports (package_hash, counts_json, username, backup_path) VALUES (?, ?, ?, ?)');
            $stmt->execute([$hash, json_encode($counts, JSON_THROW_ON_ERROR), $username, $backup]);
            $importId = (int)$this->db->lastInsertId();
            Logger::log($this->db, 'IMPORT_DATA', 'Import data lama: ' . $counts['projects'] . ' proyek, ' . $counts['transactions'] . ' transaksi.');
            $this->db->exec('COMMIT');
            return ['id' => $importId, 'counts' => $counts, 'backup_path' => $backup];
        } catch (\Throwable $e) {
            if (isset($stmt)) $stmt->closeCursor();
            if (isset($check)) $check->closeCursor();
            $this->db->exec('ROLLBACK');
            foreach ($created as $path) if (is_file($path)) unlink($path);
            throw $e;
        }
    }

    public function history(): array {
        return $this->db->query('SELECT id, counts_json, created_at, username FROM data_imports ORDER BY id DESC LIMIT 10')->fetchAll();
    }

    public function backupPath(int $id): string {
        $stmt = $this->db->prepare('SELECT backup_path FROM data_imports WHERE id = ?');
        $stmt->execute([$id]);
        $file = realpath($stmt->fetchColumn() ?: '');
        $root = realpath($this->root . '/storage/import-backups');
        if (!$file || !$root || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) throw new RuntimeException('Backup tidak ditemukan.');
        return $file;
    }
}
