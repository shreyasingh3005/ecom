<?php
try {
    $p = new PDO('mysql:host=localhost;port=3306;dbname=ecom_db', 'root', '');
    $tables = $p->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in ecom_db:\n" . implode(', ', $tables) . "\n\n";

    if (in_array('products', $tables)) {
        $count = $p->query("SELECT count(*) FROM products")->fetchColumn();
        echo "Product count: {$count}\n";
    }
    if (in_array('settings', $tables)) {
        $count = $p->query("SELECT count(*) FROM settings")->fetchColumn();
        echo "Settings count: {$count}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
