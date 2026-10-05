<?php
require_once __DIR__ . '/../includes/db.php';
$db = Database::getInstance();
foreach (['blogs', 'testimonials', 'banners', 'faqs', 'contact_submissions'] as $t) {
    echo "TABLE $t:\n";
    try {
        $cols = $db->query("DESCRIBE `$t`")->fetchAll();
        foreach ($cols as $c) {
            echo "  " . $c['Field'] . " (" . $c['Type'] . ")\n";
        }
    } catch (\Throwable $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
