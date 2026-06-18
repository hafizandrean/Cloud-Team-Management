<?php
/**
 * Cloud Team Management - Hapus Proyek
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/activity_helper.php';

// Protect page (Admin only)
requireRole('admin');

$db = Database::getConnection();

// Get and validate Project ID
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_error'] = 'ID Proyek tidak valid.';
    header('Location: index.php');
    exit;
}

try {
    // 1. Check if project exists
    $stmt = $db->prepare("SELECT * FROM proyek WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        $_SESSION['flash_error'] = 'Proyek tidak ditemukan.';
        header('Location: index.php');
        exit;
    }

    // 2. Soft Protection Delete Check
    // Prevent deletion if project has assigned members in junction table
    $checkQuery = "SELECT COUNT(*) FROM anggota_proyek WHERE proyek_id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$id]);
    $assignmentCount = $checkStmt->fetchColumn();
    
    if ($assignmentCount > 0) {
        $_SESSION['flash_error'] = 'Proyek masih memiliki anggota. Hapus assignment terlebih dahulu.';
        header('Location: index.php');
        exit;
    }

    // 3. Delete the project
    $deleteQuery = "DELETE FROM proyek WHERE id = ?";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->execute([$id]);

    // Write activity log
    writeLog($db, $_SESSION['user_id'], 'DELETE_PROJECT', 'Menghapus proyek: ' . $project['nama_proyek']);

    $_SESSION['flash_success'] = 'Proyek berhasil dihapus.';
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Gagal menghapus proyek: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
