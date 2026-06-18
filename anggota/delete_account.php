<?php
/**
 * Cloud Team Management - Hapus Akun Saya
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/activity_helper.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

$db = Database::getConnection();

// Get logged in user info
$currentUser = $_SESSION['user_id'] ?? 0;
$currentAnggota = $_SESSION['anggota_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Metode permintaan tidak valid.';
    header('Location: detail.php?id=' . $currentAnggota);
    exit;
}

try {
    // 1. Fetch current anggota and user data
    $stmt = $db->prepare("SELECT a.*, u.role FROM anggota a JOIN users u ON a.id_user = u.id WHERE a.id = ? AND a.id_user = ?");
    $stmt->execute([$currentAnggota, $currentUser]);
    $member = $stmt->fetch();

    if (!$member) {
        $_SESSION['flash_error'] = 'Data akun Anda tidak ditemukan.';
        header('Location: ../dashboard/index.php');
        exit;
    }

    // 2. Security Rule: If the user is an Admin, check if they are the last Admin
    if ($member['role'] === 'admin') {
        $adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            $_SESSION['flash_error'] = 'Penghapusan akun ditolak. Sistem harus memiliki minimal satu Admin.';
            header('Location: detail.php?id=' . $currentAnggota);
            exit;
        }
    }

    // 3. Delete physical photo file from local disk if it exists
    $fotoPath = $member['foto'];
    if (!empty($fotoPath)) {
        $fullPath = __DIR__ . '/../uploads/' . $fotoPath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    // Start transaction to delete user and member atomically
    $db->beginTransaction();

    // 4. Delete the user
    $deleteUserStmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $deleteUserStmt->execute([$currentUser]);

    // 5. Delete the member from database
    $deleteQuery = "DELETE FROM anggota WHERE id = ?";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->execute([$currentAnggota]);

    $db->commit();

    // 6. Destroy session
    session_destroy();
    
    // Start clean session for flash success
    session_start();
    $_SESSION['flash_success'] = 'Akun Anda berhasil dihapus dari sistem.';
    header('Location: ../auth/login.php');
    exit;

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['flash_error'] = 'Gagal menghapus akun: ' . $e->getMessage();
    header('Location: detail.php?id=' . $currentAnggota);
    exit;
}
