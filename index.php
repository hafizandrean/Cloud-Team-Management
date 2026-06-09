<?php
/**
 * Cloud Team Management - Root Entry Point
 * Automatically redirects users based on their authentication status.
 */

require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;