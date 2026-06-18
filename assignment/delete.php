<?php
/**
 * Cloud Team Management - Hapus Assignment (Penugasan)
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/activity_helper.php';

// Protect page (Admin only)
requireRole('admin');

$db = Database::getConnection();

// Get and validate Assignment ID
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_error'] = 'ID Assignment tidak valid.';
    header('Location: index.php');
    exit;
}

try {
    // 1. Check if assignment exists
    $stmt = $db->prepare("SELECT * FROM anggota_proyek WHERE id = ?");
    $stmt->execute([$id]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        $_SESSION['flash_error'] = 'Assignment tidak ditemukan.';
        header('Location: index.php');
        exit;
    }

    // Fetch names for log before deletion
    $mStmt = $db->prepare("SELECT nama FROM anggota WHERE id = ?");
    $mStmt->execute([$assignment['anggota_id']]);
    $memberName = $mStmt->fetchColumn();
    $memberName = $memberName ?: 'Anggota';

    $pStmt = $db->prepare("SELECT nama_proyek FROM proyek WHERE id = ?");
    $pStmt->execute([$assignment['proyek_id']]);
    $projectName = $pStmt->fetchColumn();
    $projectName = $projectName ?: 'Proyek';

    // 2. Delete assignment
    $deleteStmt = $db->prepare("DELETE FROM anggota_proyek WHERE id = ?");
    $deleteStmt->execute([$id]);

    // Log activity
    writeLog($db, $_SESSION['user_id'], 'DELETE_ASSIGNMENT', 'Menghapus ' . $memberName . ' dari proyek ' . $projectName);

    $_SESSION['flash_success'] = 'Assignment berhasil dihapus.';
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Gagal menghapus assignment: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
