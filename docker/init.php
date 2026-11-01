<?php
require __DIR__ . '/../config/db.php';
if (!$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
    throw new RuntimeException('Database tidak memiliki pengguna.');
}
echo "Database initialized.\n";
