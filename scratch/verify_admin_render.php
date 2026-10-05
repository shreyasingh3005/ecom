<?php
// scratch/verify_admin_render.php
require_once __DIR__ . '/../includes/db.php';
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_user'] = ['id' => 1, 'name' => 'Web Admin', 'email' => 'admin@gmail.com'];
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
include __DIR__ . '/../settings.php';
$htmlSettings = ob_get_clean();
echo "settings.php rendered: " . strlen($htmlSettings) . " bytes\n";
if (strpos($htmlSettings, 'Merchant UPI ID / VPA') !== false && strpos($htmlSettings, 'Admin WhatsApp Number') !== false) {
    echo "✓ UPI & WhatsApp settings present in settings.php\n";
} else {
    echo "❌ Missing UPI settings in settings.php\n";
}

ob_start();
include __DIR__ . '/../admin_orders.php';
$htmlOrders = ob_get_clean();
echo "admin_orders.php rendered: " . strlen($htmlOrders) . " bytes\n";
if (strpos($htmlOrders, '<th>Payment</th>') !== false) {
    echo "✓ Payment column present in admin_orders.php\n";
} else {
    echo "❌ Payment column missing in admin_orders.php\n";
}
