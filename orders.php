<?php
// orders.php - Live MySQL User Order History
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('orders', 'Order History');

$isLoggedIn = Auth::isCustomerLoggedIn();
$currentUser = null;
$initials = "AS";

if ($isLoggedIn) {
    $currentUser = Auth::getUser();
    $initials = strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1));
} else {
    $currentUser = [
        'first_name' => 'Guest',
        'last_name' => 'User',
        'email' => ''
    ];
}

$db = Database::getInstance();
$mockOrders = [];

if ($isLoggedIn && $currentUser) {
    $userId = Auth::getUserId();
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? OR email = ? ORDER BY id DESC");
    $stmt->execute([$userId, $currentUser['email']]);
    $rawOrders = $stmt->fetchAll();

    foreach ($rawOrders as $ro) {
        $orderId = (int)$ro['id'];
        $stmtItems = $db->prepare("
            SELECT oi.*, 
                   (SELECT image_url FROM product_images WHERE product_id = oi.product_id ORDER BY is_primary DESC, id ASC LIMIT 1) as primary_image 
            FROM order_items oi 
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll();

        $itemsList = [];
        foreach ($items as $it) {
            $itemsList[] = [
                'name' => $it['product_name'],
                'qty' => (int)$it['quantity'],
                'price' => '₹' . number_format((float)$it['price'], 2),
                'img' => !empty($it['primary_image']) ? $it['primary_image'] : 'https://images.unsplash.com/photo-1603525287431-7b79a7852b75?auto=format&fit=crop&w=80&h=80&q=80'
            ];
        }

        $status = $ro['order_status'];
        $statusColor = '#e5c378'; // Default gold
        if ($status === 'Delivered') $statusColor = '#4caf50';
        if ($status === 'Cancelled' || $status === 'Refunded') $statusColor = '#ff4d4d';

        $mockOrders[] = [
            'id' => $ro['order_number'],
            'date' => date('M d, Y', strtotime($ro['created_at'])),
            'status' => $status,
            'status_color' => $statusColor,
            'total' => '₹' . number_format((float)$ro['total_amount'], 2),
            'items' => $itemsList
        ];
    }
}

// Fallback dummy data if file is empty
if (empty($mockOrders)) {
    $mockOrders = [
        [
            'id' => 'ORD-101',
            'date' => date('M d, Y'),
            'status' => 'Processing',
            'status_color' => '#e5c378',
            'total' => '₹2,999',
            'items' => [
                [
                    'name' => 'Premium Vijaya Extract 1500mg',
                    'qty' => 1,
                    'price' => '₹2,999',
                    'img' => 'https://images.unsplash.com/photo-1603525287431-7b79a7852b75?auto=format&fit=crop&w=150&q=80'
                ]
            ]
        ]
    ];
}

// Count new/processing orders for the numeric notification
$newOrdersCount = 0;
foreach ($mockOrders as $order) {
    if (in_array($order['status'], ['Processing', 'New', 'Pending'])) {
        $newOrdersCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History | KAMS HEMP</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
        }

        /* 1. Clean Background */
        .fullscreen-bg, .bg-overlay {
            display: none !important;
        }

        /* 3. Seamless Loop Notification Bar */
        .notification-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 35px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 0, 255, 0.3);
            display: flex;
            align-items: center;
            overflow: hidden;
            z-index: 102; 
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .notification-track {
            display: flex;
            width: max-content;
            animation: seamless-marquee 35s linear infinite;
        }

        .notification-track:hover {
            animation-play-state: paused;
        }

        .marquee-content {
            display: flex;
            gap: 50px;
            padding-right: 50px;
        }

        .marquee-content span {
            text-shadow: 0 0 8px rgba(255, 0, 255, 0.6);
            white-space: nowrap; 
        }

        @keyframes seamless-marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); } 
        }

        /* 4. The Header Banner */
        .header-banner {
            position: fixed;
            top: 35px; 
            left: 0;
            width: 100%;
            height: 80px;
            background: rgba(10, 10, 10, 0.4); 
            backdrop-filter: blur(12px); 
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 0 50px;
            box-sizing: border-box;
            z-index: 100;
        }

        .header-nav {
            display: flex;
            gap: 30px;
            height: 100%; 
            align-items: center;
        }

        .header-nav > a, .nav-item-has-mega > a {
            color: #ccc;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .header-nav > a:hover, .nav-item-has-mega > a:hover {
            color: #fff;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
        }

        /* 4.1 Mega Menu */
        .nav-item-has-mega {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .mega-menu {
            position: absolute;
            top: 80px; 
            left: 0;
            width: 50vw; 
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 2px solid rgba(255, 0, 255, 0.6);
            border-radius: 0 0 15px 15px;
            padding: 35px 40px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(15px);
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 20px 40px rgba(0,0,0,0.8);
            cursor: default;
        }

        .mega-menu::before {
            content: '';
            position: absolute;
            top: -20px;
            left: 0;
            width: 100%;
            height: 20px;
            background: transparent;
        }

        .nav-item-has-mega:hover .mega-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .mega-column {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .mega-column h3 {
            color: #fff;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-nav .mega-menu a {
            color: #aaa;
            font-size: 13px;
            font-weight: 500;
            text-transform: capitalize;
            letter-spacing: 0.5px;
            padding: 8px 0;
            transition: all 0.3s ease;
            text-shadow: none;
            display: inline-block;
            width: fit-content;
        }

        .header-nav .mega-menu a:hover {
            color: #fff;
            transform: translateX(8px);
            text-shadow: 0 0 8px rgba(255, 0, 255, 0.4);
        }

        .header-logo {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-img {
            height: 45px; 
            width: auto;
            filter: drop-shadow(0 0 10px rgba(255, 0, 255, 0.6));
        }

        /* 5. Header Icons */
        .header-icons {
            margin-left: auto; 
            display: flex;
            gap: 25px;
            align-items: center;
        }

        .search-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 25px;
            padding: 10px 15px 10px 38px;
            color: #fff;
            font-size: 13px;
            font-family: inherit;
            width: 220px;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            outline: none;
            backdrop-filter: blur(5px);
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
            opacity: 1; 
        }

        .search-input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 0, 255, 0.5);
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.3);
            width: 280px;
        }

        .search-icon-inside {
            position: absolute;
            left: 12px;
            width: 16px;
            height: 16px;
            fill: none;
            stroke: rgba(255, 255, 255, 0.6);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            pointer-events: none; 
            transition: stroke 0.3s ease;
        }

        .search-input:focus + .search-icon-inside {
            stroke: rgba(255, 0, 255, 0.8);
        }

        .header-icons > a {
            color: #ccc;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .header-icons > a:hover {
            color: #fff;
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.8));
        }

        .header-icons svg:not(.search-icon-inside) {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: rgba(255, 0, 255, 0.8);
            color: white;
            font-size: 10px;
            font-weight: bold;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 5px rgba(255, 0, 255, 0.5);
        }

        /* 6. Main Content Wrapper */
        .content-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 145px; 
            padding-bottom: 60px;
            box-sizing: border-box;
        }

        /* 7. DASHBOARD & ORDER HISTORY STYLES */
        .dashboard-container {
            width: 95%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 40px;
            margin-top: 40px;
        }

        .dashboard-sidebar {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px 0;
            height: fit-content;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
        }

        .user-summary {
            text-align: center;
            padding: 0 30px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.4), rgba(138, 43, 226, 0.4));
            border: 2px solid rgba(255, 255, 255, 0.2);
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            color: #fff;
            box-shadow: 0 0 20px rgba(255, 0, 255, 0.2);
        }

        .user-summary h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .user-summary p {
            margin: 0;
            font-size: 13px;
            color: #aaa;
        }

        .dashboard-nav {
            display: flex;
            flex-direction: column;
        }

        .dashboard-nav a {
            padding: 15px 30px;
            color: #ccc;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 1px;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-nav a svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .dashboard-nav a:hover, .dashboard-nav a.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
            border-left-color: rgba(255, 0, 255, 0.6);
        }

        .dashboard-content {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
        }

        .dashboard-header {
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-header-text h2 {
            margin: 0 0 5px 0;
            font-size: 24px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #fff;
        }

        .dashboard-header-text p {
            margin: 0;
            color: #aaa;
            font-size: 14px;
        }

        /* Order Cards */
        .order-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            border-color: rgba(255, 0, 255, 0.4);
            box-shadow: 0 5px 20px rgba(0,0,0,0.4);
            transform: translateY(-2px);
        }

        .order-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .order-meta h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #fff;
            letter-spacing: 1px;
        }

        .order-meta p {
            margin: 0;
            font-size: 13px;
            color: #aaa;
        }

        .order-status {
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid currentColor;
            letter-spacing: 0.5px;
        }

        .order-item-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .item-img {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .item-details {
            flex: 1;
        }

        .item-details h5 {
            margin: 0 0 5px 0;
            font-size: 15px;
            color: #fff;
            font-weight: 500;
        }

        .item-details p {
            margin: 0;
            font-size: 13px;
            color: #aaa;
        }

        .item-price {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
        }

        .order-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 15px;
        }

        .order-total {
            font-size: 16px;
            font-weight: 600;
            color: #e5c378;
        }

        .order-actions {
            display: flex;
            gap: 10px;
        }

        .btn-outline, .btn-solid {
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        .btn-outline:hover {
            border-color: rgba(255, 0, 255, 0.6);
            color: #fff;
            background: rgba(255, 0, 255, 0.1);
        }

        .btn-solid {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.6) 0%, rgba(138, 43, 226, 0.6) 100%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .btn-solid:hover {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.8) 0%, rgba(138, 43, 226, 0.8) 100%);
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.4);
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px dashed rgba(255, 255, 255, 0.2);
            border-radius: 15px;
        }

        .empty-state h3 {
            margin: 0 0 10px 0;
            font-size: 20px;
            color: #fff;
        }

        .empty-state p {
            margin: 0 0 20px 0;
            color: #aaa;
            font-size: 14px;
        }

        @media (max-width: 900px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 600px) {
            .order-top, .order-bottom {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            .order-actions {
                width: 100%;
                flex-direction: column;
            }
            .btn-outline, .btn-solid {
                width: 100%;
                text-align: center;
                box-sizing: border-box;
            }
        }

        /* 10. Footer Styles */
        .site-footer {
            width: 100%;
            background: rgba(10, 10, 10, 0.7);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #ccc;
            padding: 60px 0 20px;
            margin-top: auto; 
            position: relative;
            z-index: 10;
        }

        .footer-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
            text-align: left;
        }

        .footer-column h3 {
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 20px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .footer-column p {
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 15px;
            color: rgba(255, 255, 255, 0.6);
            text-shadow: none;
        }

        .footer-logo-text {
            color: #fff;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-decoration: none;
            text-shadow: 0 0 10px rgba(255, 0, 255, 0.6);
            display: inline-block;
            margin-bottom: 15px;
        }

        .footer-column nav {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-column nav a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
            width: fit-content;
        }

        .footer-column nav a:hover {
            color: #fff;
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.8);
            transform: translateX(5px);
        }

        .footer-subscribe {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 5px;
            margin-top: 10px;
        }

        .footer-subscribe input {
            background: transparent;
            border: none;
            color: #fff;
            padding: 8px 15px;
            font-size: 13px;
            outline: none;
            width: 100%;
        }

        .footer-subscribe input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .footer-subscribe button {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 15px;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .footer-subscribe button:hover {
            background: rgba(255, 0, 255, 0.6);
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.4);
        }

        .footer-subscribe button svg {
            width: 14px;
            height: 14px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
        }

        .footer-bottom {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-bottom p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            margin: 0;
            text-shadow: none;
        }

        .social-icons {
            display: flex;
            gap: 15px;
        }

        .social-icons a {
            color: rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            color: #fff;
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.8));
            transform: translateY(-2px);
        }

        .social-icons svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">

    <div class="content-wrapper">

        <div class="dashboard-container">
            
            <aside class="dashboard-sidebar">
                <div class="user-summary">
                    <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
                    <h3><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h3>
                    <p><?= htmlspecialchars($currentUser['email']) ?></p>
                </div>
                <nav class="dashboard-nav">
                    <a href="profile.php">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            Account Details
                        </div>
                    </a>
                    <a href="orders.php" class="active">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            Order History
                        </div>
                        <?php if ($newOrdersCount > 0): ?>
                            <span style="background: rgba(255, 0, 255, 0.8); color: white; font-size: 10px; font-weight: bold; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 5px rgba(255, 0, 255, 0.5); margin-left: auto;"><?= $newOrdersCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="addresses.php">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            Saved Addresses
                        </div>
                    </a>
                    <a href="profile.php?logout=1" style="color: #ff4d4d;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                            Log Out
                        </div>
                    </a>
                </nav>
            </aside>

            <main class="dashboard-content">
                <div class="dashboard-header">
                    <div class="dashboard-header-text">
                        <h2>Order History</h2>
                        <p>Track, review, and manage your recent purchases.</p>
                    </div>
                </div>

                <div class="orders-list">
                    <?php if (empty($mockOrders)): ?>
                        <div class="empty-state">
                            <h3>No Orders Yet</h3>
                            <p>You haven't placed any orders in the past 6 months.</p>
                            <a href="products.php" class="btn-solid" style="display: inline-block;">Start Shopping</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($mockOrders as $order): ?>
                            <div class="order-card">
                                <div class="order-top">
                                    <div class="order-meta">
                                        <h4>Order <?= htmlspecialchars($order['id']) ?></h4>
                                        <p>Placed on <?= htmlspecialchars($order['date']) ?></p>
                                    </div>
                                    <div class="order-status" style="color: <?= htmlspecialchars($order['status_color']) ?>;">
                                        <?= htmlspecialchars($order['status']) ?>
                                    </div>
                                </div>

                                <div class="order-item-list">
                                    <?php foreach ($order['items'] as $item): ?>
                                        <div class="order-item">
                                            <img src="<?= htmlspecialchars($item['img']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="item-img">
                                            <div class="item-details">
                                                <h5><?= htmlspecialchars($item['name']) ?></h5>
                                                <p>Qty: <?= htmlspecialchars($item['qty']) ?></p>
                                            </div>
                                            <div class="item-price"><?= htmlspecialchars($item['price']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="order-bottom">
                                    <div class="order-total">
                                        Total: <?= htmlspecialchars($order['total']) ?>
                                    </div>
                                    <div class="order-actions">
                                        <a href="product.php?item=<?= urlencode($order['items'][0]['name']) ?>" class="btn-outline">View Details</a>
                                        <?php if ($order['status'] === 'Processing' || $order['status'] === 'Shipped'): ?>
                                            <a href="track.php?order_id=<?= urlencode($order['id']) ?>" class="btn-solid">Track Order</a>
                                        <?php else: ?>
                                            <a href="cart.php?action=add&reorder=1&item=<?= urlencode($order['items'][0]['name']) ?>" class="btn-solid">Reorder</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById('auto-search');
            
            const phrases = [
                "Search 'Vijaya Extract'...", 
                "Search 'Sleep Drops'...", 
                "Search 'Pain Relief'...", 
                "Search 'Full Spectrum'..."
            ];
            
            let phraseIndex = 0;
            let charIndex = 0;
            let isDeleting = false;

            function typeEffect() {
                const currentPhrase = phrases[phraseIndex];
                
                if (isDeleting) {
                    searchInput.placeholder = currentPhrase.substring(0, charIndex - 1);
                    charIndex--;
                } else {
                    searchInput.placeholder = currentPhrase.substring(0, charIndex + 1);
                    charIndex++;
                }

                let typeSpeed = isDeleting ? 40 : 80;

                if (!isDeleting && charIndex === currentPhrase.length) {
                    typeSpeed = 2000; 
                    isDeleting = true;
                } 
                else if (isDeleting && charIndex === 0) {
                    isDeleting = false;
                    phraseIndex = (phraseIndex + 1) % phrases.length;
                    typeSpeed = 500; 
                }

                setTimeout(typeEffect, typeSpeed);
            }

            setTimeout(typeEffect, 1000);
        });
    </script>
    <script src="assets/js/responsive.js"></script>
</body>
</html>