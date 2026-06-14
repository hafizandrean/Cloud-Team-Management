<?php
/**
 * Cloud Team Management - Clear Activity Logs (Admin Only)
 */

require_once __DIR__ . '/../config/layout.php';

// Enforce login
requireLogin();

// Otorisasi check (Admin only)
if (($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['flash_error'] = 'Anda tidak memiliki hak akses untuk membersihkan log.';
    header('Location: index.php');
    exit;
}

try {
    $db = Database::getConnection();
    
    // Clear all logs
    $db->exec("DELETE FROM activity_logs");
    
    $_SESSION['flash_success'] = 'Log berhasil dibersihkan.';
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Gagal membersihkan log: ' . $e->getMessage();
}

header('Location: index.php');
exit;
