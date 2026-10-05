<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/ecom/product_details.php';
$_SERVER['HTTPS'] = 'off';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

for ($i = 1; $i <= 6; $i++) {
    $_GET['id'] = (string)$i;
    $_SERVER['REQUEST_URI'] = "/ecom/product_details.php?id=$i";
    ob_start();
    require __DIR__ . '/../product_details.php';
    $out = ob_get_clean();
    echo "Product $i Details Rendered: " . strlen($out) . " bytes\n";
}
