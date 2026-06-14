<?php
/**
 * Cloud Team Management - Logout Process
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/activity_helper.php';

startSession();

// Log the logout action before destroying session
if (isset($_SESSION['user_id'])) {
    try {
        $db = Database::getConnection();
        writeLog($db, $_SESSION['user_id'], 'LOGOUT', 'User keluar dari sistem');
    } catch (Exception $e) {
        // Silently fail if DB has issues during logout
    }
}

// Clear all session data
$_SESSION = [];

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect back to login page
header("Location: login.php");
exit;
