<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';

$pdo = getDB();

echo "=== RUNNING PAYMENT & COD SYSTEM MIGRATIONS ===\n";

// 1. Create payments table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `payments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NULL,
        `order_number` VARCHAR(100) NOT NULL,
        `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
        `provider_payment_id` VARCHAR(100) NULL,
        `provider` VARCHAR(50) NOT NULL DEFAULT 'native_upi',
        `amount` DECIMAL(10,2) NOT NULL,
        `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
        `upi_id` VARCHAR(100) NULL,
        `payment_method` VARCHAR(50) NOT NULL DEFAULT 'UPI',
        `status` ENUM('PENDING','PROCESSING','SUCCESS','FAILED','CANCELLED','EXPIRED','REFUNDED') NOT NULL DEFAULT 'PENDING',
        `webhook_status` VARCHAR(50) NULL,
        `webhook_payload` LONGTEXT NULL,
        `failure_reason` TEXT NULL,
        `checkout_payload` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `paid_at` DATETIME NULL,
        INDEX (`order_id`),
        INDEX (`order_number`),
        INDEX (`transaction_id`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ Payments table created or verified.\n";

// 2. Add transaction_id column to orders if missing
$cols = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'transaction_id'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `transaction_id` VARCHAR(100) NULL AFTER `order_number`, ADD INDEX (`transaction_id`);");
    echo "✓ Added transaction_id column to orders table.\n";
} else {
    echo "✓ transaction_id column already exists in orders table.\n";
}

// 3. Update order_status enum to include 'Pending' and 'Confirmed'
$pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `order_status` ENUM('Pending','Confirmed','Processing','Shipped','Delivered','Cancelled','Refunded') DEFAULT 'Pending';");
echo "✓ orders.order_status enum updated with Pending & Confirmed.\n";

// 4. Set default settings for UPI and WhatsApp if not set
$defaultSettings = [
    'upi_id' => 'kamshemp@upi',
    'upi_merchant_name' => 'KAMS HEMP India',
    'admin_whatsapp_number' => '+919876543210',
    'payment_gateway_provider' => 'native_upi',
    'payment_gateway_key' => 'rzp_live_kamshemp',
    'payment_gateway_secret' => 'rzp_sec_kamshemp_live',
    'payment_webhook_secret' => 'whsec_kams_upi_2026'
];

foreach ($defaultSettings as $k => $v) {
    if (Settings::get($k) === null) {
        Settings::set($k, $v);
        echo "✓ Configured setting: $k = $v\n";
    } else {
        echo "✓ Setting already exists: $k\n";
    }
}

echo "=== MIGRATION FINISHED SUCCESSFULLY ===\n";
