<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Remove remember me cookie if exists
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Redirect to home page with logout message
header("Location: index.php?logout=success");
exit;
?>