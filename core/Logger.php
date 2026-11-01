<?php
namespace Core;

use Exception;
use PDO;

class Logger {
    public static function log($pdo, $action, $description) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $user_id = $_SESSION['user_id'] ?? 0;
        $username = $_SESSION['username'] ?? 'System';
        $company_id = $_SESSION['company_id'] ?? 1;
        
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $tableReady = false;
            try {
                $pdo->query("SELECT 1 FROM activity_logs LIMIT 1");
                $tableReady = true;
            } catch (Exception $e) {
                // create table if missing
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
                $tableReady = true;
            }
            if (!$tableReady) {
                return;
            }
            $stmt = $pdo->prepare("INSERT INTO activity_logs (company_id, user_id, username, action, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$company_id, $user_id, $username, $action, $description]);
        } catch (Exception $e) {
            // Silent fail
        }
    }
}
