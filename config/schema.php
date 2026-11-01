<?php
// config/schema.php
// Handles Database Schema Creation and Migrations for SQLite and MySQL

// Ensure $pdo is available
if (!isset($pdo)) {
    die("Database connection not established.");
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$isMysql = $driver === 'mysql';

// --- Type Definitions ---
$TYPE_PK = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";
$TYPE_TEXT = "TEXT"; // Fits both (MySQL TEXT is 64KB, enough for names/desc)
$TYPE_STRING = $isMysql ? "VARCHAR(255)" : "TEXT"; // Optimization for MySQL
$TYPE_MONEY = $isMysql ? "DECIMAL(15,2) DEFAULT 0" : "REAL DEFAULT 0";
$TYPE_INT = $isMysql ? "INT" : "INTEGER";
$TYPE_DATETIME = "DATETIME DEFAULT CURRENT_TIMESTAMP";
$TYPE_DATE = "DATE";

// --- Helpers ---

if (!function_exists('tableExists')) {
    function tableExists($pdo, $table) {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT count(*) FROM sqlite_master WHERE type='table' AND name=?");
                $stmt->execute([$table]);
                return $stmt->fetchColumn() > 0;
            } elseif ($driver === 'mysql') {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                return $stmt->rowCount() > 0;
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }
}

if (!function_exists('hasColumn')) {
    function hasColumn($pdo, $table, $column) {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("PRAGMA table_info($table)");
                $stmt->execute();
                $cols = $stmt->fetchAll();
                foreach ($cols as $c) {
                    if (strcasecmp($c['name'], $column) === 0) return true;
                }
            } elseif ($driver === 'mysql') {
                $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
                $stmt->execute([$column]);
                return $stmt->rowCount() > 0;
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }
}

// --- Schema Definition ---

$commands = [
    "CREATE TABLE IF NOT EXISTS companies (
        id $TYPE_PK,
        name $TYPE_STRING NOT NULL,
        address $TYPE_TEXT,
        phone $TYPE_STRING,
        logo $TYPE_STRING,
        created_at $TYPE_DATETIME
    )",
    "CREATE TABLE IF NOT EXISTS projects (
        id $TYPE_PK,
        company_id $TYPE_INT DEFAULT 1,
        client_name $TYPE_STRING NOT NULL,
        service_name $TYPE_STRING NOT NULL,
        initial_price $TYPE_MONEY,
        discount $TYPE_MONEY,
        total_price $TYPE_MONEY,
        status $TYPE_STRING DEFAULT 'ongoing',
        created_at $TYPE_DATETIME,
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )",
    "CREATE TABLE IF NOT EXISTS transactions (
        id $TYPE_PK,
        company_id $TYPE_INT DEFAULT 1,
        project_id $TYPE_INT NOT NULL,
        type $TYPE_STRING NOT NULL,
        category $TYPE_STRING,
        amount $TYPE_MONEY,
        note $TYPE_TEXT,
        transaction_date $TYPE_DATE,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )",
    "CREATE TABLE IF NOT EXISTS clients (
        id $TYPE_PK, 
        company_id $TYPE_INT DEFAULT 1,
        name $TYPE_STRING, 
        phone $TYPE_STRING, 
        email $TYPE_STRING, 
        address $TYPE_TEXT, 
        npwp $TYPE_STRING,
        UNIQUE(name),
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )",
    "CREATE TABLE IF NOT EXISTS project_fees (
        id $TYPE_PK, 
        project_id $TYPE_INT NOT NULL, 
        label $TYPE_STRING NOT NULL, 
        amount $TYPE_MONEY,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS project_documents (
        id $TYPE_PK,
        project_id $TYPE_INT NOT NULL,
        category $TYPE_STRING NOT NULL,
        file_name $TYPE_STRING NOT NULL,
        file_path $TYPE_TEXT NOT NULL,
        uploaded_at $TYPE_DATETIME,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS partners (
        id $TYPE_PK,
        company_id $TYPE_INT DEFAULT 1,
        name $TYPE_STRING NOT NULL,
        address $TYPE_TEXT,
        phone $TYPE_STRING,
        logo $TYPE_STRING,
        bank_name $TYPE_STRING,
        bank_account $TYPE_STRING,
        bank_holder $TYPE_STRING,
        is_default $TYPE_INT DEFAULT 0,
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )",
    "CREATE TABLE IF NOT EXISTS services (
        id $TYPE_PK,
        company_id $TYPE_INT DEFAULT 1,
        name $TYPE_STRING NOT NULL,
        category $TYPE_STRING,
        default_price $TYPE_MONEY,
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )",
    "CREATE TABLE IF NOT EXISTS users (
        id $TYPE_PK,
        company_id $TYPE_INT DEFAULT 1,
        username $TYPE_STRING NOT NULL,
        password $TYPE_STRING NOT NULL,
        role $TYPE_STRING DEFAULT 'admin',
        created_at $TYPE_DATETIME,
        UNIQUE(username),
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )",
    "CREATE TABLE IF NOT EXISTS activity_logs (
        id $TYPE_PK,
        company_id $TYPE_INT DEFAULT 1,
        user_id $TYPE_INT,
        username $TYPE_STRING,
        action $TYPE_STRING,
        description $TYPE_TEXT,
        created_at $TYPE_DATETIME,
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )",
    "CREATE TABLE IF NOT EXISTS service_costs (
        id $TYPE_PK,
        service_id $TYPE_INT NOT NULL,
        description $TYPE_STRING NOT NULL,
        amount $TYPE_MONEY,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    )"
];

