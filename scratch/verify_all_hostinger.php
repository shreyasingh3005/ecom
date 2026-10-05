<?php
$baseUrl = 'https://lime-dolphin-989885.hostingersite.com';

$pages = [
    'Home' => '/',
    'Home direct' => '/cbd.php',
    'Catalog' => '/cbd-products.php',
    'Products alias' => '/products',
    'Product 1' => '/product_details.php?id=1',
    'Product 2' => '/product_details.php?id=2',
    'B2B Wholesale' => '/b2b.php',
    'Blog Index' => '/blog.php',
    'Blog Post' => '/blog_post.php?slug=ayurvedic-vijaya-sleep-architecture',
    'Cart' => '/cart.php',
    'Checkout' => '/checkout.php',
    'About' => '/cbd-about.php',
    'About alias' => '/about',
    'Contact' => '/cbd-contact.php',
    'Contact alias' => '/contact',
    'FAQ' => '/faq.php',
    'Testimonials' => '/testimonials.php',
    'Orders' => '/orders.php',
    'Addresses' => '/addresses.php',
    'Profile' => '/profile.php',
    'Login' => '/login.php',
    'Privacy' => '/privacy-policy.php',
    'Terms' => '/terms.php',
    'Refund Policy' => '/refund-policy.php',
    'Shipping Policy' => '/shipping-policy.php',
    'Admin Login' => '/admin.php',
    'Admin B2B' => '/admin_b2b.php',
    'Admin Orders' => '/admin_orders.php',
    'Admin Testimonials' => '/admin_testimonials.php',
    'Admin FAQs' => '/admin_faqs.php',
    'Admin Blogs' => '/admin_blogs.php',
    'Admin Banners' => '/admin_banners.php'
];

echo "=== TESTING ALL LIVE PAGES ON HOSTINGER ===\n\n";

$failedCount = 0;
foreach ($pages as $label => $uri) {
    $ch = curl_init($baseUrl . $uri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    $is500 = ($code >= 500);
    $hasFatal = (stripos($body, 'Fatal error') !== false) || (stripos($body, 'Parse error') !== false);

    if ($is500 || $hasFatal) {
        $failedCount++;
        echo "[FAIL] $label ($uri) => HTTP $code\n";
    } else {
        echo "[OK  ] $label ($uri) => HTTP $code\n";
    }
}

echo "\n============================================\n";
echo "Total Tested: " . count($pages) . " | Failed: $failedCount\n";
