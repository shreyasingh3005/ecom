<?php
// Test admin pages directly via CLI with mock admin session
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Set admin session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_user'] = 'admin';

$adminPages = [
    'admin.php',
    'admin_orders.php',
    'inventory.php',
    'admin_b2b.php',
    'admin_blogs.php',
    'admin_faqs.php',
    'admin_testimonials.php',
    'admin_banners.php',
    'admin_contact.php',
    'users.php',
    'analytics.php',
    'settings.php'
];

foreach ($adminPages as $page) {
    ob_start();
    try {
        include __DIR__ . '/../' . $page;
        $html = ob_get_clean();
        echo "$page -> RENDER OK (" . strlen($html) . " bytes)\n";
    } catch (\Throwable $e) {
        ob_end_clean();
        echo "$page -> ERROR: " . $e->getMessage() . "\n";
    }
}