foreach ($commands as $command) {
    // Remove UNIQUE(name) constraint for MySQL if handled differently or keep standard
    // MySQL supports UNIQUE(col) in create table
    try {
        $pdo->exec($command);
    } catch (PDOException $e) {
        throw $e;
        
    }
}

// --- Migration: Add company_id to existing tables ---
$tablesToMigrate = ['projects', 'transactions', 'clients', 'partners', 'services', 'users', 'activity_logs'];
foreach ($tablesToMigrate as $table) {
    if (tableExists($pdo, $table) && !hasColumn($pdo, $table, 'company_id')) {
        try {
            $defaultVal = 1; // Default company ID
            $sql = "ALTER TABLE $table ADD COLUMN company_id $TYPE_INT DEFAULT $defaultVal";
            // For SQLite, adding FK in ALTER is restricted/complex, so we just add the column for now.
            // Constraint enforcement might require recreation in SQLite, but for now we trust app logic.
            // MySQL supports ADD CONSTRAINT but we keep it simple.
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Log error
        }
    }
}

// --- Seeding ---

// Default Company
if (tableExists($pdo, 'companies') && $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn() == 0) {
    $stmt = $pdo->prepare("INSERT INTO companies (name, address, logo, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute(['Legaone', 'Jl. Contoh No. 123, Jakarta', 'logo.png']);
}

// Default Partner
if ($pdo->query("SELECT COUNT(*) FROM partners")->fetchColumn() == 0) {
    // Try to migrate from settings (SQLite legacy)
    $migrated = false;
    if ($driver === 'sqlite' && tableExists($pdo, 'settings')) {
        $currentSettings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
        if ($currentSettings) {
            $stmt = $pdo->prepare("INSERT INTO partners (name, address, phone, logo, bank_name, bank_account, bank_holder, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([
                $currentSettings['company_name'], 
                $currentSettings['company_address'], 
                $currentSettings['company_phone'] ?? '', 
                $currentSettings['company_logo'], 
                $currentSettings['bank_name'], 
                $currentSettings['bank_account'], 
                $currentSettings['bank_holder']
            ]);
            $migrated = true;
        }
    }
    
    if (!$migrated) {
        $stmt = $pdo->prepare("INSERT INTO partners (name, address, logo, bank_name, bank_account, bank_holder, is_default) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute(['Legaone', 'Jl. Contoh No. 123, Jakarta', 'logo.png', '', '', '']);
    }
}

// Default Admin
if ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0) {
    $adminPassword = \Core\Env::get('ADMIN_PASSWORD', '');
    if (strlen($adminPassword) < 12) {
        throw new \RuntimeException('Set ADMIN_PASSWORD (minimal 12 karakter) sebelum inisialisasi database.');
    }
    $password = password_hash($adminPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([\Core\Env::get('ADMIN_USERNAME', 'admin'), $password, 'admin']);
}

// --- Migrations (Add Columns) ---

$migrations = [
    ['projects', 'original_price', $TYPE_MONEY],
    ['projects', 'service_id', $TYPE_INT],
    ['projects', 'client_id', $TYPE_INT],
    ['projects', 'name_fee', $TYPE_MONEY],
    ['projects', 'description', $TYPE_TEXT],
    ['projects', 'additional_fee_label', $TYPE_STRING],
    ['projects', 'due_date', $TYPE_DATE],
    ['projects', 'partner_id', $TYPE_INT],
    ['projects', 'partner_commission', $TYPE_MONEY],
    ['transactions', 'status', $TYPE_STRING],
    ['transactions', 'due_date', $TYPE_DATE],
];

foreach ($migrations as $mig) {
    if (!hasColumn($pdo, $mig[0], $mig[1])) {
        try {
            $pdo->exec("ALTER TABLE {$mig[0]} ADD COLUMN {$mig[1]} {$mig[2]}");
            if ($mig[0] === 'projects' && $mig[1] === 'original_price') {
                $pdo->exec("UPDATE projects SET original_price = initial_price");
            }
        } catch (Exception $e) { throw $e; }
    }
}

$pdo->exec("UPDATE projects SET status = 'done' WHERE status = 'completed'");

// Data Migration: Move name_fee to project_fees
if (tableExists($pdo, 'project_fees') && $pdo->query("SELECT COUNT(*) FROM project_fees")->fetchColumn() == 0) {
    if (hasColumn($pdo, 'projects', 'name_fee')) {
        $projectsWithFees = $pdo->query("SELECT id, name_fee, additional_fee_label FROM projects WHERE name_fee > 0")->fetchAll();
        $stmtFee = $pdo->prepare("INSERT INTO project_fees (project_id, label, amount) VALUES (?, ?, ?)");
        foreach ($projectsWithFees as $p) {
            $label = $p['additional_fee_label'] ?: 'Biaya Pesan Nama';
            $stmtFee->execute([$p['id'], $label, $p['name_fee']]);
        }
    }
}
