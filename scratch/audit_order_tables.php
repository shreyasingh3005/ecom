<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = getDB();

echo "=== TABLES IN ECOM_DB ===\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

foreach (['orders', 'order_items', 'payments', 'settings'] as $table) {
    if (in_array($table, $tables)) {
        echo "\n=== SCHEMA OF $table ===\n";
        $stmt = $pdo->query("DESCRIBE `$table`");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$r['Field']} | {$r['Type']} | Null:{$r['Null']} | Key:{$r['Key']} | Default:{$r['Default']}\n";
        }
    } else {
        echo "\n=== TABLE $table DOES NOT EXIST ===\n";
    }
}
