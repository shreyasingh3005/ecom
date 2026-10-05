<?php
// logout.php
// Universal Secure Logout Handler for Admin and Customers

session_start();

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Log out both customer and admin
Auth::logout();

// Clear all session variables
$_SESSION = [];

// Remove session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session entirely
@session_destroy();

// Restart clean session for one-time alert message
session_start();
$_SESSION['logout_notice'] = "You have been successfully logged out.";

// Safe redirection
$redirect = $_GET['redirect'] ?? 'login.php';
if ($redirect === 'cbd' || $redirect === 'home') {
    header("Location: cbd.php");
} elseif ($redirect === 'cart') {
    header("Location: cart.php");
} elseif ($redirect === 'checkout') {
    header("Location: checkout.php");
} else {
    header("Location: login.php");
}
exit;
