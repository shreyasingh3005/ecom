<?php
$url = 'https://lime-dolphin-989885.hostingersite.com/seed_blogs.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "seed_blogs.php HTTP Code: $code\n";
echo "Output: " . substr(strip_tags($res), 0, 500) . "\n";
