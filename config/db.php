<?php
// config/db.php
// Wrapper for Core\Database to maintain backward compatibility

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

// Initialize secure session (shared)
require_once __DIR__ . '/session.php';

use Core\Env;
use Core\Database;
use Core\Helpers;
use Core\Logger;

// Load Env
Env::load(__DIR__ . '/../.env');

// Connect DB
$pdo = Database::connect();

// Helper functions (Global Shim)

if (!function_exists('env')) {
    function env($key, $default = null) {
        return Env::get($key, $default);
    }
}

if (!function_exists('formatRupiah')) {
    function formatRupiah($number) {
        return Helpers::formatRupiah($number);
    }
}

if (!function_exists('logActivity')) {
    function logActivity($pdo, $action, $description) {
        Logger::log($pdo, $action, $description);
    }
}
if (!function_exists('currentCompanyId')) {
    function currentCompanyId() {
        return Helpers::currentCompanyId();
    }
}
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $tableExists = false;
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='activity_logs'");
        $stmt->execute();
        $tableExists = $stmt->fetchColumn() > 0;
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
        $tableExists = $stmt && $stmt->rowCount() > 0;
    }
    if ($tableExists) {
        $hasCreatedAt = false;
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("PRAGMA table_info(activity_logs)");
            $stmt->execute();
            $cols = $stmt->fetchAll();
            foreach ($cols as $c) {
                if (strcasecmp($c['name'], 'created_at') === 0) { $hasCreatedAt = true; break; }
            }
            if (!$hasCreatedAt) {
                $pdo->exec("ALTER TABLE activity_logs ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
                $pdo->exec("UPDATE activity_logs SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL");
            }
        } else {
            $stmt = $pdo->query("SHOW COLUMNS FROM activity_logs LIKE 'created_at'");
            $hasCreatedAt = $stmt && $stmt->rowCount() > 0;
            if (!$hasCreatedAt) {
                $pdo->exec("ALTER TABLE activity_logs ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
                $pdo->exec("UPDATE activity_logs SET created_at = NOW() WHERE created_at IS NULL");
            }
        }
    } else {
        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS activity_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    company_id INTEGER DEFAULT 1,
                    user_id INTEGER,
                    username TEXT,
                    action TEXT,
                    description TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    company_id INT DEFAULT 1,
                    user_id INT,
                    username VARCHAR(255),
                    action VARCHAR(255),
                    description TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
    }
} catch (Exception $e) {}
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $tableExists = false;
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='projects'");
        $stmt->execute();
        $tableExists = $stmt->fetchColumn() > 0;
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'projects'");
        $tableExists = $stmt && $stmt->rowCount() > 0;
    }
    if ($tableExists) {
        $hasPaymentLink = false;
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("PRAGMA table_info(projects)");
            $stmt->execute();
            $cols = $stmt->fetchAll();
            foreach ($cols as $c) {
                if (strcasecmp($c['name'], 'payment_link') === 0) { $hasPaymentLink = true; break; }
            }
            if (!$hasPaymentLink) {
                $pdo->exec("ALTER TABLE projects ADD COLUMN payment_link TEXT");
            }
        } else {
            $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'payment_link'");
            $hasPaymentLink = $stmt && $stmt->rowCount() > 0;
            if (!$hasPaymentLink) {
                $pdo->exec("ALTER TABLE projects ADD COLUMN payment_link TEXT");
            }
        }
    }
} catch (Exception $e) {}
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $tableExists = false;
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='settings'");
        $stmt->execute();
        $tableExists = $stmt->fetchColumn() > 0;
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        $tableExists = $stmt && $stmt->rowCount() > 0;
    }
    if (!$tableExists) {
        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    company_name TEXT,
                    company_address TEXT,
                    company_phone TEXT,
                    company_logo TEXT,
                    bank_name TEXT,
                    bank_account TEXT,
                    bank_holder TEXT,
                    payment_link TEXT,
                    reminder_wa_template TEXT,
                    reminder_email_subject TEXT,
                    reminder_email_body TEXT,
                    reminder_min_interval_days INTEGER DEFAULT 7,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    company_name VARCHAR(255),
                    company_address TEXT,
                    company_phone VARCHAR(255),
                    company_logo VARCHAR(255),
                    bank_name VARCHAR(255),
                    bank_account VARCHAR(255),
                    bank_holder VARCHAR(255),
                    payment_link TEXT,
                    reminder_wa_template TEXT,
                    reminder_email_subject VARCHAR(255),
                    reminder_email_body TEXT,
                    reminder_min_interval_days INTEGER DEFAULT 7,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
    } else {
        $hasWa = false; $hasSub = false; $hasBody = false; $hasPaymentLink = false;
        $hasInterval = false;
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("PRAGMA table_info(settings)");
            $stmt->execute();
            $cols = $stmt->fetchAll();
            foreach ($cols as $c) {
                if (strcasecmp($c['name'], 'reminder_wa_template') === 0) $hasWa = true;
                if (strcasecmp($c['name'], 'reminder_email_subject') === 0) $hasSub = true;
                if (strcasecmp($c['name'], 'reminder_email_body') === 0) $hasBody = true;
                if (strcasecmp($c['name'], 'reminder_min_interval_days') === 0) $hasInterval = true;
                if (strcasecmp($c['name'], 'payment_link') === 0) $hasPaymentLink = true;
            }
            if (!$hasWa) { $pdo->exec("ALTER TABLE settings ADD COLUMN reminder_wa_template TEXT"); }
            if (!$hasSub) { $pdo->exec("ALTER TABLE settings ADD COLUMN reminder_email_subject TEXT"); }
            if (!$hasBody) { $pdo->exec("ALTER TABLE settings ADD COLUMN reminder_email_body TEXT"); }
            if (!$hasInterval) { $pdo->exec("ALTER TABLE settings ADD COLUMN reminder_min_interval_days INTEGER DEFAULT 7"); }
            if (!$hasPaymentLink) { $pdo->exec("ALTER TABLE settings ADD COLUMN payment_link TEXT"); }
        } else {
            $stmt = $pdo->query("SHOW COLUMNS FROM settings LIKE 'reminder_wa_template'");
            $hasWa = $stmt && $stmt->rowCount() > 0;
            $stmt = $pdo->query("SHOW COLUMNS FROM settings LIKE 'reminder_email_subject'");
            $hasSub = $stmt && $stmt->rowCount() > 0;
            $stmt = $pdo->query("SHOW COLUMNS FROM settings LIKE 'reminder_email_body'");
            $hasBody = $stmt && $stmt->rowCount() > 0;
            if (!$hasWa) { $pdo->exec("ALTER TABLE settings ADD COLUMN reminder_wa_template TEXT"); }
            if (!$hasSub) { $pdo->exec("ALTER TABLE settings ADD COLUMN reminder_email_subject VARCHAR(255)"); }
            if (!$hasBody) { $pdo->exec("ALTER TABLE settings ADD COLUMN reminder_email_body TEXT"); }
            $stmt = $pdo->query("SHOW COLUMNS FROM settings LIKE 'reminder_min_interval_days'");
            $hasInterval = $stmt && $stmt->rowCount() > 0;
            if (!$hasInterval) { $pdo->exec("ALTER TABLE settings ADD COLUMN reminder_min_interval_days INT DEFAULT 7"); }
            $stmt = $pdo->query("SHOW COLUMNS FROM settings LIKE 'payment_link'");
            $hasPaymentLink = $stmt && $stmt->rowCount() > 0;
            if (!$hasPaymentLink) { $pdo->exec("ALTER TABLE settings ADD COLUMN payment_link TEXT"); }
        }
    }
} catch (Exception $e) {}
