<?php
$baseUrl = 'https://lime-dolphin-989885.hostingersite.com';

$cleanUrls = [
    '/cbd-products',
    '/products',
    '/faq',
    '/testimonials',
    '/blog',
    '/cart',
    '/checkout',
    '/about',
    '/contact',
    '/b2b',
    '/orders',
    '/addresses',
    '/profile',
    '/category?cat=oils-tinctures',
    '/category.php?cat=oils-tinctures'
];

foreach ($cleanUrls as $path) {
    $url = $baseUrl . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't auto follow so we see redirect or 500
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    echo sprintf("%-35s => HTTP %d %s\n", $path, $httpCode, $redirectUrl ? "Redirect: $redirectUrl" : "");
}
