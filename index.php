<?php
/**
 * Cloud Team Management - Root Entry Point
 * Automatically redirects users based on their authentication status.
 */

require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard/index.php");
} else {
    header("Location: auth/login.php");
}
exit;