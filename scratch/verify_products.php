<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/inventory.php';

for ($i = 1; $i <= 6; $i++) {
    $p = Inventory::getProduct($i);
    echo "ID {$p['id']}: {$p['name']}\n";
    echo "  Price: ₹{$p['price']} | MRP: ₹{$p['mrp']} | Badge: {$p['sale_badge']} | Stock: {$p['stock']}\n";
    echo "  Primary Image:   {$p['primary_image']}\n";
    echo "  Secondary Image: {$p['secondary_image']}\n\n";
}
