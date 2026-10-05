<?php
require_once __DIR__ . '/../includes/db.php';
$db = Database::getInstance();
$stmt = $db->query('SELECT id, name, price, mrp, stock, category_name FROM products');
while($r = $stmt->fetch()) {
    echo "{$r['id']}: {$r['name']} | {$r['category_name']} | ₹{$r['price']}\n";
}
