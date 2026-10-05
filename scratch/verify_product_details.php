<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/ecom/product_details.php';
$_SERVER['REQUEST_URI'] = '/ecom/product_details.php?id=1';
$_SERVER['HTTPS'] = 'off';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET['id'] = '1';

ob_start();
require __DIR__ . '/../product_details.php';
$output = ob_get_clean();

echo "Product 1 Page Render Length: " . strlen($output) . " bytes\n";
echo "Has Primary Image: " . (strpos($output, 'p1_vijaya_primary.jpg') !== false ? 'YES' : 'NO') . "\n";
echo "Has Secondary Image: " . (strpos($output, 'p1_vijaya_alt.jpg') !== false ? 'YES' : 'NO') . "\n";
echo "Has Formatted Price ₹2,999.00: " . (strpos($output, '2,999.00') !== false ? 'YES' : 'NO') . "\n";
echo "Has Formatted MRP ₹3,999.00: " . (strpos($output, '3,999.00') !== false ? 'YES' : 'NO') . "\n";
echo "Has 25% OFF Badge: " . (strpos($output, '25% OFF') !== false ? 'YES' : 'NO') . "\n";
