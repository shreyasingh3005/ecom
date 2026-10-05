<?php
// settings.php
session_start();

// Set default timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/analytics.php';

Auth::requireAdmin();

$db = Database::getInstance();
$adminUser = Auth::admin();

$adminFullName = htmlspecialchars($adminUser['name'] ?? 'Web Administrator');
$nameParts = explode(' ', trim($adminUser['name'] ?? 'Web Admin'));
$adminInitials = strtoupper(substr($nameParts[0] ?? 'W', 0, 1) . substr($nameParts[1] ?? 'A', 0, 1));
$adminData = [
    'first_name' => $nameParts[0] ?? 'Web',
    'last_name' => isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : 'Admin',
    'email' => $adminUser['email'] ?? 'admin@gmail.com'
];

$successMsg = $_GET['success'] ?? '';
$errorMsg = $_GET['error'] ?? '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    
    // --- Update Admin Profile ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_admin') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $fullName = trim($firstName . ' ' . $lastName);
        $email = trim($_POST['email'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        
        if (empty($fullName) || empty($email)) {
            $errorMsg = "Name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Please enter a valid email address.";
        } else {
            if (!empty($newPass)) {
                if (strlen($newPass) < 8) {
                    $errorMsg = "Password must be at least 8 characters long.";
                } else {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $upStmt = $db->prepare("UPDATE admins SET name = ?, email = ?, password = ? WHERE id = ?");
                    $upStmt->execute([$fullName, $email, $hash, $adminUser['id']]);
                    $_SESSION['admin_name'] = $fullName;
                    $_SESSION['admin_email'] = $email;
                    $successMsg = "Admin profile updated successfully.";
                }
            } else {
                $upStmt = $db->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
                $upStmt->execute([$fullName, $email, $adminUser['id']]);
                $_SESSION['admin_name'] = $fullName;
                $_SESSION['admin_email'] = $email;
                $successMsg = "Admin profile updated successfully.";
            }
        }
    } 
    // --- Update Global Settings ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
        // General Tab
        Settings::set('store_name', trim($_POST['store_name'] ?? 'KAMS HEMP'));
        Settings::set('support_email', trim($_POST['support_email'] ?? 'support@kamshemp.com'));
        Settings::set('support_phone', trim($_POST['support_phone'] ?? '+91 98765 43210'));
        Settings::set('currency', $_POST['currency'] ?? 'INR');
        Settings::set('tax_rate', trim($_POST['tax_rate'] ?? '18'));
        Settings::set('tax_percentage', trim($_POST['tax_rate'] ?? '18'));

        // Payment & UPI Tab
        Settings::set('upi_id', trim($_POST['upi_id'] ?? 'kamshemp@upi'));
        Settings::set('upi_merchant_name', trim($_POST['upi_merchant_name'] ?? 'KAMS HEMP India'));
        Settings::set('admin_whatsapp_number', trim($_POST['admin_whatsapp_number'] ?? '+919876543210'));
        Settings::set('payment_gateway_provider', trim($_POST['payment_gateway_provider'] ?? 'native_upi'));
        Settings::set('payment_gateway_key', trim($_POST['payment_gateway_key'] ?? ''));
        Settings::set('payment_gateway_secret', trim($_POST['payment_gateway_secret'] ?? ''));
        Settings::set('payment_webhook_secret', trim($_POST['payment_webhook_secret'] ?? 'whsec_kams_upi_2026'));
        Settings::set('enable_razorpay', isset($_POST['enable_razorpay']) ? '1' : '0');
        Settings::set('razorpay_key', trim($_POST['razorpay_key'] ?? ''));
        Settings::set('enable_cashfree', isset($_POST['enable_cashfree']) ? '1' : '0');
        Settings::set('cashfree_app_id', trim($_POST['cashfree_app_id'] ?? ''));
        Settings::set('cashfree_secret_key', trim($_POST['cashfree_secret_key'] ?? ''));
        Settings::set('cashfree_env', trim($_POST['cashfree_env'] ?? 'sandbox'));
        Settings::set('cashfree_webhook_secret', trim($_POST['cashfree_webhook_secret'] ?? ''));
        Settings::set('enable_stripe', isset($_POST['enable_stripe']) ? '1' : '0');
        Settings::set('stripe_key', trim($_POST['stripe_key'] ?? ''));

        // Shipping Tab
        Settings::set('free_shipping_threshold', trim($_POST['free_shipping_threshold'] ?? '2000'));
        Settings::set('standard_delivery_fee', trim($_POST['standard_delivery_fee'] ?? '150'));
        Settings::set('shipping_fee', trim($_POST['standard_delivery_fee'] ?? '150'));
        Settings::set('express_delivery_fee', trim($_POST['express_delivery_fee'] ?? '300'));

        // SEO Tab
        Settings::set('meta_title', trim($_POST['meta_title'] ?? ''));
        Settings::set('meta_description', trim($_POST['meta_description'] ?? ''));
        Settings::set('ga_tracking_id', trim($_POST['ga_tracking_id'] ?? ''));

        // System Tab
        Settings::set('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
        Settings::set('require_email_verification', isset($_POST['require_email_verification']) ? '1' : '0');
        Settings::set('two_factor_auth', isset($_POST['two_factor_auth']) ? '1' : '0');

        // Referral Tab
        Settings::set('referral_discount_percent', trim($_POST['referral_discount_percent'] ?? '10'));
        Settings::set('referrer_reward_percent', trim($_POST['referrer_reward_percent'] ?? '10'));
        Settings::set('max_wallet_usage_percent', trim($_POST['max_wallet_usage_percent'] ?? '50'));
        Settings::set('referral_banner_title', trim($_POST['referral_banner_title'] ?? 'Give 10% Discount, Earn 10% Recurring Cashback'));
        Settings::set('referral_banner_subtitle', trim($_POST['referral_banner_subtitle'] ?? 'Share your unique referral link with friends. They get an instant 10% discount on checkout, and you receive 10% cash reward into your wallet on every purchase they ever make!'));

        // Storefront & Banners Tab
        Settings::set('hero_badge', trim($_POST['hero_badge'] ?? '🌿 100% Certified Organic Vijaya Extract'));
        Settings::set('hero_title', trim($_POST['hero_title'] ?? 'Ancient Vedic Healing, Powered by Modern Science'));
        Settings::set('hero_subtitle', trim($_POST['hero_subtitle'] ?? "Explore India's most certified Full-Spectrum Vijaya & CBD extracts. Lab tested, doctor prescribed, and AYUSH compliant."));
        Settings::set('hero_cta_text', trim($_POST['hero_cta_text'] ?? 'Shop Ayurvedic Extracts'));
        Settings::set('hero_cta_link', trim($_POST['hero_cta_link'] ?? '#products-grid'));
        Settings::set('announcement_bar_text', trim($_POST['announcement_bar_text'] ?? '⚡ Special Launch Offer: Refer a friend to get 10% OFF, and earn 10% Cashback on EVERY order forever! Free shipping above ₹3,999.'));

        // Handle Banner Image File Upload
        if (!empty($_FILES['hero_banner_file']['name']) && $_FILES['hero_banner_file']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $fileTmp = $_FILES['hero_banner_file']['tmp_name'];
            $fileName = $_FILES['hero_banner_file']['name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($ext, $allowedExts)) {
                $uploadDir = __DIR__ . '/uploads/banners/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newBannerName = 'hero_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $targetPath = $uploadDir . $newBannerName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    Settings::set('hero_banner_image', 'uploads/banners/' . $newBannerName);
                }
            } else {
                $errorMsg = "Invalid banner image format. Allowed formats: JPG, PNG, WEBP.";
            }
        }

        $successMsg = "Platform settings updated successfully!";
    }
}

