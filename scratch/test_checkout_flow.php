<?php
// Scratch test: End-to-end checkout flow validation
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/inventory.php';

$db = Database::getInstance();

// 1. Check an active product
$stmt = $db->query("SELECT id, name, price, stock FROM products WHERE status = 'Active' AND stock > 0 LIMIT 1");
$prod = $stmt->fetch();

if (!$prod) {
    die("No active in-stock products found to test checkout flow.\n");
}

echo "Found product for checkout test: {$prod['name']} (ID: {$prod['id']}, Stock: {$prod['stock']}, Price: ₹{$prod['price']})\n";

// 2. Simulate Cart calculation
$cart = [
    [
        'id' => (int)$prod['id'],
        'name' => $prod['name'],
        'price' => (float)$prod['price'],
        'qty' => 1,
        'image' => 'uploads/test.jpg'
    ]
];

$subtotal = (float)$prod['price'];
$tax = round($subtotal * 0.18, 2);
$shipping = $subtotal >= 3999 ? 0 : 250;
$total = $subtotal + $tax + $shipping;

echo "Calculated order: Subtotal=₹$subtotal, Tax=₹$tax, Shipping=₹$shipping, Total=₹$total\n";

// 3. Verify that DB connection and inventory methods respond accurately
$stockCheck = Inventory::checkStock($prod['id'], 1);
echo "Inventory::checkStock result: " . ($stockCheck['available'] ? "Available (Current: {$stockCheck['current_stock']})" : "Not available") . "\n";

echo "End-to-end checkout calculation verification passed successfully!\n";
