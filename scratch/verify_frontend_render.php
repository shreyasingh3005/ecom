<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/ecom/cbd.php';
$_SERVER['REQUEST_URI'] = '/ecom/cbd.php';
$_SERVER['HTTPS'] = 'off';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

ob_start();
require __DIR__ . '/../cbd.php';
$output = ob_get_clean();

echo "Render Length: " . strlen($output) . " bytes\n";
echo "Contains Hero Title: " . (strpos($output, 'Ancient Vedic Healing') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Vijaya Extract: " . (strpos($output, 'Premium Vijaya Extract 1500mg') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Deep Sleep: " . (strpos($output, 'Deep Sleep Restorative Drops') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 25% OFF: " . (strpos($output, '25% OFF') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Referral Showcase: " . (strpos($output, 'referral-showcase') !== false ? 'YES' : 'NO') . "\n";
echo "Contains FAQ: " . (strpos($output, 'Frequently Asked Questions') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Primary Image: " . (strpos($output, 'p1_vijaya_primary.jpg') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Secondary Image: " . (strpos($output, 'p1_vijaya_alt.jpg') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Trust Strip: " . (strpos($output, 'AYUSH Certified Formulations') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Category Filters: " . (strpos($output, 'category-filter-bar') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Customer Reviews: " . (strpos($output, 'Dr. Vikram Sharma') !== false ? 'YES' : 'NO') . "\n";
