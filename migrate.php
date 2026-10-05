<?php
// migrate.php
// Full Database Architecture Creation & JSON Data Migration Script

require_once __DIR__ . '/includes/env.php';

echo "=== Starting KAMS HEMP Database Migration ===\n";

$host = env('DB_HOST', 'localhost');
$port = env('DB_PORT', '3306');
$dbName = env('DB_DATABASE', 'ecom_db');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');

// 1. Connect to MySQL server and ensure Database exists
try {
    $serverPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[OK] Database `{$dbName}` confirmed/created.\n";
} catch (Exception $e) {
    die("[FATAL] Could not connect to MySQL server: " . $e->getMessage() . "\n");
}

// 2. Connect to the specific database
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

// 3. Create Tables
$tables = [

"admins" => "CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) DEFAULT 'super_admin',
    `last_login` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"categories" => "CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"products" => "CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `legacy_id` BIGINT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `sku` VARCHAR(100) NOT NULL,
    `category_id` INT NULL,
    `category_name` VARCHAR(100) NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `mrp` DECIMAL(10,2) NULL,
    `sale_badge` VARCHAR(50) NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `status` ENUM('Active', 'Low Stock', 'Out of Stock', 'Inactive') DEFAULT 'Active',
    `description` TEXT NULL,
    `potency` VARCHAR(50) DEFAULT 'Regular',
    `extract_type` VARCHAR(50) DEFAULT 'Full Spectrum',
    `featured` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`category_id`),
    INDEX (`status`),
    INDEX (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"product_images" => "CREATE TABLE IF NOT EXISTS `product_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `image_url` VARCHAR(255) NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `is_secondary` TINYINT(1) DEFAULT 0,
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"inventory_transactions" => "CREATE TABLE IF NOT EXISTS `inventory_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `change_qty` INT NOT NULL,
    `stock_before` INT NOT NULL,
    `stock_after` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `reference_id` VARCHAR(100) NULL,
    `note` TEXT NULL,
    `created_by` VARCHAR(100) DEFAULT 'System',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"users" => "CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `referral_code` VARCHAR(50) NOT NULL UNIQUE,
    `referred_by_user_id` INT NULL,
    `has_used_referral` TINYINT(1) DEFAULT 0,
    `wallet_balance` DECIMAL(10,2) DEFAULT 0.00,
    `is_verified` TINYINT(1) DEFAULT 1,
    `verification_otp` VARCHAR(10) NULL,
    `status` ENUM('Active', 'Suspended', 'Inactive') DEFAULT 'Active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`referral_code`),
    INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"user_addresses" => "CREATE TABLE IF NOT EXISTS `user_addresses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `legacy_id` VARCHAR(100) NULL,
    `title` VARCHAR(50) DEFAULT 'Home',
    `name` VARCHAR(100) NULL,
    `phone` VARCHAR(20) NULL,
    `street` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `zip` VARCHAR(20) NOT NULL,
    `country` VARCHAR(50) DEFAULT 'India',
    `is_default` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"orders" => "CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(100) NOT NULL UNIQUE,
    `user_id` INT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `billing_address` TEXT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `referral_code` VARCHAR(50) NULL,
    `wallet_deduction` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `shipping_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `order_status` ENUM('Processing', 'Shipped', 'Delivered', 'Cancelled', 'Refunded') DEFAULT 'Processing',
    `payment_status` ENUM('Pending', 'Paid', 'Failed', 'Refunded') DEFAULT 'Paid',
    `payment_method` VARCHAR(50) DEFAULT 'COD',
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`order_status`),
    INDEX (`payment_status`),
    INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"order_items" => "CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `image_url` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"referrals" => "CREATE TABLE IF NOT EXISTS `referrals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `referrer_id` INT NOT NULL,
    `referee_id` INT NULL,
    `referral_code` VARCHAR(50) NOT NULL,
    `referee_discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    `referrer_reward_percent` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    `order_id` INT NULL,
    `reward_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Completed',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"wallet_transactions" => "CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` ENUM('Credit', 'Debit') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `balance_after` DECIMAL(10,2) NOT NULL,
    `reference_id` VARCHAR(100) NOT NULL,
    `source` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('Completed', 'Pending', 'Failed') DEFAULT 'Completed',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"coupons" => "CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `discount_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL,
    `min_order_amount` DECIMAL(10,2) DEFAULT 0.00,
    `max_discount_amount` DECIMAL(10,2) NULL,
    `usage_limit` INT DEFAULT 100,
    `used_count` INT DEFAULT 0,
    `expiry_date` DATE NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"settings" => "CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"traffic" => "CREATE TABLE IF NOT EXISTS `traffic` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `page_url` VARCHAR(255) NOT NULL,
    `page_title` VARCHAR(255) NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `device_category` ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
    `source` VARCHAR(100) DEFAULT 'Direct',
    `referrer_url` VARCHAR(255) NULL,
    `user_id` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (`created_at`),
    INDEX (`page_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"product_analytics" => "CREATE TABLE IF NOT EXISTS `product_analytics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `action_type` ENUM('view', 'click', 'add_to_cart', 'order') NOT NULL,
    `user_id` INT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (`product_id`),
    INDEX (`action_type`),
    INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"b2b_inquiries" => "CREATE TABLE IF NOT EXISTS `b2b_inquiries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `phone` VARCHAR(25) NOT NULL,
    `gst_number` VARCHAR(50) NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `business_type` VARCHAR(100) NOT NULL,
    `estimated_monthly_volume` VARCHAR(100) NULL,
    `attachment_path` VARCHAR(255) NULL,
    `message` TEXT NULL,
    `status` ENUM('New', 'Contacted', 'In Discussion', 'Approved', 'Rejected') DEFAULT 'New',
    `admin_notes` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`status`),
    INDEX (`created_at`),
    INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"email_logs" => "CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient` VARCHAR(191) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `status` ENUM('sent', 'failed') DEFAULT 'sent',
    `error_message` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"audit_logs" => "CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` VARCHAR(50) NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"testimonials" => "CREATE TABLE IF NOT EXISTS `testimonials` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_role` VARCHAR(100) DEFAULT 'Verified Buyer',
    `rating` INT NOT NULL DEFAULT 5,
    `review` TEXT NOT NULL,
    `review_text` TEXT NULL,
    `product_id` INT NULL,
    `product_name` VARCHAR(255) NULL,
    `location` VARCHAR(100) NULL,
    `avatar_url` VARCHAR(255) NULL,
    `is_verified` TINYINT(1) DEFAULT 1,
    `verified_purchase` TINYINT(1) DEFAULT 1,
    `status` ENUM('Published', 'Pending', 'Archived', 'Approved') DEFAULT 'Published',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"faqs" => "CREATE TABLE IF NOT EXISTS `faqs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(50) NOT NULL DEFAULT 'General',
    `question` VARCHAR(255) NOT NULL,
    `answer` TEXT NOT NULL,
    `sort_order` INT DEFAULT 0,
    `status` ENUM('Published', 'Draft') DEFAULT 'Published',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (`category`),
    INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"blogs" => "CREATE TABLE IF NOT EXISTS `blogs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `excerpt` TEXT NULL,
    `content` LONGTEXT NOT NULL,
    `featured_image` VARCHAR(255) NULL,
    `category` VARCHAR(100) DEFAULT 'Ayurvedic Science',
    `tags` VARCHAR(255) NULL,
    `author` VARCHAR(100) DEFAULT 'Dr. KAMS Ayurvedic Board',
    `seo_title` VARCHAR(255) NULL,
    `seo_description` TEXT NULL,
    `status` ENUM('Published', 'Draft') DEFAULT 'Published',
    `view_count` INT DEFAULT 0,
    `views` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`slug`),
    INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"banners" => "CREATE TABLE IF NOT EXISTS `banners` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `subtitle` TEXT NULL,
    `badge` VARCHAR(100) NULL,
    `cta_text` VARCHAR(100) DEFAULT 'Shop Now',
    `cta_link` VARCHAR(255) DEFAULT 'cbd-products.php',
    `button_text` VARCHAR(100) DEFAULT 'Shop Now',
    `link_url` VARCHAR(255) DEFAULT 'cbd-products.php',
    `position` VARCHAR(50) DEFAULT 'hero',
    `image_url` VARCHAR(255) NULL,
    `sort_order` INT DEFAULT 0,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"contact_submissions" => "CREATE TABLE IF NOT EXISTS `contact_submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `phone` VARCHAR(25) NULL,
    `subject` VARCHAR(150) NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('New', 'Read', 'Replied') DEFAULT 'New',
    `admin_notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (`status`),
    INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

];

foreach ($tables as $name => $sql) {
    $pdo->exec($sql);
    echo "[OK] Table `{$name}` created or exists.\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// 4. Seed Admin user (User requested: admin@gmail.com / admin@123)
$adminEmail = 'admin@gmail.com';
$adminPass = 'admin@123';
$adminHash = password_hash($adminPass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT id FROM `admins` WHERE `email` = ?");
$stmt->execute([$adminEmail]);
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO `admins` (`first_name`, `last_name`, `email`, `password_hash`, `role`) VALUES (?, ?, ?, ?, 'super_admin')");
    $stmt->execute(['Web', 'Administrator', $adminEmail, $adminHash]);
    echo "[OK] Admin user created: {$adminEmail}\n";
} else {
    $stmt = $pdo->prepare("UPDATE `admins` SET `password_hash` = ? WHERE `email` = ?");
    $stmt->execute([$adminHash, $adminEmail]);
    echo "[OK] Admin user password synchronized: {$adminEmail}\n";
}

// 5. Seed / Migrate Settings from settings.json
$defaultSettings = [
    'store_name' => 'CBD Store',
    'support_email' => 'support@kamshemp.com',
    'support_phone' => '+91 98765 43210',
    'currency' => 'INR',
    'tax_rate' => '18',
    'referral_discount_percent' => '10',
    'referrer_reward_percent' => '10',
    'max_wallet_usage_percent' => '50',
    'free_shipping_threshold' => '2000',
    'standard_delivery_fee' => '150',
    'express_delivery_fee' => '300',
    'meta_title' => 'KAMS HEMP | Premium Vedic Cannabis Extracts',
    'meta_description' => 'Discover premium AYUSH-certified hemp extracts and CBD products for holistic wellness.',
    'ga_tracking_id' => 'G-XXXXXXXXXX',
    'enable_razorpay' => '1',
    'razorpay_key' => '',
    'enable_stripe' => '0',
    'stripe_key' => '',
    'maintenance_mode' => '0',
    'require_email_verification' => '1',
    'two_factor_auth' => '0'
];

if (file_exists(__DIR__ . '/settings.json')) {
    $jsonSettings = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);
    if (is_array($jsonSettings)) {
        foreach ($jsonSettings as $k => $v) {
            $defaultSettings[$k] = is_bool($v) ? ($v ? '1' : '0') : (string)$v;
        }
    }
}

$settingStmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)");
foreach ($defaultSettings as $k => $v) {
    $settingStmt->execute([$k, $v]);
}
echo "[OK] Platform settings imported into `settings` table.\n";

// 6. Seed Categories
$categories = [
    'Oils & Tinctures' => 'oils-tinctures',
    'Edibles & Wellness' => 'edibles-wellness',
    'Topicals' => 'topicals',
    'Pet Care' => 'pet-care'
];
$catStmt = $pdo->prepare("INSERT IGNORE INTO `categories` (`name`, `slug`) VALUES (?, ?)");
foreach ($categories as $cName => $cSlug) {
    $catStmt->execute([$cName, $cSlug]);
}

// 7. Migrate Products & Images from inventory.json
if (file_exists(__DIR__ . '/inventory.json')) {
    $inventory = json_decode(file_get_contents(__DIR__ . '/inventory.json'), true);
    if (is_array($inventory)) {
        $prodStmt = $pdo->prepare("SELECT id FROM `products` WHERE `legacy_id` = ? OR `name` = ?");
        $insertProd = $pdo->prepare("INSERT INTO `products` 
            (`legacy_id`, `name`, `slug`, `sku`, `category_name`, `price`, `mrp`, `sale_badge`, `stock`, `status`, `description`, `potency`, `extract_type`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $imgStmt = $pdo->prepare("INSERT INTO `product_images` (`product_id`, `image_url`, `is_primary`, `is_secondary`, `sort_order`) VALUES (?, ?, ?, ?, ?)");

        foreach ($inventory as $item) {
            $legId = $item['id'] ?? null;
            $name = trim($item['name'] ?? '');
            if (empty($name)) continue;

            $prodStmt->execute([$legId, $name]);
            $existing = $prodStmt->fetch();

            $priceVal = (float) preg_replace('/[^0-9.]/', '', $item['price'] ?? 0);
            $mrpVal = isset($item['mrp']) && !empty($item['mrp']) ? (float) preg_replace('/[^0-9.]/', '', $item['mrp']) : null;
            $stock = (int)($item['stock'] ?? 0);
            $status = $item['status'] ?? ($stock > 20 ? 'Active' : ($stock > 0 ? 'Low Stock' : 'Out of Stock'));
            $badge = $item['sale_badge'] ?? '';
            $desc = $item['description'] ?? $name;
            $sku = $item['sku'] ?? ('KAMS-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4)) . '-' . rand(100, 999));
            $cat = $item['category'] ?? 'Oils & Tinctures';
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $potency = 'Regular';
            if ($priceVal > 2500) $potency = 'Strong';
            elseif ($priceVal < 1400) $potency = 'Light';

            $extract = 'Full Spectrum';
            $nl = strtolower($name);
            if (strpos($nl, 'isolate') !== false) $extract = 'Pure Isolate';
            elseif (strpos($nl, 'broad') !== false) $extract = 'Broad Spectrum';

            if (!$existing) {
                $insertProd->execute([
                    $legId, $name, $slug, $sku, $cat, $priceVal, $mrpVal, $badge, $stock, $status, $desc, $potency, $extract
                ]);
                $productId = $pdo->lastInsertId();

                // Save Images
                $images = !empty($item['images']) ? $item['images'] : (!empty($item['img']) ? [$item['img']] : []);
                $order = 0;
                foreach ($images as $imgUrl) {
                    $isPri = ($order === 0) ? 1 : 0;
                    $isSec = ($order === 1) ? 1 : 0;
                    $imgStmt->execute([$productId, $imgUrl, $isPri, $isSec, $order]);
                    $order++;
                }

                // Initial inventory log
                $pdo->prepare("INSERT INTO `inventory_transactions` (`product_id`, `change_qty`, `stock_before`, `stock_after`, `type`, `note`) VALUES (?, ?, 0, ?, 'initial_import', 'Migrated from inventory.json')")
                    ->execute([$productId, $stock, $stock]);
            }
        }
        echo "[OK] Products & Images migrated from inventory.json.\n";
    }
}

// 8. Migrate Users & Addresses from users.json
if (file_exists(__DIR__ . '/users.json')) {
    $usersData = json_decode(file_get_contents(__DIR__ . '/users.json'), true);
    if (is_array($usersData)) {
        $findUser = $pdo->prepare("SELECT id FROM `users` WHERE `email` = ?");
        $insertUser = $pdo->prepare("INSERT INTO `users` (`first_name`, `last_name`, `email`, `phone`, `password_hash`, `referral_code`, `wallet_balance`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertAddr = $pdo->prepare("INSERT INTO `user_addresses` (`user_id`, `legacy_id`, `title`, `name`, `phone`, `street`, `city`, `state`, `zip`, `country`, `is_default`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($usersData as $u) {
            // Check for corrupted nested array
            if (isset($u[0]) && is_array($u[0])) {
                $u = $u[0];
            }
            $email = trim($u['email'] ?? '');
            if (empty($email)) continue;

            $findUser->execute([$email]);
            $uRow = $findUser->fetch();

            if (!$uRow) {
                $fname = $u['first_name'] ?? ($u['name'] ?? 'Customer');
                $lname = $u['last_name'] ?? '';
                $phone = $u['phone'] ?? '9990051200';
                $pass = $u['password'] ?? password_hash('Pass@123', PASSWORD_DEFAULT);
                if (strpos($pass, '$2y$') !== 0) {
                    $pass = password_hash($pass, PASSWORD_DEFAULT);
                }
                $refCode = $u['referral_code'] ?? (strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $fname), 0, 4)) . rand(1000, 9999));
                $wallet = (float)($u['wallet_balance'] ?? 0.00);

                $insertUser->execute([$fname, $lname, $email, $phone, $pass, $refCode, $wallet]);
                $newUserId = $pdo->lastInsertId();

                // Addresses
                if (!empty($u['addresses']) && is_array($u['addresses'])) {
                    foreach ($u['addresses'] as $addr) {
                        if (is_string($addr)) {
                            $insertAddr->execute([$newUserId, null, 'Home', "$fname $lname", $phone, $addr, 'New Delhi', 'Delhi', '110001', 'India', 1]);
                        } elseif (is_array($addr)) {
                            $insertAddr->execute([
                                $newUserId,
                                $addr['id'] ?? null,
                                $addr['title'] ?? 'Home',
                                $addr['name'] ?? "$fname $lname",
                                $addr['phone'] ?? $phone,
                                $addr['street'] ?? 'Address',
                                $addr['city'] ?? 'City',
                                $addr['state'] ?? 'State',
                                $addr['zip'] ?? '000000',
                                $addr['country'] ?? 'India',
                                !empty($addr['is_default']) ? 1 : 0
                            ]);
                        }
                    }
                }
            }
        }
        echo "[OK] Users & Addresses migrated from users.json.\n";
    }
}

// 9. Migrate Orders from orders.json
if (file_exists(__DIR__ . '/orders.json')) {
    $ordersData = json_decode(file_get_contents(__DIR__ . '/orders.json'), true);
    if (is_array($ordersData)) {
        $findOrder = $pdo->prepare("SELECT id FROM `orders` WHERE `order_number` = ?");
        $insertOrder = $pdo->prepare("INSERT INTO `orders` 
            (`order_number`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `shipping_address`, `subtotal`, `discount_amount`, `referral_code`, `wallet_deduction`, `shipping_fee`, `tax_amount`, `total_amount`, `order_status`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insertItem = $pdo->prepare("INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`, `image_url`) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($ordersData as $ord) {
            $rawId = $ord['id'] ?? ($ord['order_id'] ?? ('ORD-' . rand(100000, 999999)));
            $orderNum = ltrim($rawId, '#');

            $findOrder->execute([$orderNum]);
            if ($findOrder->fetch()) continue;

            $cust = $ord['customer'] ?? trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? ''));
            $parts = explode(' ', $cust, 2);
            $fname = $ord['first_name'] ?? ($parts[0] ?? 'Customer');
            $lname = $ord['last_name'] ?? ($parts[1] ?? '');
            $email = $ord['email'] ?? 'customer@example.com';
            $phone = $ord['phone'] ?? '9990051200';
            $shippingAddr = $ord['shipping_address'] ?? 'Customer Shipping Address';
            
            $totalVal = isset($ord['total']) ? (float)$ord['total'] : (float)preg_replace('/[^0-9.]/', '', $ord['amount'] ?? 0);
            $subtotal = isset($ord['subtotal']) ? (float)$ord['subtotal'] : $totalVal;
            $discount = (float)($ord['referral_discount'] ?? 0.00);
            $wallet = (float)($ord['wallet_deduction'] ?? 0.00);
            $shipFee = (float)($ord['shipping'] ?? 0.00);
            $tax = (float)($ord['tax'] ?? 0.00);
            $status = $ord['status'] ?? 'Processing';

            $createdAt = $ord['created_at'] ?? date('Y-m-d H:i:s');

            // Find user id if exists
            $uStmt = $pdo->prepare("SELECT id FROM `users` WHERE `email` = ?");
            $uStmt->execute([$email]);
            $uRow = $uStmt->fetch();
            $userId = $uRow ? $uRow['id'] : null;

            $insertOrder->execute([
                $orderNum, $userId, $fname, $lname, $email, $phone, $shippingAddr,
                $subtotal, $discount, ($ord['referral_code'] ?? null), $wallet, $shipFee, $tax, $totalVal, $status, $createdAt
            ]);
            $newOrderId = $pdo->lastInsertId();

            // Insert line items
            if (!empty($ord['cart']) && is_array($ord['cart'])) {
                foreach ($ord['cart'] as $c) {
                    $pName = $c['name'] ?? 'Product';
                    $pPrice = (float)preg_replace('/[^0-9.]/', '', $c['price'] ?? 0);
                    $pQty = (int)($c['qty'] ?? 1);
                    $pSub = $pPrice * $pQty;
                    $pImg = $c['img'] ?? null;
                    $insertItem->execute([$newOrderId, null, $pName, $pPrice, $pQty, $pSub, $pImg]);
                }
            } else {
                $prodsStr = $ord['products'] ?? 'Premium Vijaya Extract 1500mg (x1)';
                $insertItem->execute([$newOrderId, null, $prodsStr, $subtotal, 1, $subtotal, null]);
            }
        }
        echo "[OK] Orders & Order Items migrated from orders.json.\n";
    }
}

echo "\n=== Migration Completed Successfully! ===\n";
