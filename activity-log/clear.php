<?php
/**
 * Cloud Team Management - Clear Activity Logs (Admin Only)
 */

require_once __DIR__ . '/../config/layout.php';

// Protect page (Admin only)
requireRole('admin');

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
