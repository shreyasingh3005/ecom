<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = getDB();

echo "=== PRODUCTS TABLE SCHEMA ===\n";
$stmt = $pdo->query("DESCRIBE products");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== PRODUCT_IMAGES TABLE SCHEMA ===\n";
$stmt = $pdo->query("DESCRIBE product_images");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== CURRENT PRODUCTS (1-6) ===\n";
$stmt = $pdo->query("SELECT id, name, price, mrp, sale_badge, stock, status, featured FROM products ORDER BY id ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}

echo "\n=== SETTINGS TABLE SCHEMA & KEYS ===\n";
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['setting_key']}: {$row['setting_value']}\n";
}
