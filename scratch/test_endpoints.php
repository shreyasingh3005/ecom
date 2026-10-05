<?php
$urls = [
    'http://localhost/ecom/cbd.php',
    'http://localhost/ecom/cbd-products.php',
    'http://localhost/ecom/cart.php',
    'http://localhost/ecom/checkout.php',
    'http://localhost/ecom/profile.php',
    'http://localhost/ecom/login.php',
    'http://localhost/ecom/b2b.php',
    'http://localhost/ecom/faq.php',
    'http://localhost/ecom/testimonials.php',
    'http://localhost/ecom/search.php?q=vijaya',
    'http://localhost/ecom/blog.php',
    'http://localhost/ecom/blog_post.php?id=1',
    'http://localhost/ecom/cbd-about.php',
    'http://localhost/ecom/cbd-contact.php',
    'http://localhost/ecom/privacy-policy.php',
    'http://localhost/ecom/terms.php',
    'http://localhost/ecom/shipping-policy.php',
    'http://localhost/ecom/refund-policy.php',
    'http://localhost/ecom/404.php',
    'http://localhost/ecom/sitemap.xml',
    'http://localhost/ecom/robots.txt'
];

foreach ($urls as $url) {
    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
    $resp = @file_get_contents($url, false, $context);
    $status = $http_response_header[0] ?? 'NO_RESPONSE';
    echo basename($url) . " -> " . $status . " (" . strlen($resp) . " bytes)\n";
}
