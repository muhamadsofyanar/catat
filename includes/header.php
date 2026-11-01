<?php
require_once __DIR__ . '/auth.php';
if (!isset($_SESSION['role'])) {
    require_once __DIR__ . '/../config/db.php';
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $role = $stmt->fetchColumn();
    $_SESSION['role'] = $role ?: 'staff';
}
function isActive($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return ($current === $page) ? 'active' : '';
}
if (isset($base_url)) {
    $base = $base_url;
} else {
    $script_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $base = (strtolower(basename($script_dir)) === 'public') ? '' : 'public/';
}
$brandLogo = null;
if (!isset($pdo)) {
    $dbFile = __DIR__ . '/../config/db.php';
    if (file_exists($dbFile)) require $dbFile;
}
if (isset($pdo)) {
    try {
        $s = $pdo->query("SELECT company_logo FROM settings LIMIT 1")->fetch();
        $val = $s['company_logo'] ?? '';
        if ($val) {
            if (preg_match('/^https?:\\/\\//i', $val)) {
                $brandLogo = $val;
            } else {
                $brandLogo = ltrim($val, '/');
            }
        }
    } catch (\Exception $e) {}
}
if (!$brandLogo) {
    $publicDir = __DIR__ . '/../public/';
    if (file_exists($publicDir.'logo.png')) $brandLogo = 'logo.png';
    elseif (file_exists($publicDir.'logo.jpg')) $brandLogo = 'logo.jpg';
    elseif (file_exists($publicDir.'logo.jpeg')) $brandLogo = 'logo.jpeg';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle : 'LegaVoice Project Manager' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #3b82f6; /* Modern Blue */
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #0ea5e9;
            --light-bg: #f1f5f9;
            --card-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.05), 0 4px 6px -4px rgb(0 0 0 / 0.05);
            --navbar-bg: rgba(15, 23, 42, 0.95);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: #334155;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Navbar Modernization */
        .navbar {
            background-color: var(--navbar-bg) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
            font-size: 1.35rem;
            color: #fff !important;
        }
        
        .nav-link {
            font-weight: 500;
            color: #94a3b8 !important;
            transition: all 0.2s ease;
            padding: 0.6rem 1rem !important;
            border-radius: 0.5rem;
            font-size: 0.95rem;
        }
        
        .nav-link:hover {
            color: #fff !important;
            background-color: rgba(255,255,255,0.1);
            transform: translateY(-1px);
        }
        
        .nav-link.active {
            color: #fff !important;
            background-color: var(--primary-color);
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.5);
        }
        
        /* Card Improvements */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: #fff;
            overflow: hidden;
        }
        
        .card:hover {
            /* transform: translateY(-2px); */ /* Optional: subtle lift */
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.05), 0 8px 10px -6px rgb(0 0 0 / 0.05);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #f1f5f9;
            padding: 1.25rem 1.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Button Modernization */
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            transition: all 0.2s;
            letter-spacing: 0.01em;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4);
        }
        
        .btn-primary:hover {
            background-color: #2563eb;
            border-color: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 6px 8px -1px rgba(59, 130, 246, 0.5);
        }

        /* Table Improvements */
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 1rem 1.5rem;
        }
        .table tbody td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .badge {
            font-weight: 600;
            padding: 0.5em 0.85em;
            border-radius: 9999px; /* Pill shape */
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9; 
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1; 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8; 
        }
        
        /* Form Controls */
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        /* Utilities */
        .text-small { font-size: 0.875rem; }
        .fw-medium { font-weight: 500; }
        .shadow-hover:hover { box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4 sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $base ?>index.php">
            <?php if ($brandLogo): ?>
                <img src="<?= htmlspecialchars($brandLogo) ?>" alt="Logo" style="height:28px; width:auto; border-radius:6px;">
            <?php else: ?>
                <i class="bi bi-building-fill-check text-primary"></i>
            <?php endif; ?>
            <span>LegaVoice</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= isActive('index.php') ?>" href="<?= $base ?>index.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= isActive('projects.php') ?>" href="<?= $base ?>projects.php"><i class="bi bi-briefcase me-1"></i> Daftar Proyek</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= isActive('project_form.php') ?>" href="<?= $base ?>project_form.php"><i class="bi bi-plus-lg me-1"></i> Project Baru</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= isActive('cash_flow.php') || isActive('payables.php') || isActive('receivables.php') || isActive('owner_debt.php') || isActive('general_expenses.php') ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-cash-stack me-1"></i> Keuangan
                    </a>
                    <ul class="dropdown-menu border-0">
                        <li><a class="dropdown-item" href="<?= $base ?>cash_flow.php"><i class="bi bi-wallet2 me-2"></i> Arus Kas</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>report.php"><i class="bi bi-file-bar-graph me-2"></i> Laporan Bulanan</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>general_expenses.php"><i class="bi bi-journal-text me-2"></i> Transaksi Umum</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= $base ?>payables.php"><i class="bi bi-exclamation-triangle me-2"></i> Hutang Vendor</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>receivables.php"><i class="bi bi-hourglass-split me-2"></i> Piutang Klien</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>owner_debt.php"><i class="bi bi-person-badge me-2"></i> Hutang Owner</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= $base ?>partner_payments.php"><i class="bi bi-receipt-cutoff me-2"></i> Riwayat Bayar Mitra</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= isActive('clients.php') || isActive('client_detail.php') || isActive('services.php') || isActive('partners.php') || isActive('partner_detail.php') ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-database me-1"></i> Master Data
                    </a>
                    <ul class="dropdown-menu border-0">
                        <li><a class="dropdown-item" href="<?= $base ?>clients.php"><i class="bi bi-people me-2"></i> Data Klien</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>services.php"><i class="bi bi-gear me-2"></i> Layanan</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>partners.php"><i class="bi bi-person-badge me-2"></i> Mitra</a></li>
                    </ul>
                </li>
                <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                <?php endif; ?>
            </ul>
            
            <!-- Global Search -->
            <form class="d-flex mx-3 flex-grow-1" action="<?= $base ?>search.php" method="GET" style="max-width: 400px;">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input class="form-control border-start-0 ps-0" type="search" name="q" placeholder="Cari proyek, klien..." aria-label="Search" value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
                </div>
            </form>

            <ul class="navbar-nav align-items-center">
                <li class="nav-item">
                    <a class="nav-link text-warning" href="<?= $base ?>adjust_balance_form.php" title="Penyesuaian Saldo"><i class="bi bi-tools"></i></a>
                </li>
                <li class="nav-item ms-3 dropdown">
                    <a class="nav-link dropdown-toggle text-white bg-white bg-opacity-10 px-3 rounded-pill" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i> <?= $_SESSION['username'] ?? 'User' ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0" style="z-index: 1050;">
                        <li><h6 class="dropdown-header text-uppercase fw-bold text-muted small"><?= $_SESSION['role'] ?? 'staff' ?></h6></li>
                        <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                        <li><a class="dropdown-item" href="<?= $base ?>settings.php"><i class="bi bi-gear-fill me-2"></i> Pengaturan</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>users.php"><i class="bi bi-people-fill me-2"></i> Manajemen User</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>activity_logs.php"><i class="bi bi-activity me-2"></i> Log Aktivitas</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>health_check.php"><i class="bi bi-shield-check me-2"></i> Health Check</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-danger" href="<?= $base ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
