<?php
try {
    $p = new PDO('mysql:host=localhost;port=3306', 'root', '');
    echo "Connected successfully to MySQL!";
    $stmt = $p->query("SHOW DATABASES");
    echo "\nDatabases:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $db) {
        echo "- " . $db . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
