<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_name'] = 'Admin';
$_SESSION['admin_email'] = 'admin@gmail.com';
$_SESSION['admin_role'] = 'Superadmin';

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/ecom/settings.php';
$_SERVER['REQUEST_URI'] = '/ecom/settings.php';
$_SERVER['HTTPS'] = 'off';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

ob_start();
require __DIR__ . '/../settings.php';
$output = ob_get_clean();

echo "Settings Page Render Length: " . strlen($output) . " bytes\n";
echo "Has Banners Tab: " . (strpos($output, 'Banners & Storefront') !== false ? 'YES' : 'NO') . "\n";
echo "Has Tab Banners Pane: " . (strpos($output, 'id="tab-banners"') !== false ? 'YES' : 'NO') . "\n";
echo "Has Active Hero Banner: " . (strpos($output, 'hero_banner_main.jpg') !== false ? 'YES' : 'NO') . "\n";
echo "Has File Upload Input: " . (strpos($output, 'name="hero_banner_file"') !== false ? 'YES' : 'NO') . "\n";
echo "Has Multipart Form: " . (strpos($output, 'enctype="multipart/form-data"') !== false ? 'YES' : 'NO') . "\n";
echo "Has Referrer Reward Field: " . (strpos($output, 'name="referrer_reward_percent"') !== false ? 'YES' : 'NO') . "\n";
echo "Has Max Wallet Usage Field: " . (strpos($output, 'name="max_wallet_usage_percent"') !== false ? 'YES' : 'NO') . "\n";
