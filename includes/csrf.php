<?php
// includes/csrf.php
// Cross-Site Request Forgery (CSRF) Protection

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify($terminateOnFailure = true) {
        $valid = verify_csrf();
        if (!$valid && $terminateOnFailure) {
            http_response_code(403);
            die("CSRF verification failed. Please refresh the page and try again.");
        }
        return $valid;
    }
}
