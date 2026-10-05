<?php
// admin_orders.php
session_start();

// Set default timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/referral.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/settings.php';

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

// Status Color Mapping
$statusColors = [
    'Processing' => '#e5c378',
    'Shipped'    => '#66b3ff',
    'Delivered'  => '#b3ffb3',
    'Cancelled'  => '#ffb3b3',
    'Refunded'   => '#aaa'
];

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    
    // --- Admin Profile Update ---
    if ($_POST['action'] === 'update_admin') {
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
                    $successMsg = "Admin profile and password updated successfully.";
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
    
    // --- Update Order Status ---
    elseif ($_POST['action'] === 'update_status') {
        $orderId = trim($_POST['order_id'] ?? '');
        $newStatus = ucfirst(strtolower(trim($_POST['new_status'] ?? 'Processing')));
        
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? OR id = ? LIMIT 1");
        $stmt->execute([$orderId, $orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $errorMsg = "Order not found.";
        } else {
            $oldStatus = ucfirst(strtolower($order['order_status']));
            if ($oldStatus === $newStatus) {
                $errorMsg = "Order is already marked as '{$newStatus}'. No changes were made.";
            } else {
                // Check if cancelling or refunding (and was not already cancelled/refunded)
                if (in_array($newStatus, ['Cancelled', 'Refunded']) && !in_array($oldStatus, ['Cancelled', 'Refunded'])) {
                    // Restore inventory stock for order items
                    $itemsStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                    $itemsStmt->execute([$order['id']]);
                    $orderItems = $itemsStmt->fetchAll();
                    foreach ($orderItems as $item) {
                        if (!empty($item['product_id'])) {
                            Inventory::restoreStock(
                                (int)$item['product_id'],
                                (int)$item['quantity'],
                                (int)$order['id'],
                                "Restored via Order #{$order['order_number']} status change to {$newStatus}"
                            );
                        }
                    }
                    
                    // Refund wallet balance if wallet was used
                    if ((float)$order['wallet_amount_used'] > 0 && !empty($order['user_id'])) {
                        ReferralSystem::creditWallet(
                            (int)$order['user_id'],
                            (float)$order['wallet_amount_used'],
                            "Refund for cancelled order #{$order['order_number']}",
                            (int)$order['id']
                        );
                    }
                }
                
                $upStmt = $db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
                $upStmt->execute([$newStatus, $order['id']]);
                
                header("Location: admin_orders.php?success=" . urlencode("Order status updated to {$newStatus} successfully."));
                exit;
            }
        }
    }
    
    // --- Cancel Order ---
    elseif ($_POST['action'] === 'cancel_order') {
        $orderId = trim($_POST['order_id'] ?? '');
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? OR id = ? LIMIT 1");
        $stmt->execute([$orderId, $orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $errorMsg = "Order not found.";
        } else {
            $oldStatus = ucfirst(strtolower($order['order_status']));
            if ($oldStatus === 'Cancelled') {
                $errorMsg = "Order is already cancelled.";
            } else {
                if ($oldStatus !== 'Refunded') {
                    // Restore inventory
                    $itemsStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                    $itemsStmt->execute([$order['id']]);
                    $orderItems = $itemsStmt->fetchAll();
                    foreach ($orderItems as $item) {
                        if (!empty($item['product_id'])) {
                            Inventory::restoreStock(
                                (int)$item['product_id'],
                                (int)$item['quantity'],
                                (int)$order['id'],
                                "Restored via Order #{$order['order_number']} cancellation"
                            );
                        }
                    }
                    
                    // Refund wallet if used
                    if ((float)$order['wallet_amount_used'] > 0 && !empty($order['user_id'])) {
                        ReferralSystem::creditWallet(
                            (int)$order['user_id'],
                            (float)$order['wallet_amount_used'],
                            "Refund for cancelled order #{$order['order_number']}",
                            (int)$order['id']
                        );
                    }
                }
                
                $upStmt = $db->prepare("UPDATE orders SET order_status = 'Cancelled', updated_at = NOW() WHERE id = ?");
                $upStmt->execute([$order['id']]);
                
                header("Location: admin_orders.php?success=" . urlencode("Order #{$order['order_number']} cancelled successfully."));
                exit;
            }
        }
    }
}

// Fetch all orders from MySQL
$ordersStmt = $db->query("SELECT * FROM orders ORDER BY id DESC");
$rawOrders = $ordersStmt->fetchAll();

// Fetch and group order items
$itemsStmt = $db->query("SELECT * FROM order_items ORDER BY id ASC");
$allItems = $itemsStmt->fetchAll();
$itemsByOrderId = [];
foreach ($allItems as $item) {
    $itemsByOrderId[$item['order_id']][] = $item;
}

$ordersList = [];
$newOrdersCount = 0;

foreach ($rawOrders as $o) {
    $oid = $o['id'];
    $orderItems = $itemsByOrderId[$oid] ?? [];
    $itemCount = 0;
    $productNames = [];
    foreach ($orderItems as $it) {
        $itemCount += (int)$it['quantity'];
        $productNames[] = $it['product_name'] . ' (x' . $it['quantity'] . ')';
    }
    
    $statusKey = ucfirst(strtolower($o['order_status']));
    if ($statusKey === 'Processing') {
        $newOrdersCount++;
    }
    
    try {
        $dt = new DateTime($o['created_at']);
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $dateStr = $dt->format('M d, Y');
        $timeStr = $dt->format('h:i A') . ' IST';
    } catch (Exception $e) {
        $dateStr = date('M d, Y');
        $timeStr = date('h:i A') . ' IST';
    }
    
    $customerName = trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''));
    if (empty($customerName)) {
        $customerName = ($o['email'] ?? '') ?: 'Guest Customer';
    }
    
    $totalAmt = (float)($o['total_amount'] ?? 0);
    $ordersList[] = [
        'db_id' => $o['id'],
        'id' => $o['order_number'],
        'customer' => $customerName,
        'email' => $o['email'] ?? '',
        'phone' => $o['phone'] ?? '',
        'shipping_address' => $o['shipping_address'] ?? '',
        'date' => $dateStr,
        'time' => $timeStr,
        'amount' => '₹' . number_format($totalAmt, 2),
        'raw_amount' => $totalAmt,
        'items' => $itemCount > 0 ? $itemCount : 1,
        'products' => !empty($productNames) ? implode(', ', $productNames) : 'Various Items',
        'status' => $statusKey,
        'color' => $statusColors[$statusKey] ?? '#ccc',
        'payment_method' => $o['payment_method'] ?? 'COD',
        'payment_status' => $o['payment_status'] ?? 'Pending',
        'transaction_id' => $o['transaction_id'] ?? '',
        'wallet_amount_used' => (float)($o['wallet_deduction'] ?? 0),
        'discount_amount' => (float)($o['discount_amount'] ?? 0)
    ];
}

