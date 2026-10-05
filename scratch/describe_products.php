<?php
require_once __DIR__ . '/../includes/db.php';
$db = Database::getInstance();
echo "TABLE products:\n";
foreach ($db->query("DESCRIBE `products`")->fetchAll() as $c) {
    echo "  " . $c['Field'] . " (" . $c['Type'] . ")\n";
}