// Load current settings from database
$allSettings = Settings::getAll();
$currentSettings = [
    'store_name' => $allSettings['store_name'] ?? 'KAMS HEMP',
    'support_email' => $allSettings['support_email'] ?? 'support@kamshemp.com',
    'support_phone' => $allSettings['support_phone'] ?? '+91 98765 43210',
    'currency' => $allSettings['currency'] ?? 'INR',
    'tax_rate' => $allSettings['tax_percentage'] ?? ($allSettings['tax_rate'] ?? '18'),
    'upi_id' => $allSettings['upi_id'] ?? 'kamshemp@upi',
    'upi_merchant_name' => $allSettings['upi_merchant_name'] ?? 'KAMS HEMP India',
    'admin_whatsapp_number' => $allSettings['admin_whatsapp_number'] ?? '+919876543210',
    'payment_gateway_provider' => $allSettings['payment_gateway_provider'] ?? 'native_upi',
    'payment_gateway_key' => $allSettings['payment_gateway_key'] ?? '',
    'payment_gateway_secret' => $allSettings['payment_gateway_secret'] ?? '',
    'payment_webhook_secret' => $allSettings['payment_webhook_secret'] ?? 'whsec_kams_upi_2026',
    'enable_razorpay' => !empty($allSettings['enable_razorpay']) && $allSettings['enable_razorpay'] !== '0',
    'razorpay_key' => $allSettings['razorpay_key'] ?? '',
    'enable_cashfree' => !empty($allSettings['enable_cashfree']) && $allSettings['enable_cashfree'] !== '0',
    'cashfree_app_id' => $allSettings['cashfree_app_id'] ?? '',
    'cashfree_secret_key' => $allSettings['cashfree_secret_key'] ?? '',
    'cashfree_env' => $allSettings['cashfree_env'] ?? 'sandbox',
    'cashfree_webhook_secret' => $allSettings['cashfree_webhook_secret'] ?? '',
    'enable_stripe' => !empty($allSettings['enable_stripe']) && $allSettings['enable_stripe'] !== '0',
    'stripe_key' => $allSettings['stripe_key'] ?? '',
    'free_shipping_threshold' => $allSettings['free_shipping_threshold'] ?? '2000',
    'standard_delivery_fee' => $allSettings['shipping_fee'] ?? ($allSettings['standard_delivery_fee'] ?? '150'),
    'express_delivery_fee' => $allSettings['express_delivery_fee'] ?? '300',
    'meta_title' => $allSettings['meta_title'] ?? 'KAMS HEMP | Premium Vedic Cannabis Extracts',
    'meta_description' => $allSettings['meta_description'] ?? 'Discover premium, AYUSH-certified hemp extracts and CBD products for holistic wellness.',
    'ga_tracking_id' => $allSettings['ga_tracking_id'] ?? 'G-XXXXXXXXXX',
    'maintenance_mode' => !empty($allSettings['maintenance_mode']) && $allSettings['maintenance_mode'] !== '0',
    'require_email_verification' => !empty($allSettings['require_email_verification']) && $allSettings['require_email_verification'] !== '0',
    'two_factor_auth' => !empty($allSettings['two_factor_auth']) && $allSettings['two_factor_auth'] !== '0',
    'referral_discount_percent' => $allSettings['referral_discount_percent'] ?? '10',
    'referrer_reward_percent' => $allSettings['referrer_reward_percent'] ?? '10',
    'max_wallet_usage_percent' => $allSettings['max_wallet_usage_percent'] ?? '50',
    'referral_banner_title' => $allSettings['referral_banner_title'] ?? 'Give 10% Discount, Earn 10% Recurring Cashback',
    'referral_banner_subtitle' => $allSettings['referral_banner_subtitle'] ?? 'Share your unique referral link with friends. They get an instant 10% discount on checkout, and you receive 10% cash reward into your wallet on every purchase they ever make!',
    'hero_badge' => $allSettings['hero_badge'] ?? '🌿 100% Certified Organic Vijaya Extract',
    'hero_title' => $allSettings['hero_title'] ?? 'Ancient Vedic Healing, Powered by Modern Science',
    'hero_subtitle' => $allSettings['hero_subtitle'] ?? "Explore India's most certified Full-Spectrum Vijaya & CBD extracts. Lab tested, doctor prescribed, and AYUSH compliant.",
    'hero_cta_text' => $allSettings['hero_cta_text'] ?? 'Shop Ayurvedic Extracts',
    'hero_cta_link' => $allSettings['hero_cta_link'] ?? '#products-grid',
    'hero_banner_image' => !empty($allSettings['hero_banner_image']) ? $allSettings['hero_banner_image'] : 'uploads/banners/hero_banner_main.jpg',
    'announcement_bar_text' => $allSettings['announcement_bar_text'] ?? '⚡ Special Launch Offer: Refer a friend to get 10% OFF, and earn 10% Cashback on EVERY order forever! Free shipping above ₹3,999.'
];

