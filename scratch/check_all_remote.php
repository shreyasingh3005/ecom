<?php
$baseUrl = 'https://lime-dolphin-989885.hostingersite.com';

$testUrls = [
    '/' => '/',
    '/index.php' => '/index.php',
    '/cbd.php' => '/cbd.php',
    '/cbd-products.php' => '/cbd-products.php',
    '/products' => '/products',
    '/category.php?cat=oils-tinctures' => '/category.php?cat=oils-tinctures',
    '/product_details.php?id=1' => '/product_details.php?id=1',
    '/b2b.php' => '/b2b.php',
    '/blog.php' => '/blog.php',
    '/blog_post.php?slug=ayurvedic-vijaya-sleep-architecture' => '/blog_post.php?slug=ayurvedic-vijaya-sleep-architecture',
    '/cart.php' => '/cart.php',
    '/checkout.php' => '/checkout.php',
    '/about' => '/about',
    '/about.php' => '/about.php',
    '/contact' => '/contact',
    '/contact.php' => '/contact.php',
    '/admin' => '/admin',
    '/admin.php' => '/admin.php',
    '/admin_b2b.php' => '/admin_b2b.php',
    '/login.php' => '/login.php',
    '/faq.php' => '/faq.php',
    '/testimonials.php' => '/testimonials.php',
    '/orders.php' => '/orders.php',
    '/addresses.php' => '/addresses.php',
    '/profile.php' => '/profile.php',
    '/privacy-policy.php' => '/privacy-policy.php',
    '/terms.php' => '/terms.php'
];

foreach ($testUrls as $label => $path) {
    $url = $baseUrl . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($ch);
    curl_close($ch);

    $is500 = ($httpCode >= 500);
    $hasFatal = stripos($body, 'Fatal error') !== false || stripos($body, 'Parse error') !== false;
    
    echo sprintf("[%s] %-40s => HTTP %d %s\n", 
        ($is500 || $hasFatal) ? 'FAIL' : 'OK  ',
        $label,
        $httpCode,
        ($hasFatal ? '(PHP Fatal Error detected)' : ($is500 ? '(Server Error 500)' : ''))
    );

    if ($is500 || $hasFatal) {
        echo "   Snippet: " . substr(strip_tags($body), 0, 300) . "\n\n";
    }
}
