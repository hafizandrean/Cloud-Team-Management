<?php
/**
 * Cloud Team Management - Authentication Helper Functions
 */

/**
 * Starts the PHP session safely if it hasn't been started already.
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Checks if the user is currently authenticated.
 * @return bool True if logged in, false otherwise.
 */
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}

/**
 * Enforces authentication. Redirects to login.php if the user is not authenticated.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Enforces role-based authorization.
 * @param array|string $allowedRoles Single role string or array of allowed roles.
 */
function requireRole($allowedRoles) {
    requireLogin();
    
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, $allowedRoles)) {
        header("Location: dashboard.php?error=unauthorized");
        exit;
    }
}

/**
 * Retrieves information about the currently logged-in user.
 * @return array|null User details array or null if not logged in.
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['role'],
        'email'    => $_SESSION['email'] ?? '',
        'nama'     => $_SESSION['nama'] ?? $_SESSION['username'],
        'foto'     => $_SESSION['foto'] ?? null
    ];
}
