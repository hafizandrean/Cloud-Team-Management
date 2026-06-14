<?php
/**
 * Cloud Team Management - Logout Process
 */

require_once __DIR__ . '/../config/auth.php';

startSession();

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
