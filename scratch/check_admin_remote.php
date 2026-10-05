<?php
$baseUrl = 'https://lime-dolphin-989885.hostingersite.com';

$adminPages = [
    '/admin.php',
    '/admin_banners.php',
    '/admin_faqs.php',
    '/admin_testimonials.php',
    '/admin_blogs.php',
    '/admin_b2b.php',
    '/admin_contact.php',
    '/admin_orders.php',
    '/admin/banners',
    '/admin/faqs',
    '/admin/testimonials',
    '/admin/blogs',
    '/admin/b2b'
];

foreach ($adminPages as $p) {
    $ch = curl_init($baseUrl . $p);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $is500 = ($code >= 500);
    $hasFatal = stripos($res, 'Fatal error') !== false || stripos($res, 'Parse error') !== false;
    echo sprintf("[%s] %-30s => HTTP %d\n", ($is500 || $hasFatal) ? 'FAIL' : 'OK  ', $p, $code);
}