// Prepare recent orders for notification dropdown
$recentOrders = array_slice($ordersList, 0, 4);

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
    <title>Order Management | Admin | KAMS HEMP</title>
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

        .fullscreen-bg, .bg-overlay {
            display: none !important;
        }

        .admin-layout {
            display: flex;
            width: 100vw;
            height: 100vh;
            position: relative;
            z-index: 1;
        }

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
            flex-shrink: 0;
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
            flex-shrink: 0;
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
            position: relative;
            z-index: 100;
        }

        .search-bar {
            position: relative;
            width: 100%;
            max-width: 300px;
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
            flex-shrink: 0;
        }

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
            padding: 0;
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
            max-width: 90vw;
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

        .panel {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            flex-grow: 1;
            flex-shrink: 0;
            min-height: fit-content;
            overflow-x: auto; 
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
            flex-wrap: wrap; 
            gap: 15px;
            min-width: 200px;
        }

        .panel-header h3 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .filters-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-select {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            cursor: pointer;
        }
        .filter-select option {
            background: #151515;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px; 
        }

        th {
            text-align: left;
            padding: 12px 10px;
            font-size: 12px;
            text-transform: uppercase;
            color: #aaa;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            white-space: nowrap;
        }

        td {
            padding: 15px 10px;
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            white-space: nowrap;
        }

        tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid;
            display: inline-block;
            text-align: center;
        }

        .action-icons {
            display: flex;
            gap: 10px;
        }

        .icon-btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 6px;
            padding: 6px;
            color: #ccc;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-btn:hover {
            color: #fff;
            border-color: rgba(255,0,255,0.5);
            background: rgba(255,0,255,0.1);
        }
        
        .icon-btn.delete:hover {
            border-color: rgba(255,0,0,0.5);
            background: rgba(255,0,0,0.1);
            color: #ffb3b3;
        }

        .icon-btn svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-content {
            background: rgba(15, 15, 15, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            width: 100%;
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

        .form-group {
            margin-bottom: 15px;
            width: 100%;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            color: #ccc;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            padding: 10px 12px;
            color: #fff;
            font-size: 14px;
            font-family: inherit;
            box-sizing: border-box;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 0, 255, 0.5);
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.2);
        }

        .btn-solid {
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
            width: 100%;
            box-sizing: border-box;
            display: block;
            text-align: center;
        }

        .btn-solid:hover {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.8) 0%, rgba(138, 43, 226, 0.8) 100%);
            box-shadow: 0 0 20px rgba(255, 0, 255, 0.4);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
        }

        .btn-outline:hover {
            border-color: rgba(255, 0, 255, 0.6);
            background: rgba(255, 0, 255, 0.1);
        }

        @media (max-width: 768px) {
            .admin-main {
                padding: 15px;
                gap: 20px;
            }
            
            .topbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            .search-bar {
                max-width: 100%;
            }
            .topbar-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .notification-dropdown {
                right: auto;
                left: -20px;
            }
            
            .panel {
                padding: 20px;
            }

            .filters-container {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .filter-select {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .notification-dropdown {
                left: -60px;
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
                <a href="admin_orders.php" class="nav-link active">
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
                <a href="settings.php" class="nav-link">
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
                    <input type="text" id="searchInput" placeholder="Search orders by ID, Customer..." onkeyup="filterOrders()">
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
                                                <div class="notif-text" style="margin-bottom: 4px; line-height: 1.3;" title="<?= htmlspecialchars($order['products']) ?>"><?= htmlspecialchars($order['products']) ?></div>
                                                <span class="notif-time" style="margin: 0; line-height: 1;"><?= htmlspecialchars($order['date']) ?> at <?= htmlspecialchars($order['time']) ?></span>
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

            <?php if(!empty($errorMsg)): ?>
                <div class="alert-msg alert-error"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <?php if(!empty($successMsg)): ?>
                <div class="alert-msg alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <h3>Order Management</h3>
                    <div class="filters-container">
                        <select class="filter-select" id="statusFilter" onchange="filterOrders()">
                            <option value="">All Statuses</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="refunded">Refunded</option>
                        </select>
                        <select class="filter-select" id="dateFilter" onchange="filterOrders()">
                            <option value="30">Last 30 Days</option>
                            <option value="7">Last 7 Days</option>
                            <option value="90">Last 3 Months</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Payment</th>
                            <th>Date & Time (IST)</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordersList as $order): ?>
                            <tr class="order-row" data-status="<?= strtolower($order['status']) ?>" data-date="<?= date('Y-m-d', strtotime($order['date'])) ?>">
                                <td class="searchable" style="color: #fff; font-weight: 500;">
                                    <div><?= $order['id'] ?></div>
                                    <?php if (!empty($order['transaction_id'])): ?>
                                        <div style="font-size: 10px; color: #888; font-family: monospace;"><?= htmlspecialchars($order['transaction_id']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="searchable"><?= htmlspecialchars($order['customer']) ?></td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <span style="font-size: 12px; font-weight: 700; color: <?= strtoupper($order['payment_method']) === 'UPI' ? '#00ffcc' : '#e5c378' ?>;">
                                            <?= htmlspecialchars($order['payment_method']) ?>
                                        </span>
                                        <span style="font-size: 11px; color: <?= strtolower($order['payment_status']) === 'paid' ? '#4ade80' : '#f59e0b' ?>;">
                                            ● <?= htmlspecialchars($order['payment_status']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($order['date']) ?></div>
                                    <div style="font-size: 11px; color: #aaa; margin-top: 2px;"><?= htmlspecialchars($order['time']) ?></div>
                                </td>
                                <td style="color: #aaa;"><?= $order['items'] ?> item(s)</td>
                                <td style="color: #e5c378; font-weight: bold;"><?= $order['amount'] ?></td>
                                <td>
                                    <span class="status-badge" style="color: <?= $order['color'] ?>; border-color: <?= $order['color'] ?>;">
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-icons">
                                        <button class="icon-btn" title="View Order" onclick='viewOrder(<?= json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <svg><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </button>
                                        <button class="icon-btn" title="Update Status" onclick='openUpdateStatusModal("<?= $order['id'] ?>", "<?= htmlspecialchars($order['status']) ?>")'>
                                            <svg><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </button>
                                        <button class="icon-btn delete" title="Cancel Order" onclick='confirmCancelModal("<?= $order['id'] ?>")'>
                                            <svg><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
            <form method="POST" action="admin_orders.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_admin">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-input" value="<?= htmlspecialchars($adminData['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-input" value="<?= htmlspecialchars($adminData['last_name']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($adminData['email']) ?>" required>
                </div>

                <div class="form-group" style="margin-top: 25px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                    <label>Change Password (Optional)</label>
                    <input type="password" name="new_password" class="form-input" placeholder="Min 8 chars (letters & numbers)" pattern="^[A-Za-z0-9]{8,}$" title="At least 8 characters, letters and numbers only">
                </div>

                <button type="submit" class="btn-solid" style="margin-top: 20px; width:100%; justify-content:center;">Save Admin Profile</button>
                <a href="logout.php" class="btn-outline" style="margin-top: 10px; color: #ff6666; border-color: rgba(255, 102, 102, 0.4); text-align: center; display: block; text-decoration: none;">Log Out of Admin</a>
            </form>
        </div>
    </div>

    <div id="viewOrderModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Order Details</h3>
                <button type="button" class="close-btn" onclick="closeViewOrderModal()">&times;</button>
            </div>
            <div id="viewOrderDetails" style="font-size: 14px; line-height: 1.6; color: #ccc;">
            </div>
        </div>
    </div>

    <div id="updateStatusModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Update Order Status</h3>
                <button type="button" class="close-btn" onclick="closeUpdateStatusModal()">&times;</button>
            </div>
            <form method="POST" action="admin_orders.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="updateOrderId" value="">
                
                <div class="form-group">
                    <label>Order ID</label>
                    <input type="text" id="displayOrderId" class="form-input" disabled style="opacity:0.7;">
                </div>

                <div class="form-group">
                    <label>New Status</label>
                    <select name="new_status" id="newStatusSelect" class="form-input" required>
                        <option value="Processing">Processing</option>
                        <option value="Shipped">Shipped</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Refunded">Refunded</option>
                    </select>
                </div>

                <button type="submit" class="btn-solid" style="margin-top: 20px; width:100%; justify-content:center;">Save Status</button>
            </form>
        </div>
    </div>

    <div id="cancelConfirmModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div style="color: #ffb3b3; margin-bottom: 20px;">
                <svg style="width:64px; height:64px; stroke:currentColor; fill:none; stroke-width:2;" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            </div>
            <h3 style="margin:0 0 10px 0; font-size:20px; letter-spacing:1px; text-transform:uppercase;">Cancel Order</h3>
            <p style="color:#aaa; font-size:14px; margin:0 0 25px 0; line-height:1.5;">
                Are you sure you want to cancel order <strong id="cancelOrderName" style="color:#fff;"></strong>? This will notify the customer.
            </p>
            <div style="display:flex; gap:15px; justify-content:center;">
                <button class="btn-outline" style="width:100%; border-color:rgba(255,255,255,0.2); color:#ccc;" onclick="closeCancelModal()">Go Back</button>
                <button class="btn-solid" style="width:100%; background:rgba(255,0,0,0.2); border-color:rgba(255,0,0,0.5); color:#ffb3b3; justify-content:center;" onclick="executeCancel()">Yes, Cancel It</button>
            </div>
        </div>
    </div>

    <script>
        // Anchored to Indian Standard Time (IST)
        const currentContextDate = new Date(new Date().toLocaleString("en-US", { timeZone: "Asia/Kolkata" }));

        // Filtering Logic
        function filterOrders() {
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const dateFilter = document.getElementById('dateFilter').value;
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.order-row');

            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowDate = new Date(row.getAttribute('data-date'));
                
                // Search match
                const textContent = row.textContent.toLowerCase();
                const searchMatch = searchInput === '' || textContent.includes(searchInput);

                // Status match
                const statusMatch = (statusFilter === '' || rowStatus === statusFilter);
                
                // Date match
                let dateMatch = true;
                if (dateFilter !== 'all' && dateFilter !== '') {
                    const daysDiff = (currentContextDate - rowDate) / (1000 * 60 * 60 * 24);
                    const maxDays = parseInt(dateFilter);
                    
                    if (daysDiff > maxDays) {
                        dateMatch = false;
                    }
                }
                
                if (statusMatch && dateMatch && searchMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Admin Profile Logic
        function openAdminModal() { document.getElementById('adminModal').style.display = 'flex'; }
        function closeAdminModal() { document.getElementById('adminModal').style.display = 'none'; }

        // View Order Modal Logic
        function viewOrder(order) {
            const html = `
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
                    <strong style="color:#fff; font-size:16px;">Order ID:</strong>
                    <span style="color:#e5c378; font-weight: bold;">${order.id}</span>
                </div>
                ${order.transaction_id ? `
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
                    <strong style="color:#fff; font-size:14px;">Transaction ID:</strong>
                    <span style="font-family: monospace; font-size: 13px; color:#00ffcc;">${order.transaction_id}</span>
                </div>` : ''}
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
                    <strong style="color:#fff; font-size:16px;">Customer:</strong>
                    <span>${order.customer}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
                    <strong style="color:#fff; font-size:16px;">Payment:</strong>
                    <span>
                        <strong style="color:${order.payment_method === 'UPI' ? '#00ffcc' : '#e5c378'};">${order.payment_method}</strong>
                        (${order.payment_status})
                    </span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px; align-items:flex-start;">
                    <strong style="color:#fff; font-size:16px;">Date Placed (IST):</strong>
                    <span style="text-align: right;">${order.date}<br><span style="font-size:12px; color:#aaa;">${order.time}</span></span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px; align-items:flex-start;">
                    <strong style="color:#fff; font-size:16px;">Shipping Address:</strong>
                    <span style="text-align: right; max-width: 60%; font-size: 13px; color: #bbb;">${order.shipping_address || 'N/A'}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
                    <strong style="color:#fff; font-size:16px;">Items Count:</strong>
                    <span>${order.items}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
                    <strong style="color:#fff; font-size:16px;">Products:</strong>
                    <span style="text-align: right; max-width: 65%; line-height: 1.4; color: #ccc;">${order.products}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
                    <strong style="color:#fff; font-size:16px;">Total Amount:</strong>
                    <span style="color:#e5c378; font-weight:bold; font-size: 17px;">${order.amount}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top: 25px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <strong style="color:#fff; font-size:16px;">Current Status:</strong>
                    <span class="status-badge" style="color:${order.color}; border-color:${order.color};">${order.status}</span>
                </div>
            `;
            document.getElementById('viewOrderDetails').innerHTML = html;
            document.getElementById('viewOrderModal').style.display = 'flex';
        }
        function closeViewOrderModal() { document.getElementById('viewOrderModal').style.display = 'none'; }

        // Update Status Modal Logic
        function openUpdateStatusModal(id, currentStatus) {
            document.getElementById('updateOrderId').value = id;
            document.getElementById('displayOrderId').value = id;
            document.getElementById('newStatusSelect').value = currentStatus;
            document.getElementById('updateStatusModal').style.display = 'flex';
        }
        function closeUpdateStatusModal() { document.getElementById('updateStatusModal').style.display = 'none'; }

        // Cancel Confirmation Modal Logic
        let orderToCancelId = null;
        function confirmCancelModal(id) {
            orderToCancelId = id;
            document.getElementById('cancelOrderName').innerText = id;
            document.getElementById('cancelConfirmModal').style.display = 'flex';
        }
        function closeCancelModal() {
            document.getElementById('cancelConfirmModal').style.display = 'none';
            orderToCancelId = null;
        }
        function executeCancel() {
            if (orderToCancelId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_orders.php';
                form.innerHTML = '<?= csrf_field() ?><input type="hidden" name="action" value="cancel_order"><input type="hidden" name="order_id" value="'+orderToCancelId+'">';
                document.body.appendChild(form);
                form.submit();
            }
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

        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target === document.getElementById('adminModal')) closeAdminModal();
            if (event.target === document.getElementById('viewOrderModal')) closeViewOrderModal();
            if (event.target === document.getElementById('updateStatusModal')) closeUpdateStatusModal();
            if (event.target === document.getElementById('cancelConfirmModal')) closeCancelModal();
            
            if (notifBtn && !notifBtn.contains(event.target) && notifDropdown && !notifDropdown.contains(event.target)) {
                notifDropdown.style.display = 'none';
            }
        }

        // Alert Timeout
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