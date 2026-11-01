<?php
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses Ditolak. Hanya Admin yang dapat menghapus proyek.");
}
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $projectModel = new \Modules\Projects\Models\Project();
    $deletedProject = $projectModel->delete($id);
    if ($deletedProject) {
        $projectName = $deletedProject['client_name'] . " - " . $deletedProject['service_name'];
        logActivity($pdo, 'DELETE_PROJECT', "Deleted Project: $projectName");
    }
}
header("Location: index.php");
