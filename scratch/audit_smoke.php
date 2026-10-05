<?php
// Read-only HTTP smoke checks. Never creates orders or sends notifications.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$base = 'http://localhost/ecom/';
$routes = [
    'index.php' => 200, 'about.php' => 200, 'contact.php' => 200,
    'cbd.php' => 200, 'cbd-products.php' => 200,
    'category.php?cat=oils-tinctures' => 200, 'product_details.php?id=1' => 200,
    'cart.php' => 200, 'checkout.php' => 200, 'profile.php' => 200,
    'addresses.php' => 302, 'orders.php' => 200, 'order-tracking.php' => 200,
    'login.php' => 200, 'b2b.php' => 200, 'blog.php' => 200,
    'blog_post.php?id=1' => 200, 'faq.php' => 200, 'testimonials.php' => 200,
    'search.php?q=example' => 200, 'cbd-about.php' => 200, 'cbd-contact.php' => 200,
    'privacy-policy.php' => 200, 'terms.php' => 200,
    'shipping-policy.php' => 200, 'refund-policy.php' => 200, '404.php' => 404,
    'api/payment_webhook.php' => 405, 'api/cashfree_webhook.php' => 405,
    'api/confirm_manual_upi.php' => 405, 'api/payment_status.php' => 400,
    'api/simulate_upi_payment.php' => 400, 'api/cashfree_return.php' => 302,
    'admin_config.json' => 403, 'users.json' => 403, 'orders.json' => 403,
    'inventory.json' => 403, 'settings.json' => 403, '.env' => 403,
    'ecom_db.sql' => 403, 'test_e2e.php' => 403, 'test_payment_e2e.php' => 403,
    'migrate.php' => 403, 'scratch/test_admin_render.php' => 403,
    'includes/db.php' => 403,
];
foreach (['admin', 'admin_orders', 'inventory', 'admin_b2b', 'admin_blogs', 'admin_faqs',
    'admin_testimonials', 'admin_banners', 'admin_contact', 'users', 'analytics', 'settings'] as $page) {
    $routes[$page . '.php'] = 302;
}
$failed = 0;
foreach ($routes as $path => $expected) {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $phpError = preg_match('/(?:Fatal error:|Parse error:|<b>(?:Warning|Notice|Deprecated)<\/b>:)/i', (string)$body);
    $pass = !$error && !$phpError && $status === $expected;
    if (!$pass) $failed++;
    printf("%s %s: HTTP %d (expected %d)%s\n", $pass ? 'PASS' : 'FAIL', $path,
        $status, $expected, $phpError ? ' PHP diagnostic detected' : '');
}
printf("%d/%d checks passed\n", count($routes) - $failed, count($routes));
exit($failed ? 1 : 0);