// --- Load User Data for Referral Statistics ---
$usersStmt = $db->query("
    SELECT u.first_name, u.last_name, u.email, u.referral_code, 
           COUNT(r.id) as referral_count
    FROM users u
    LEFT JOIN referrals r ON u.id = r.referrer_id AND r.status = 'completed'
    GROUP BY u.id
    ORDER BY referral_count DESC
");
$allUsersForRef = $usersStmt->fetchAll();
$totalReferrals = 0;
$referralLeaders = [];

foreach ($allUsersForRef as $u) {
    $count = (int)($u['referral_count'] ?? 0);
    $totalReferrals += $count;
    if ($count > 0) {
        $referralLeaders[] = $u;
    }
}

// Fetch orders from MySQL for notifications
$ordersStmt = $db->query("SELECT * FROM orders ORDER BY id DESC LIMIT 10");
$allOrdersForNotif = $ordersStmt->fetchAll();
$recentOrders = [];
$newOrdersCount = 0;

foreach ($allOrdersForNotif as $order) {
    $st = ucfirst(strtolower($order['order_status']));
    if (in_array($st, ['Processing', 'New', 'Pending'])) {
        $newOrdersCount++;
    }
    if (count($recentOrders) < 4) {
        $cName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: ($order['email'] ?? 'Customer Order');
        $recentOrders[] = [
            'id' => $order['order_number'],
            'product_name' => $cName,
            'amount' => '₹' . number_format((float)($order['total_amount'] ?? 0), 2),
            'time_str' => date('h:i A', strtotime($order['created_at'])),
            'status' => $st
        ];
    }
}

// Real Traffic Data from Database
$trafficMetrics = Analytics::getTrafficMetrics();
$trafficStats = [
    'active_now' => $trafficMetrics['active_now'] ?? 1,
    'today_visits' => $trafficMetrics['today_page_views'] ?? 0,
    'status' => (($trafficMetrics['today_page_views'] ?? 0) > 50) ? 'High Volume' : 'Normal Volume'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Settings | Admin | KAMS HEMP</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            overflow: hidden; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
            background-color: #050505;
        }

        /* 1. Clean Background */
        .fullscreen-bg, .bg-overlay {
            display: none !important;
        }

        /* Admin Layout Container */
        .admin-layout {
            display: flex;
            width: 100vw;
            height: 100vh;
            position: relative;
            z-index: 1;
        }

        /* 3. Sidebar */
        .admin-sidebar {
            width: 280px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            padding: 30px 0;
            box-shadow: 5px 0 25px rgba(0,0,0,0.5);
            position: relative;
            flex-shrink: 0;
            overflow-y: auto;
        }

        .brand-logo {
            text-align: center;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #fff;
            text-shadow: 0 0 10px rgba(255, 0, 255, 0.6);
            margin-bottom: 30px;
            text-decoration: none;
        }

        .brand-logo span {
            display: block;
            font-size: 10px;
            color: #aaa;
            letter-spacing: 4px;
            text-shadow: none;
            margin-top: 5px;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 0 16px;
            flex-grow: 1;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 11px 16px;
            color: #ccc;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            letter-spacing: 0.5px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .nav-link svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.15) 0%, rgba(138, 43, 226, 0.15) 100%);
            color: #fff;
            border-left: 3px solid rgba(255, 0, 255, 0.8);
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.1);
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            padding: 10px;
            border-radius: 10px;
            transition: background 0.3s;
        }

        .admin-profile:hover {
            background: rgba(255,255,255,0.05);
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.6) 0%, rgba(138, 43, 226, 0.6) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .admin-info h4 {
            margin: 0 0 3px 0;
            font-size: 14px;
        }
        .admin-info p {
            margin: 0;
            font-size: 11px;
            color: #aaa;
        }

        /* 4. Main Content Area */
        .admin-main {
            flex: 1;
            overflow-y: auto;
            padding: 40px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(15, 15, 15, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative; /* Added to fix stacking context */
            z-index: 100;
        }

        .search-bar {
            position: relative;
            width: 300px;
        }

        .search-bar input {
            width: 100%;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 10px 15px 10px 40px;
            color: #fff;
            font-size: 13px;
            outline: none;
            box-sizing: border-box;
            transition: all 0.3s;
        }

        .search-bar input:focus {
            border-color: rgba(255, 0, 255, 0.5);
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.2);
        }

        .search-bar svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            stroke: #aaa;
            fill: none;
        }

        .topbar-actions {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        /* Notification Wrapper & Dropdown */
        .notification-wrapper {
            position: relative;
        }

        .action-btn {
            background: transparent;
            border: none;
            color: #ccc;
            cursor: pointer;
            position: relative;
            transition: color 0.3s;
        }

        .action-btn:hover {
            color: #fff;
        }

        .action-btn svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            fill: none;
        }

        .notification-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: #ff4d4d;
            border-radius: 50%;
            border: 2px solid #151515;
        }

        .notification-dropdown {
            position: absolute;
            top: 40px;
            right: 0;
            width: 320px;
            background: rgba(20, 20, 20, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 2px solid rgba(255, 0, 255, 0.6);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.8);
            z-index: 1000;
            display: none;
            flex-direction: column;
            overflow: hidden;
        }

        .notif-section {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .notif-section:last-child {
            border-bottom: none;
            background: rgba(255, 255, 255, 0.02);
        }

        .notif-header {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #aaa;
            margin: 0 0 10px 0;
            display: flex;
            justify-content: space-between;
        }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
            transition: opacity 0.3s, transform 0.3s;
        }

        .notif-item:last-child {
            margin-bottom: 0;
        }

        .notif-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(255, 0, 255, 0.1);
            color: #ffb3ff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notif-icon svg {
            width: 14px;
            height: 14px;
        }

        .notif-text {
            font-size: 13px;
            line-height: 1.4;
            color: #e0e0e0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 190px;
        }

        .notif-time {
            font-size: 11px;
            color: #888;
            display: block;
        }

        .notif-close-btn {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            padding: 0;
            margin-left: 5px;
            transition: color 0.3s;
            display: flex;
            align-items: flex-start;
        }
        
        .notif-close-btn:hover {
            color: #ff4d4d;
        }

        .notif-close-btn svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .traffic-section-link {
            transition: background 0.3s ease;
            display: block;
            text-decoration: none;
        }

        .traffic-section-link:hover {
            background: rgba(255, 255, 255, 0.03) !important;
        }

        .traffic-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .traffic-stat:last-child {
            margin-bottom: 0;
        }

        .traffic-val {
            font-weight: bold;
            color: #fff;
        }

        .status-badge-mini {
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            background: rgba(179, 255, 179, 0.1);
            color: #b3ffb3;
            border: 1px solid #b3ffb3;
        }

        /* 5. Alerts */
        .alert-msg {
            width: 100%;
            padding: 15px 20px;
            border-radius: 10px;
            font-size: 14px;
            box-sizing: border-box;
            border: 1px solid transparent;
            margin-bottom: -10px;
            transition: opacity 0.5s ease;
        }
        .alert-error {
            background: rgba(255, 0, 0, 0.1);
            border-color: rgba(255, 0, 0, 0.4);
            color: #ffb3b3;
        }
        .alert-success {
            background: rgba(0, 255, 0, 0.1);
            border-color: rgba(0, 255, 0, 0.4);
            color: #b3ffb3;
        }

        /* Panels & Tables */
        .panel {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            margin-bottom: 30px;
            flex-shrink: 0;
            min-height: fit-content;
        }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
        }
        .panel-header h3 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            padding: 12px 10px;
            font-size: 12px;
            text-transform: uppercase;
            color: #aaa;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        td {
            padding: 15px 10px;
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
        }
        tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        /* 6. Settings Layout */
        .settings-header h2 {
            margin: 0 0 5px 0;
            font-size: 24px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        
        .settings-header p {
            margin: 0;
            color: #aaa;
            font-size: 14px;
        }

        .settings-wrapper {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }

        .settings-sidebar {
            width: 240px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-shrink: 0;
        }

        .settings-tab-btn {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px 20px;
            color: #ccc;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .settings-tab-btn svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .settings-tab-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .settings-tab-btn.active {
            background: rgba(255, 0, 255, 0.15);
            border-color: rgba(255, 0, 255, 0.5);
            color: #fff;
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.2);
        }

        .settings-content-area {
            flex-grow: 1;
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
        }

        .tab-pane {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-pane.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-title {
            margin: 0 0 25px 0;
            font-size: 20px;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
        }

        /* Form Elements */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 13px;
            color: #ccc;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            padding: 12px 15px;
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-input:focus {
            border-color: rgba(255, 0, 255, 0.5);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.2);
        }

        select.form-input option {
            background: #151515;
            color: #fff;
        }

        /* Toggle Switch */
        .toggle-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .toggle-info h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
        }

        .toggle-info p {
            margin: 0;
            font-size: 12px;
            color: #aaa;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
        }

        .switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255,255,255,0.1);
            transition: .4s;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: #aaa;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: rgba(255, 0, 255, 0.4);
            border-color: rgba(255, 0, 255, 0.8);
        }

        input:checked + .slider:before {
            transform: translateX(22px);
            background-color: #fff;
            box-shadow: 0 0 8px rgba(255, 255, 255, 0.8);
        }

        .save-btn {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.6) 0%, rgba(138, 43, 226, 0.6) 100%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            float: right;
            margin-top: 20px;
        }

        .save-btn:hover {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.8) 0%, rgba(138, 43, 226, 0.8) 100%);
            box-shadow: 0 0 20px rgba(255, 0, 255, 0.4);
            transform: translateY(-2px);
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* Admin Profile Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: rgba(15, 15, 15, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            width: 90%;
            max-width: 450px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .close-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
        }

        @media (max-width: 900px) {
            .settings-wrapper {
                flex-direction: column;
            }
            .settings-sidebar {
                width: 100%;
                flex-direction: row;
                overflow-x: auto;
            }
            .settings-tab-btn {
                white-space: nowrap;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>

    <div class="fullscreen-bg"></div>
    <div class="bg-overlay"></div>

    <div class="admin-layout">
        
        <aside class="admin-sidebar">
            <button type="button" class="admin-sidebar-close" aria-label="Close admin menu">✕</button>
            <a href="cbd.php" class="brand-logo">
                KAMS HEMP
                <span>ADMINISTRATION</span>
            </a>

            <nav class="nav-menu">
                <a href="admin.php" class="nav-link">
                    <svg><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard
                </a>
                <a href="admin_orders.php" class="nav-link">
                    <svg><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    Orders
                </a>
                <a href="inventory.php" class="nav-link">
                    <svg><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    Inventory
                </a>
                <a href="admin_b2b.php" class="nav-link">
                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
                    B2B Wholesale
                </a>
                <a href="admin_blogs.php" class="nav-link">
                    <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    Blogs & Science
                </a>
                <a href="admin_faqs.php" class="nav-link">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    FAQs
                </a>
                <a href="admin_testimonials.php" class="nav-link">
                    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    Testimonials
                </a>
                <a href="admin_banners.php" class="nav-link">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                    Hero & Banners
                </a>
                <a href="admin_contact.php" class="nav-link">
                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    Contact Leads
                </a>
                <a href="users.php" class="nav-link">
                    <svg><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Users
                </a>
                <a href="analytics.php" class="nav-link">
                    <svg><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    Traffic & Analytics
                </a>
                <a href="settings.php" class="nav-link active">
                    <svg><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Settings
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="admin-profile" onclick="openAdminModal()">
                    <div class="admin-avatar"><?= $adminInitials ?></div>
                    <div class="admin-info">
                        <h4><?= $adminFullName ?></h4>
                        <p>Web Administrator</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="admin-main">
            
            <div class="topbar">
                <button type="button" class="admin-mobile-toggle" id="adminSidebarToggle" aria-label="Toggle navigation menu">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
                <div class="search-bar">
                    <svg><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" placeholder="Search settings...">
                </div>
                <div class="topbar-actions">
                    <div class="notification-wrapper">
                        <button class="action-btn" id="notifBtn">
                            <svg><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                            <?php if ($newOrdersCount > 0): ?>
                                <span class="notification-dot" style="display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:bold; width:14px; height:14px; right:-6px; top:-6px;"><?= $newOrdersCount ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <div class="notification-dropdown" id="notifDropdown">
                            <div class="notif-section">
                                <h4 class="notif-header">Recent Orders <a href="admin_orders.php" style="color:#ff00ff; text-decoration:none;">View All</a></h4>
                                <?php if (empty($recentOrders)): ?>
                                    <p style="font-size:12px; color:#aaa; margin:0;" id="empty-notif-msg">No recent orders.</p>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <div class="notif-item" id="notif-<?= htmlspecialchars(str_replace('#', '', $order['id'])) ?>">
                                            <div class="notif-icon">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                            </div>
                                            <div style="flex-grow: 1; padding-right: 5px;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                                    <strong style="font-size: 13px; color: #fff; line-height: 1;"><?= htmlspecialchars($order['id']) ?></strong>
                                                    <span style="font-size: 13px; font-weight: bold; color: #e5c378; line-height: 1;"><?= htmlspecialchars($order['amount'] ?? '₹--') ?></span>
                                                </div>
                                                <div class="notif-text" style="margin-bottom: 4px; line-height: 1.3;" title="<?= htmlspecialchars($order['product_name'] ?? 'Premium Products') ?>"><?= htmlspecialchars($order['product_name'] ?? 'Premium Products') ?></div>
                                                <span class="notif-time" style="margin: 0; line-height: 1;"><?= htmlspecialchars($order['date'] ?? '') ?> at <?= htmlspecialchars($order['time'] ?? ($order['time_str'] ?? '')) ?></span>
                                            </div>
                                            <button class="notif-close-btn" onclick="dismissNotif('notif-<?= htmlspecialchars(str_replace('#', '', $order['id'])) ?>')" title="Dismiss" style="margin-top: -2px;">
                                                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <a href="analytics.php" class="notif-section traffic-section-link">
                                <h4 class="notif-header">Traffic Status <span class="status-badge-mini"><?= htmlspecialchars($trafficStats['status']) ?></span></h4>
                                <div class="traffic-stat">
                                    <span style="color:#aaa;">Active Users Now</span>
                                    <span class="traffic-val"><?= htmlspecialchars($trafficStats['active_now']) ?></span>
                                </div>
                                <div class="traffic-stat">
                                    <span style="color:#aaa;">Today's Page Views</span>
                                    <span class="traffic-val"><?= htmlspecialchars($trafficStats['today_visits']) ?></span>
                                </div>
                                <div style="margin-top: 10px; font-size: 11px; color: #ff00ff; text-align: right; font-weight: 600;">View Analytics →</div>
                            </a>
                        </div>
                    </div>

                    <a href="logout.php" class="action-btn" title="Logout Administrator" aria-label="Logout" style="text-decoration:none; color:#ff6666;">
                        <svg><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </a>
                </div>
            </div>

            <div class="settings-header">
                <h2>Platform Settings</h2>
                <p>Manage store configurations, gateways, shipping rules, and security policies.</p>
            </div>

            <?php if (!empty($errorMsg)): ?>
                <div class="alert-msg alert-error"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <?php if (!empty($successMsg)): ?>
                <div class="alert-msg alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <div class="panel" style="padding: 25px;">
                <div class="panel-header" style="margin-bottom: 15px; border-bottom: none; padding-bottom: 0;">
                    <h3>Referral Network Overview</h3>
                    <div style="font-size: 15px; color: #ffb3ff; font-weight: bold; background: rgba(255, 0, 255, 0.1); padding: 5px 12px; border-radius: 8px; border: 1px solid rgba(255, 0, 255, 0.3);">
                        Total Referrals: <?= number_format($totalReferrals) ?>
                    </div>
                </div>
                
                <?php if (empty($referralLeaders)): ?>
                    <p style="color: #aaa; text-align: center; margin: 20px 0 0 0;">No active referrals yet in the customer database.</p>
                <?php else: ?>
                    <table style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th>Customer (Referrer)</th>
                                <th>Email Address</th>
                                <th>Referral Code</th>
                                <th>Total Shares / Uses</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($referralLeaders, 0, 5) as $leader): ?>
                                <tr>
                                    <td style="color: #fff; font-weight: 500;"><?= htmlspecialchars(($leader['first_name']??'') . ' ' . ($leader['last_name']??'')) ?></td>
                                    <td><?= htmlspecialchars($leader['email'] ?? '') ?></td>
                                    <td style="color: #e5c378; font-family: monospace; font-weight: bold; letter-spacing: 1px;"><?= htmlspecialchars($leader['referral_code'] ?? 'N/A') ?></td>
                                    <td><?= (int)($leader['referral_count'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="settings-wrapper">
                
                <div class="settings-sidebar">
                    <button class="settings-tab-btn active" type="button" onclick="openTab('general', event)">
                        <svg><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                        General Store Info
                    </button>
                    <button class="settings-tab-btn" type="button" onclick="openTab('banners', event)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M20.4 14.5L16 10 4 20"/></svg>
                        Banners & Storefront
                    </button>
                    <button class="settings-tab-btn" type="button" onclick="openTab('payment', event)">
                        <svg><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                        Payment & Checkout
                    </button>
                    <button class="settings-tab-btn" type="button" onclick="openTab('shipping', event)">
                        <svg><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                        Shipping Rules
                    </button>
                    <button class="settings-tab-btn" type="button" onclick="openTab('referral', event)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        Referrals & Rewards
                    </button>
                    <button class="settings-tab-btn" type="button" onclick="openTab('seo', event)">
                        <svg><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                        SEO & Analytics
                    </button>
                    <button class="settings-tab-btn" type="button" onclick="openTab('system', event)">
                        <svg><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        System & Security
                    </button>
                </div>

                <div class="settings-content-area">
                    <form action="settings.php" method="POST" enctype="multipart/form-data" class="clearfix">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div id="tab-general" class="tab-pane active">
                            <h3 class="tab-title">General Store Information</h3>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label>Store Name</label>
                                    <input type="text" name="store_name" class="form-input" value="<?= htmlspecialchars($currentSettings['store_name']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Support Email Address</label>
                                    <input type="email" name="support_email" class="form-input" value="<?= htmlspecialchars($currentSettings['support_email']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Support Phone Number</label>
                                    <input type="text" name="support_phone" class="form-input" value="<?= htmlspecialchars($currentSettings['support_phone']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Base Currency</label>
                                    <select name="currency" class="form-input">
                                        <option value="INR" <?= $currentSettings['currency'] == 'INR' ? 'selected' : '' ?>>INR (₹) - Indian Rupee</option>
                                        <option value="USD" <?= $currentSettings['currency'] == 'USD' ? 'selected' : '' ?>>USD ($) - US Dollar</option>
                                        <option value="EUR" <?= $currentSettings['currency'] == 'EUR' ? 'selected' : '' ?>>EUR (€) - Euro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Base Tax Rate (%)</label>
                                    <input type="number" name="tax_rate" class="form-input" value="<?= htmlspecialchars($currentSettings['tax_rate']) ?>">
                                </div>
                            </div>
                        </div>

                        <div id="tab-banners" class="tab-pane">
                            <h3 class="tab-title">Storefront Hero & Promotional Banners</h3>
                            <p style="color: #aaa; font-size: 13px; margin-bottom: 20px;">Manage hero banner imagery, floating badges, promotional headings, and call-to-actions shown on the storefront homepage.</p>
                            
                            <div class="form-grid">
                                <div class="form-group full-width" style="background: rgba(255,255,255,0.03); padding: 18px; border-radius: 14px; border: 1px solid rgba(255,0,255,0.2);">
                                    <label style="color:#ffb3ff; font-weight: 700; margin-bottom: 12px; display: block; font-size: 14px;">Active Hero Banner Image</label>
                                    <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                        <div style="width: 280px; height: 140px; border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,0,255,0.4); box-shadow: 0 0 20px rgba(255,0,255,0.2); background: #000; flex-shrink: 0;">
                                            <img src="<?= htmlspecialchars($currentSettings['hero_banner_image']) ?>?v=<?= time() ?>" alt="Current Hero Banner" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                        <div style="flex: 1; min-width: 250px;">
                                            <label style="font-size: 13px; color: #fff; margin-bottom: 6px;">Upload Replacement Hero Banner (16:9 ratio recommended)</label>
                                            <input type="file" name="hero_banner_file" class="form-input" accept="image/jpeg,image/png,image/webp" style="padding: 10px; background: rgba(0,0,0,0.5);">
                                            <p style="font-size: 11px; color: #aaa; margin-top: 6px;">Supports JPG, PNG, WEBP (Max 5MB). The new banner updates instantly on the homepage.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group full-width">
                                    <label>Storefront Announcement Bar Marquee (Top Ticker)</label>
                                    <input type="text" name="announcement_bar_text" class="form-input" value="<?= htmlspecialchars($currentSettings['announcement_bar_text']) ?>">
                                    <p style="font-size:11px; color:#888; margin-top:4px;">Appears on the continuous top scrolling announcement ribbon on every page.</p>
                                </div>

                                <div class="form-group">
                                    <label>Hero Floating Badge</label>
                                    <input type="text" name="hero_badge" class="form-input" value="<?= htmlspecialchars($currentSettings['hero_badge']) ?>" placeholder="e.g. 🌿 100% Certified Organic Vijaya Extract">
                                </div>

                                <div class="form-group">
                                    <label>Hero CTA Button Text</label>
                                    <input type="text" name="hero_cta_text" class="form-input" value="<?= htmlspecialchars($currentSettings['hero_cta_text']) ?>" placeholder="e.g. Shop Ayurvedic Extracts">
                                </div>

                                <div class="form-group full-width">
                                    <label>Hero Main Title</label>
                                    <input type="text" name="hero_title" class="form-input" value="<?= htmlspecialchars($currentSettings['hero_title']) ?>" placeholder="e.g. Ancient Vedic Healing, Powered by Modern Science">
                                </div>

                                <div class="form-group full-width">
                                    <label>Hero Subtitle / Description</label>
                                    <textarea name="hero_subtitle" class="form-input" rows="3"><?= htmlspecialchars($currentSettings['hero_subtitle']) ?></textarea>
                                </div>

                                <div class="form-group full-width">
                                    <label>Hero CTA Target Link</label>
                                    <input type="text" name="hero_cta_link" class="form-input" value="<?= htmlspecialchars($currentSettings['hero_cta_link']) ?>" placeholder="e.g. #products-grid">
                                </div>
                            </div>
                        </div>

                        <div id="tab-payment" class="tab-pane">
                            <h3 class="tab-title">UPI & Payment Gateway Settings</h3>
                            <p style="color: #aaa; font-size: 13px; margin-bottom: 25px;">Configure your dynamic UPI Virtual Payment Address (VPA), Merchant Name, and automated server-side verification webhook credentials.</p>

                            <!-- Dynamic UPI Section -->
                            <div style="background: rgba(0, 255, 204, 0.03); border: 1px solid rgba(0, 255, 204, 0.15); border-radius: 12px; padding: 20px; margin-bottom: 25px;">
                                <h4 style="color: #00ffcc; font-size: 15px; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                                    <span>⚡</span> Dynamic UPI & Scan-to-Pay Configuration
                                </h4>
                                <p style="color: #94a3b8; font-size: 12px; margin-bottom: 18px;">These settings are dynamically injected into customer QR codes and UPI payment requests at checkout.</p>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Merchant UPI ID / VPA</label>
                                        <input type="text" name="upi_id" class="form-input" value="<?= htmlspecialchars($currentSettings['upi_id']) ?>" placeholder="yourbusiness@upi" required>
                                        <p style="font-size: 11px; color: #888; margin-top: 4px;">Dynamic QR code generates payment requests to this UPI ID.</p>
                                    </div>

                                    <div class="form-group">
                                        <label>Business / Merchant Name</label>
                                        <input type="text" name="upi_merchant_name" class="form-input" value="<?= htmlspecialchars($currentSettings['upi_merchant_name']) ?>" placeholder="KAMS HEMP India" required>
                                        <p style="font-size: 11px; color: #888; margin-top: 4px;">Name displayed on customer's Google Pay / PhonePe / Paytm screen.</p>
                                    </div>

                                    <div class="form-group full-width">
                                        <label>Admin WhatsApp Number (for Order Sharing & Customer Support)</label>
                                        <input type="text" name="admin_whatsapp_number" class="form-input" value="<?= htmlspecialchars($currentSettings['admin_whatsapp_number']) ?>" placeholder="+919876543210" required>
                                        <p style="font-size: 11px; color: #888; margin-top: 4px;">Customers receive one-click WhatsApp sharing to this number on the order confirmation screen.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Server-Side Verification & Webhook Section -->
                            <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 20px; margin-bottom: 25px;">
                                <h4 style="color: #f1f5f9; font-size: 15px; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                                    <span>🔒</span> Server-Side Verification & Webhook Endpoint
                                </h4>
                                <p style="color: #94a3b8; font-size: 12px; margin-bottom: 18px;">Automated gateway callback verifies cryptographic signatures and confirms orders instantly.</p>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Active Payment Provider</label>
                                        <select name="payment_gateway_provider" class="form-input">
                                            <option value="native_upi" <?= $currentSettings['payment_gateway_provider'] === 'native_upi' ? 'selected' : '' ?>>Direct Dynamic UPI & QR (Native Engine)</option>
                                            <option value="cashfree" <?= $currentSettings['payment_gateway_provider'] === 'cashfree' ? 'selected' : '' ?>>Cashfree Payment Gateway (Cards, NetBanking, UPI)</option>
                                            <option value="razorpay" <?= $currentSettings['payment_gateway_provider'] === 'razorpay' ? 'selected' : '' ?>>Razorpay UPI & Cards</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Webhook HMAC-SHA256 Secret</label>
                                        <input type="text" name="payment_webhook_secret" class="form-input" value="<?= htmlspecialchars($currentSettings['payment_webhook_secret']) ?>" placeholder="whsec_xxxxxxxxxxxx">
                                        <p style="font-size: 11px; color: #888; margin-top: 4px;">Used to verify cryptographic signature on /api/payment_webhook.php.</p>
                                    </div>

                                    <div class="form-group full-width">
                                        <label>Webhook Listener URL (Configure in Payment Gateway)</label>
                                        <input type="text" class="form-input" value="https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?>/ecom/api/payment_webhook.php" readonly style="opacity: 0.75; font-family: monospace; background: rgba(0,0,0,0.3);">
                                    </div>

                                    <div class="form-group">
                                        <label>Gateway Key ID</label>
                                        <input type="text" name="payment_gateway_key" class="form-input" value="<?= htmlspecialchars($currentSettings['payment_gateway_key']) ?>" placeholder="rzp_live_...">
                                    </div>

                                    <div class="form-group">
                                        <label>Gateway Secret Key</label>
                                        <input type="password" name="payment_gateway_secret" class="form-input" value="<?= htmlspecialchars($currentSettings['payment_gateway_secret']) ?>" placeholder="••••••••••••">
                                    </div>
                                </div>
                            </div>

                            <!-- Cashfree Payment Gateway Section -->
                            <div style="background: rgba(0, 102, 255, 0.04); border: 1px solid rgba(0, 102, 255, 0.25); border-radius: 12px; padding: 20px; margin-bottom: 25px;">
                                <div class="toggle-row" style="margin-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 16px;">
                                    <div class="toggle-info">
                                        <h4 style="color: #60a5fa; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                                            <span>💳</span> Cashfree Payment Gateway Integration
                                        </h4>
                                        <p>Accept Credit/Debit Cards, NetBanking, UPI, and Digital Wallets via Cashfree PG (Orders API v2023-08-01).</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="enable_cashfree" <?= $currentSettings['enable_cashfree'] ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Cashfree Environment</label>
                                        <select name="cashfree_env" class="form-input">
                                            <option value="sandbox" <?= $currentSettings['cashfree_env'] === 'sandbox' ? 'selected' : '' ?>>Sandbox / Test Mode</option>
                                            <option value="production" <?= $currentSettings['cashfree_env'] === 'production' ? 'selected' : '' ?>>Production / Live Mode</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Cashfree App ID (Client ID)</label>
                                        <input type="text" name="cashfree_app_id" class="form-input" value="<?= htmlspecialchars($currentSettings['cashfree_app_id']) ?>" placeholder="TEST_... or live_...">
                                    </div>

                                    <div class="form-group full-width">
                                        <label>Cashfree Secret Key</label>
                                        <input type="password" name="cashfree_secret_key" class="form-input" value="<?= htmlspecialchars($currentSettings['cashfree_secret_key']) ?>" placeholder="cfsk_ma_...">
                                    </div>

                                    <div class="form-group">
                                        <label>Cashfree Webhook Secret (Optional)</label>
                                        <input type="text" name="cashfree_webhook_secret" class="form-input" value="<?= htmlspecialchars($currentSettings['cashfree_webhook_secret']) ?>" placeholder="Optional webhook signature secret">
                                    </div>

                                    <div class="form-group">
                                        <label>Cashfree Webhook Callback URL</label>
                                        <input type="text" class="form-input" value="https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?>/ecom/api/cashfree_webhook.php" readonly style="opacity: 0.75; font-family: monospace; background: rgba(0,0,0,0.3);">
                                    </div>
                                </div>
                            </div>

                            <!-- Legacy Optional Gateways -->
                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <h4>Razorpay Integration Toggle</h4>
                                    <p>Enable Razorpay for UPI, Netbanking, and Indian Cards.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="enable_razorpay" <?= $currentSettings['enable_razorpay'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="form-group full-width" style="margin-bottom: 20px;">
                                <label>Razorpay Key ID</label>
                                <input type="password" name="razorpay_key" class="form-input" value="<?= htmlspecialchars($currentSettings['razorpay_key']) ?>">
                            </div>

                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <h4>Stripe Integration Toggle</h4>
                                    <p>Enable Stripe for International Credit Card processing.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="enable_stripe" <?= $currentSettings['enable_stripe'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="form-group full-width">
                                <label>Stripe Publishable Key</label>
                                <input type="password" name="stripe_key" class="form-input" value="<?= htmlspecialchars($currentSettings['stripe_key']) ?>">
                            </div>
                        </div>

                        <div id="tab-shipping" class="tab-pane">
                            <h3 class="tab-title">Shipping & Logistics Rules</h3>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label>Free Shipping Threshold (₹)</label>
                                    <input type="number" name="free_shipping_threshold" class="form-input" value="<?= htmlspecialchars($currentSettings['free_shipping_threshold']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Standard Delivery Fee (Pan-India)</label>
                                    <input type="number" name="standard_delivery_fee" class="form-input" value="<?= htmlspecialchars($currentSettings['standard_delivery_fee']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Express Delivery Fee (Major Cities)</label>
                                    <input type="number" name="express_delivery_fee" class="form-input" value="<?= htmlspecialchars($currentSettings['express_delivery_fee']) ?>">
                                </div>
                            </div>
                        </div>

                        <div id="tab-referral" class="tab-pane">
                            <h3 class="tab-title">Referral & Recurring Cashback Program</h3>
                            <p style="color: #aaa; font-size: 13px; margin-bottom: 20px;">Configure the dual-sided referral model: referee gets instant discount at checkout, and referrer receives recurring wallet cashback on every qualifying order.</p>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>New Customer Discount (%)</label>
                                    <input type="number" name="referral_discount_percent" class="form-input" value="<?= htmlspecialchars($currentSettings['referral_discount_percent']) ?>" min="0" max="100">
                                    <p style="font-size:11px; color:#888; margin-top:4px;">Instant discount the referee gets on qualifying checkout (e.g. 10%).</p>
                                </div>

                                <div class="form-group">
                                    <label>Referrer Recurring Reward (%)</label>
                                    <input type="number" name="referrer_reward_percent" class="form-input" value="<?= htmlspecialchars($currentSettings['referrer_reward_percent']) ?>" min="0" max="100">
                                    <p style="font-size:11px; color:#888; margin-top:4px;">Cashback deposited to referrer's wallet on EVERY order their friend places (e.g. 10%).</p>
                                </div>

                                <div class="form-group full-width">
                                    <label>Max Order Wallet Deduction (%)</label>
                                    <input type="number" name="max_wallet_usage_percent" class="form-input" value="<?= htmlspecialchars($currentSettings['max_wallet_usage_percent']) ?>" min="0" max="100">
                                    <p style="font-size:11px; color:#888; margin-top:4px;">Maximum percentage of an order total that can be paid using customer wallet credits (e.g. 50%).</p>
                                </div>

                                <div class="form-group full-width">
                                    <label>Referral Showcase Banner Title</label>
                                    <input type="text" name="referral_banner_title" class="form-input" value="<?= htmlspecialchars($currentSettings['referral_banner_title']) ?>">
                                </div>

                                <div class="form-group full-width">
                                    <label>Referral Showcase Description</label>
                                    <textarea name="referral_banner_subtitle" class="form-input" rows="3"><?= htmlspecialchars($currentSettings['referral_banner_subtitle']) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div id="tab-seo" class="tab-pane">
                            <h3 class="tab-title">SEO & External Analytics</h3>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label>Global Meta Title</label>
                                    <input type="text" name="meta_title" class="form-input" value="<?= htmlspecialchars($currentSettings['meta_title']) ?>">
                                </div>
                                <div class="form-group full-width">
                                    <label>Global Meta Description</label>
                                    <textarea name="meta_description" class="form-input" rows="3"><?= htmlspecialchars($currentSettings['meta_description']) ?></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label>Google Analytics Tracking ID (GA4)</label>
                                    <input type="text" name="ga_tracking_id" class="form-input" value="<?= htmlspecialchars($currentSettings['ga_tracking_id']) ?>">
                                </div>
                            </div>
                        </div>

                        <div id="tab-system" class="tab-pane">
                            <h3 class="tab-title">System & Security Configuration</h3>
                            
                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <h4>Maintenance Mode</h4>
                                    <p>Disable storefront access for visitors. Admin access remains open.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="maintenance_mode" <?= $currentSettings['maintenance_mode'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <h4>Require Email OTP Verification</h4>
                                    <p>Force new customers to verify their email address before checkout.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="require_email_verification" <?= $currentSettings['require_email_verification'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <h4>Two-Factor Authentication (Admin)</h4>
                                    <p>Require an authenticator app code for all admin logins.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="two_factor_auth" <?= $currentSettings['two_factor_auth'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="save-btn">Save All Settings</button>
                    </form>
                </div>

            </div>
        </main>
    </div>

    <div id="adminModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Admin Profile</h3>
                <button type="button" class="close-btn" onclick="closeAdminModal()">&times;</button>
            </div>
            <form method="POST" action="settings.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_admin">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-input" value="<?= htmlspecialchars($adminData['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-input" value="<?= htmlspecialchars($adminData['last_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($adminData['email'] ?? '') ?>" required>
                </div>

                <div class="form-group" style="margin-top: 25px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                    <label>Change Password (Optional)</label>
                    <input type="password" name="new_password" class="form-input" placeholder="Min 8 chars (letters & numbers)" pattern="^[A-Za-z0-9]{8,}$" title="At least 8 characters, letters and numbers only">
                </div>

                <button type="submit" class="save-btn" style="margin-top: 20px; width: 100%; text-align: center; float:none;">Save Admin Profile</button>
                <a href="logout.php" class="btn-outline" style="margin-top: 10px; color: #ff6666; border-color: rgba(255, 102, 102, 0.4); text-align: center; display: block; text-decoration: none;">Log Out of Admin</a>
            </form>
        </div>
    </div>

    <script>
        function openTab(tabId, event) {
            // Hide all tab panes
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.settings-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab pane
            document.getElementById('tab-' + tabId).classList.add('active');

            // Activate the clicked button
            if (event) {
                event.currentTarget.classList.add('active');
            }
        }

        function openAdminModal() {
            document.getElementById('adminModal').style.display = 'flex';
        }

        function closeAdminModal() {
            document.getElementById('adminModal').style.display = 'none';
        }

        // Notification System Logic
        const notifBtn = document.getElementById('notifBtn');
        const notifDropdown = document.getElementById('notifDropdown');

        if(notifBtn) {
            notifBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (notifDropdown.style.display === 'flex') {
                    notifDropdown.style.display = 'none';
                } else {
                    notifDropdown.style.display = 'flex';
                }
            });
        }

        // Dismiss individual notification
        function dismissNotif(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.transition = 'opacity 0.3s, transform 0.3s, margin 0.3s, height 0.3s, padding 0.3s';
                el.style.opacity = '0';
                el.style.transform = 'translateX(10px)';
                setTimeout(() => {
                    el.style.display = 'none';
                    
                    const notifContainer = el.parentElement;
                    let anyVisible = false;
                    const items = notifContainer.querySelectorAll('.notif-item');
                    items.forEach(item => {
                        if(item.style.display !== 'none') anyVisible = true;
                    });
                    
                    if(!anyVisible && !document.getElementById('empty-notif-msg')) {
                        const emptyMsg = document.createElement('p');
                        emptyMsg.id = 'empty-notif-msg';
                        emptyMsg.style.cssText = 'font-size:12px; color:#aaa; margin:0;';
                        emptyMsg.innerText = 'No recent orders.';
                        notifContainer.insertBefore(emptyMsg, notifContainer.children[1]);
                        
                        const notifDot = notifBtn.querySelector('.notification-dot');
                        if (notifDot) notifDot.style.display = 'none';
                    } else {
                        const notifDot = notifBtn.querySelector('.notification-dot');
                        if (notifDot) {
                            let count = parseInt(notifDot.innerText);
                            if (!isNaN(count) && count > 1) {
                                notifDot.innerText = count - 1;
                            } else {
                                notifDot.style.display = 'none';
                            }
                        }
                    }
                }, 300);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('adminModal');
            if (event.target === modal) {
                closeAdminModal();
            }

            // Close Notification Dropdown if clicking outside
            if (notifBtn && !notifBtn.contains(event.target) && notifDropdown && !notifDropdown.contains(event.target)) {
                notifDropdown.style.display = 'none';
            }
        }

        // Hide alert message automatically after 4 seconds
        document.addEventListener("DOMContentLoaded", function() {
            const alertMsgs = document.querySelectorAll('.alert-msg');
            alertMsgs.forEach(msg => {
                setTimeout(() => {
                    msg.style.opacity = '0';
                    setTimeout(() => msg.style.display = 'none', 500);
                }, 4000);
            });
        });
    </script>
    <script src="assets/js/responsive.js"></script>
</body>
</html>