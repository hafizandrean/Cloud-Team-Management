<?php
/**
 * Cloud Team Management - Hapus Anggota
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/activity_helper.php';

$db = Database::getConnection();

// Get and validate Member ID
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_error'] = 'ID Anggota tidak valid.';
    header('Location: index.php');
    exit;
}

try {
    // 1. Fetch member data to verify existence and get photo path
    $stmt = $db->prepare("SELECT * FROM anggota WHERE id = ?");
    $stmt->execute([$id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        $_SESSION['flash_error'] = 'Anggota tidak ditemukan.';
        header('Location: index.php');
        exit;
    }

    // 2. Soft Protection Delete Check
    // Check if member is still assigned to projects in the junction table
    $checkQuery = "SELECT COUNT(*) FROM anggota_proyek WHERE anggota_id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$id]);
    $assignmentCount = $checkStmt->fetchColumn();
    
    if ($assignmentCount > 0) {
        $_SESSION['flash_error'] = 'Anggota masih terhubung dengan proyek. Hapus assignment terlebih dahulu.';
        header('Location: index.php');
        exit;
    }

    // 3. Delete physical photo file from local disk if it exists
    $fotoPath = $member['foto'];
    if (!empty($fotoPath)) {
        $fullPath = __DIR__ . '/../uploads/' . $fotoPath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    // 4. Delete the member from database
    $deleteQuery = "DELETE FROM anggota WHERE id = ?";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->execute([$id]);

    // Write activity log
    writeLog($db, $_SESSION['user_id'], 'DELETE_MEMBER', 'Menghapus anggota: ' . $member['nama']);

    $_SESSION['flash_success'] = 'Anggota berhasil dihapus.';
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Gagal menghapus anggota: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
