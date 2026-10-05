<?php
// Scratch migration to add backward-compatible column aliases
require_once __DIR__ . '/../includes/db.php';
$db = Database::getInstance();

function safeAddColumn($db, $table, $column, $def) {
    try {
        $cols = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $def");
            echo "Added $column to $table\n";
        } else {
            echo "Column $column already exists in $table\n";
        }
    } catch (\Throwable $e) {
        echo "Error adding $column to $table: " . $e->getMessage() . "\n";
    }
}

// 1. blogs
safeAddColumn($db, 'blogs', 'views', 'INT DEFAULT 0');
$db->exec("UPDATE blogs SET views = view_count WHERE views = 0 AND view_count > 0");

// 2. testimonials
safeAddColumn($db, 'testimonials', 'customer_role', 'VARCHAR(100) DEFAULT "Verified Buyer"');
safeAddColumn($db, 'testimonials', 'review_text', 'TEXT NULL');
safeAddColumn($db, 'testimonials', 'product_id', 'INT NULL');
safeAddColumn($db, 'testimonials', 'avatar_url', 'VARCHAR(255) NULL');
safeAddColumn($db, 'testimonials', 'verified_purchase', 'TINYINT(1) DEFAULT 1');
$db->exec("UPDATE testimonials SET review_text = review WHERE review_text IS NULL OR review_text = ''");

// 3. banners
safeAddColumn($db, 'banners', 'position', 'VARCHAR(50) DEFAULT "hero"');
safeAddColumn($db, 'banners', 'button_text', 'VARCHAR(100) DEFAULT "Explore Now"');
safeAddColumn($db, 'banners', 'link_url', 'VARCHAR(255) DEFAULT "cbd-products.php"');
$db->exec("UPDATE banners SET button_text = cta_text WHERE button_text IS NULL OR button_text = ''");
$db->exec("UPDATE banners SET link_url = cta_link WHERE link_url IS NULL OR link_url = ''");

echo "All schema adjustments complete!\n";
