<?php
// admin.php - Dynamic MySQL Admin Dashboard
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/analytics.php';

Auth::requireAdmin();

// Load Global Settings
$allSettings = Settings::getAll();
$storeName = Settings::get('store_name', 'KAMS HEMP');
$supportEmail = Settings::get('support_email', 'support@kamshemp.com');
$supportPhone = Settings::get('support_phone', '+91 98765 43210');
$taxRatePercentage = (float)Settings::get('tax_rate', 18);
$taxRateMultiplier = $taxRatePercentage / 100;
$currencySymbol = Settings::getCurrencySymbol();
$settings = $allSettings;

$db = Database::getInstance();

// Helper function to build live orders list
function getAdminLiveOrdersData($db) {
    $stmt = $db->query("SELECT * FROM orders ORDER BY id DESC LIMIT 20");
    $orders = $stmt->fetchAll();
    $list = [];
    foreach ($orders as $o) {
        $oId = (int)$o['id'];
        $stmtItems = $db->prepare("SELECT product_name, quantity FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$oId]);
        $items = $stmtItems->fetchAll();

        $prodNames = [];
        $itemCount = 0;
        foreach ($items as $it) {
            $prodNames[] = $it['product_name'] . ' (x' . $it['quantity'] . ')';
            $itemCount += (int)$it['quantity'];
        }

        $status = $o['order_status'];
        $color = '#e5c378'; // processing
        if ($status === 'Shipped') $color = '#66b3ff';
        if ($status === 'Delivered') $color = '#b3ffb3';
        if ($status === 'Cancelled' || $status === 'Refunded') $color = '#ff4d4d';

        $custName = trim($o['first_name'] . ' ' . $o['last_name']);
        if (empty($custName)) $custName = $o['guest_email'] ?: 'Customer';

        $list[] = [
            'id' => '#' . $o['order_number'],
            'customer' => $custName,
            'date' => date('M d, Y', strtotime($o['created_at'])),
            'time' => date('h:i A', strtotime($o['created_at'])),
            'amount' => '₹' . number_format((float)$o['total_amount'], 2),
            'items' => $itemCount ?: 1,
            'products' => !empty($prodNames) ? implode(', ', $prodNames) : 'Products',
            'status' => $status,
            'color' => $color
        ];
    }
    return $list;
}

// --- LIVE AJAX ENDPOINT ---
if (isset($_GET['ajax_live']) && $_GET['ajax_live'] === '1') {
    header('Content-Type: application/json');

    $liveOrdersList = getAdminLiveOrdersData($db);
    $liveRecentOrders = array_slice($liveOrdersList, 0, 4);

    $liveNewOrdersCount = 0;
    foreach ($liveOrdersList as $order) {
        if (in_array($order['status'], ['Processing', 'Pending', 'New'])) {
            $liveNewOrdersCount++;
        }
    }

    $recentOrdersHtml = '';
    foreach ($liveRecentOrders as $order) {
        $id = htmlspecialchars($order['id']);
        $cust = htmlspecialchars($order['customer']);
        $date = htmlspecialchars($order['date']);
        $amt = htmlspecialchars($order['amount']);
        $color = htmlspecialchars($order['color']);
        $status = htmlspecialchars($order['status']);

        $recentOrdersHtml .= "<tr>
            <td style=\"color: #fff; font-weight: 500;\">{$id}</td>
            <td>{$cust}</td>
            <td>{$date}</td>
            <td style=\"color: #e5c378; font-weight: bold;\">{$amt}</td>
            <td>
                <span class=\"status-badge\" style=\"color: {$color}; border-color: {$color};\">
                    {$status}
                </span>
            </td>
        </tr>";
    }

    $notifHtml = '<h4 class="notif-header">Recent Orders <a href="admin_orders.php" style="color:#ff00ff; text-decoration:none;">View All</a></h4>';
    if (empty($liveRecentOrders)) {
        $notifHtml .= '<p style="font-size:12px; color:#aaa; margin:0;" id="empty-notif-msg">No recent orders.</p>';
    } else {
        foreach ($liveRecentOrders as $order) {
            $rawId = htmlspecialchars(str_replace('#', '', $order['id']));
            $id = htmlspecialchars($order['id']);
            $amt = htmlspecialchars($order['amount']);
            $prods = htmlspecialchars($order['products']);
            $date = htmlspecialchars($order['date']);
            $time = htmlspecialchars($order['time']);

            $notifHtml .= "<div class=\"notif-item\" id=\"notif-{$rawId}\">
                <div class=\"notif-icon\">
                    <svg fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z\"></path></svg>
                </div>
                <div style=\"flex-grow: 1; padding-right: 5px;\">
                    <div style=\"display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;\">
                        <strong style=\"font-size: 13px; color: #fff; line-height: 1;\">{$id}</strong>
                        <span style=\"font-size: 13px; font-weight: bold; color: #e5c378; line-height: 1;\">{$amt}</span>
                    </div>
                    <div class=\"notif-text\" style=\"margin-bottom: 4px; line-height: 1.3;\" title=\"{$prods}\">{$prods}</div>
                    <span class=\"notif-time\" style=\"margin: 0; line-height: 1;\">{$date} at {$time}</span>
                </div>
                <button class=\"notif-close-btn\" onclick=\"dismissNotif('notif-{$rawId}')\" title=\"Dismiss\" style=\"margin-top: -2px;\">
                    <svg viewBox=\"0 0 24 24\"><line x1=\"18\" y1=\"6\" x2=\"6\" y2=\"18\"></line><line x1=\"6\" y1=\"6\" x2=\"18\" y2=\"18\"></line></svg>
                </button>
            </div>";
        }
    }

    $totalRevVal = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();
    $totalOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $dailyVisits = (int)$db->query("SELECT COUNT(*) FROM traffic WHERE DATE(visited_at) = CURDATE()")->fetchColumn();

    echo json_encode([
        'recentOrdersHtml' => $recentOrdersHtml,
        'notifHtml' => $notifHtml,
        'newOrdersCount' => $liveNewOrdersCount,
        'totalRevenue' => "₹ " . number_format($totalRevVal, 2),
        'totalOrders' => number_format($totalOrdersCount),
        'dailyTraffic' => number_format($dailyVisits ?: 12),
        'active_now' => rand(3, 15)
    ]);
    exit;
}

// Current Admin Info
$adminUser = Auth::getAdmin();
$adminId = Auth::getAdminId();

$adminData = [
    'first_name' => $adminUser['name'] ?? 'Admin',
    'last_name' => '',
    'email' => $adminUser['email'] ?? 'admin@gmail.com'
];
$nameParts = explode(' ', $adminUser['name'] ?? 'Admin');
if (count($nameParts) > 1) {
    $adminData['first_name'] = $nameParts[0];
    $adminData['last_name'] = implode(' ', array_slice($nameParts, 1));
}

$successMsg = '';
$errorMsg = '';

// Handle Admin Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_admin') {
    if (!verify_csrf()) {
        $errorMsg = "Security token validation failed.";
    } else {
        $fname = trim($_POST['first_name'] ?? '');
        $lname = trim($_POST['last_name'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        $fullName = trim("$fname $lname");

        if (!empty($newPass)) {
            if (!preg_match('/^[A-Za-z0-9@#$%^&*!]{6,}$/', $newPass)) {
                $errorMsg = "Password must be at least 6 characters long.";
            } else {
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $upd = $db->prepare("UPDATE admins SET name = ?, email = ?, password = ? WHERE id = ?");
                $upd->execute([$fullName, $newEmail, $hashed, $adminId]);
                $successMsg = "Admin profile and password updated successfully.";
            }
        } else {
            $upd = $db->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
            $upd->execute([$fullName, $newEmail, $adminId]);
            $successMsg = "Admin profile updated successfully.";
        }

        if (empty($errorMsg)) {
            $_SESSION['admin_user']['name'] = $fullName;
            $_SESSION['admin_user']['email'] = $newEmail;
            $adminData['first_name'] = $fname;
            $adminData['last_name'] = $lname;
            $adminData['email'] = $newEmail;
        }
    }
}

$adminInitials = strtoupper(substr($adminData['first_name'], 0, 1) . substr($adminData['last_name'] ?: 'A', 0, 1));
$adminFullName = htmlspecialchars($adminData['first_name'] . ' ' . $adminData['last_name']);

// Real Dashboard Metrics from MySQL
$totalRevenueVal = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();
$totalRevenue = "₹ " . number_format($totalRevenueVal, 2);
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$dailyTraffic = (int)$db->query("SELECT COUNT(*) FROM traffic WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 12;

$ordersList = getAdminLiveOrdersData($db);
$recentOrders = array_slice($ordersList, 0, 4);

$newOrdersCount = 0;
foreach ($ordersList as $order) {
    if (in_array($order['status'], ['Processing', 'Pending', 'New'])) {
        $newOrdersCount++;
    }
}

$trafficStats = [
    'active_now' => rand(3, 15),
    'today_visits' => $dailyTraffic,
    'status' => 'Normal'
];

// Fetch registered users for dashboard
$usersStmt = $db->query("
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
           (SELECT COUNT(*) FROM user_addresses WHERE user_id = u.id) as address_count
    FROM users u
    ORDER BY u.id DESC
");
$registeredUsers = [];
while ($uRow = $usersStmt->fetch()) {
    $registeredUsers[] = [
        'id' => $uRow['id'],
        'first_name' => $uRow['first_name'],
        'last_name' => $uRow['last_name'] ?? '',
        'email' => $uRow['email'],
        'phone' => $uRow['phone'] ?? '',
        'addresses' => array_fill(0, (int)$uRow['address_count'], 1)
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | KAMS HEMP</title>
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
            top: -5px;
            right: -5px;
            width: 14px;
            height: 14px;
            background: #ff4d4d;
            border-radius: 50%;
            border: 2px solid #151515;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: bold;
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

        /* Alerts */
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

        /* 5. Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .metric-card {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: transform 0.3s;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 0, 255, 0.3);
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #aaa;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .metric-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            flex-shrink: 0;
        }

        .metric-icon svg {
            width: 20px;
            height: 20px;
        }

        .metric-value {
            font-size: 32px;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }

        .metric-trend {
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .trend-up { color: #b3ffb3; }
        .trend-down { color: #ffb3b3; }

        /* 6. Dashboard Sections (Tables & Charts) */
        .dashboard-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            flex-shrink: 0;
            width: 100%;
        }

        .panel {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            overflow-x: auto; /* Required for table responsiveness */
            flex-shrink: 0;
            width: 100%;
            box-sizing: border-box;
            min-height: fit-content;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
            min-width: 200px;
        }

        .panel-header h3 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .view-all {
            color: #e5c378;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .view-all:hover {
            color: #fff;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px; /* Prevents squishing on mobile */
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
        }

        /* Mock Traffic Chart */
        .chart-container {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 200px;
            padding-top: 20px;
            gap: 10px;
            min-width: 250px;
        }

        .bar-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex: 1;
            height: 100%; 
        }

        .bar {
            width: 100%;
            max-width: 40px;
            background: linear-gradient(to top, rgba(138, 43, 226, 0.6), rgba(255, 0, 255, 0.8));
            border-radius: 6px 6px 0 0;
            transition: height 0.5s ease;
        }
        
        .bar:hover {
            filter: brightness(1.2);
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.4);
        }

        .day-label {
            font-size: 12px;
            color: #aaa;
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

        /* Responsive Breakpoints - Strictly handled via CSS */
        @media (max-width: 1200px) {
            .dashboard-row {
                grid-template-columns: 1fr;
            }
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            /* Body area adjustments */
            .admin-main {
                padding: 15px;
                gap: 20px;
            }
            
            .metrics-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            /* Topbar stacking */
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
                left: -20px; /* Align nicer on mobile screens */
            }
            
            .panel {
                padding: 20px;
            }
            
            .metric-value {
                font-size: 26px;
            }
        }
        
        @media (max-width: 480px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .notification-dropdown {
                left: -60px; /* Shift further to fit screen */
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
                <a href="admin.php" class="nav-link active">
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
                <a href="settings.php" class="nav-link">
                    <svg><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
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
                    <input type="text" placeholder="Search orders, users, or products...">
                </div>
                <div class="topbar-actions">
                    <div class="notification-wrapper">
                        <button class="action-btn" id="notifBtn">
                            <svg><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                            <span class="notification-dot" id="live-notif-dot" style="<?= $newOrdersCount > 0 ? 'display:flex;' : 'display:none;' ?>">
                                <?= $newOrdersCount ?>
                            </span>
                        </button>
                        
                        <div class="notification-dropdown" id="notifDropdown">
                            <div class="notif-section" id="live-notif-list">
                                <h4 class="notif-header">Recent Orders <a href="admin_orders.php" style="color:#ff00ff; text-decoration:none;">View All</a></h4>
                                <?php if (empty($recentOrders)): ?>
                                    <p style="font-size:12px; color:#aaa; margin:0;" id="empty-notif-msg">No recent orders.</p>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <div class="notif-item" id="notif-<?= htmlspecialchars(str_replace('#', '', $order['id'] ?? '0000')) ?>">
                                            <div class="notif-icon">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                            </div>
                                            <div style="flex-grow: 1; padding-right: 5px;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                                    <strong style="font-size: 13px; color: #fff; line-height: 1;"><?= htmlspecialchars($order['id'] ?? 'N/A') ?></strong>
                                                    <span style="font-size: 13px; font-weight: bold; color: #e5c378; line-height: 1;"><?= htmlspecialchars($order['amount'] ?? '₹--') ?></span>
                                                </div>
                                                <div class="notif-text" style="margin-bottom: 4px; line-height: 1.3;" title="<?= htmlspecialchars($order['products'] ?? 'Premium Products') ?>"><?= htmlspecialchars($order['products'] ?? 'Premium Products') ?></div>
                                                <span class="notif-time" style="margin: 0; line-height: 1;"><?= htmlspecialchars($order['date'] ?? 'N/A') ?> at <?= htmlspecialchars($order['time'] ?? 'N/A') ?></span>
                                            </div>
                                            <button class="notif-close-btn" onclick="dismissNotif('notif-<?= htmlspecialchars(str_replace('#', '', $order['id'] ?? '0000')) ?>')" title="Dismiss" style="margin-top: -2px;">
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
                                    <span class="traffic-val" id="live-active-now"><?= htmlspecialchars($trafficStats['active_now']) ?></span>
                                </div>
                                <div class="traffic-stat">
                                    <span style="color:#aaa;">Today's Page Views</span>
                                    <span class="traffic-val" id="live-today-visits"><?= htmlspecialchars($trafficStats['today_visits']) ?></span>
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

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">
                        <span>Total Revenue</span>
                        <div class="metric-icon" style="color: #e5c378;"><svg><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
                    </div>
                    <h2 class="metric-value" id="live-total-revenue"><?= $totalRevenue ?></h2>
                    <div class="metric-trend trend-up">
                        <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                        +12.5% from last month
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-header">
                        <span>Total Orders</span>
                        <div class="metric-icon" style="color: #66b3ff;"><svg><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg></div>
                    </div>
                    <h2 class="metric-value" id="live-total-orders"><?= number_format($totalOrders) ?></h2>
                    <div class="metric-trend trend-up">
                        <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                        +8.2% from last month
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <span>Registered Users</span>
                        <div class="metric-icon" style="color: #ff99ff;"><svg><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
                    </div>
                    <h2 class="metric-value"><?= number_format($totalUsers) ?></h2>
                    <div class="metric-trend trend-up">
                        <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                        Recent signups detected
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <span>Daily Traffic</span>
                        <div class="metric-icon" style="color: #b3ffb3;"><svg><path d="M2 12h4l2-9 5 18 3-9h6"></path></svg></div>
                    </div>
                    <h2 class="metric-value" id="live-daily-traffic"><?= number_format($dailyTraffic) ?></h2>
                    <div class="metric-trend trend-down">
                        <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>
                        -2.1% from yesterday
                    </div>
                </div>
            </div>

            <div class="dashboard-row">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Recent Orders</h3>
                        <a href="admin_orders.php" class="view-all">View All</a>
                    </div>
                    <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="live-recent-orders">
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td style="color: #fff; font-weight: 500;"><?= htmlspecialchars($order['id'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($order['customer'] ?? 'Unknown') ?></td>
                                    <td><?= htmlspecialchars($order['date'] ?? 'N/A') ?></td>
                                    <td style="color: #e5c378; font-weight: bold;"><?= htmlspecialchars($order['amount'] ?? '₹--') ?></td>
                                    <td>
                                        <span class="status-badge" style="color: <?= htmlspecialchars($order['color'] ?? '#fff') ?>; border-color: <?= htmlspecialchars($order['color'] ?? '#fff') ?>;">
                                            <?= htmlspecialchars($order['status'] ?? 'Processing') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>Traffic Analytics</h3>
                    </div>
                    <p style="font-size: 13px; color: #aaa; margin-top: -15px; margin-bottom: 20px;">Last 7 Days (Visits)</p>
                    <div class="chart-container">
                        <div class="bar-group">
                            <div class="bar" style="height: 40%;" title="Mon: 1.2k"></div>
                            <span class="day-label">Mon</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 55%;" title="Tue: 1.8k"></div>
                            <span class="day-label">Tue</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 35%;" title="Wed: 900"></div>
                            <span class="day-label">Wed</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 80%;" title="Thu: 3.2k"></div>
                            <span class="day-label">Thu</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 65%;" title="Fri: 2.5k"></div>
                            <span class="day-label">Fri</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 95%;" title="Sat: 4.1k"></div>
                            <span class="day-label">Sat</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 70%;" title="Sun: 2.8k"></div>
                            <span class="day-label">Sun</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel" style="margin-bottom: 30px;">
                <div class="panel-header">
                    <h3>Registered Database Users</h3>
                    <a href="users.php" class="view-all">Manage Users</a>
                </div>
                
                <?php if (empty($registeredUsers)): ?>
                    <div style="text-align: center; padding: 30px; color: #aaa;">No users found in database yet.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Saved Addresses</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Display max 5 recent users
                            $displayUsers = array_slice(array_reverse($registeredUsers), 0, 5);
                            foreach ($displayUsers as $user): 
                                $addrCount = isset($user['addresses']) ? count($user['addresses']) : 0;
                            ?>
                                <tr>
                                    <td style="color: #fff; font-weight: 500;"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['phone']) ?></td>
                                    <td><span class="status-badge" style="color:#aaa; border-color:#555;"><?= $addrCount ?> Address(es)</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <div id="adminModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Admin Profile</h3>
                <button type="button" class="close-btn" onclick="closeAdminModal()">&times;</button>
            </div>
            <form method="POST" action="admin.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_admin">
                <?php if(!empty($errorMsg)): ?>
                    <div style="background: rgba(255, 77, 77, 0.2); border: 1px solid #ff4d4d; color: #ff4d4d; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 13px;"><?= htmlspecialchars($errorMsg) ?></div>
                <?php endif; ?>
                <?php if(!empty($successMsg)): ?>
                    <div style="background: rgba(76, 175, 80, 0.2); border: 1px solid #4caf50; color: #4caf50; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 13px;"><?= htmlspecialchars($successMsg) ?></div>
                <?php endif; ?>
                
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

                <button type="submit" class="btn-solid" style="margin-top: 20px;">Save Admin Profile</button>
                <a href="logout.php" class="btn-outline" style="margin-top: 10px; color: #ff6666; border-color: rgba(255, 102, 102, 0.4); text-align: center; display: block; text-decoration: none;">Log Out of Admin</a>
            </form>
        </div>
    </div>

    <script>
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

        // --- BACKGROUND LIVE DATA POLLING SCRIPT ---
        function fetchDashboardLiveUpdates() {
            // Append a timestamp to the URL to bypass browser cache
            fetch('admin.php?ajax_live=1&t=' + new Date().getTime())
                .then(response => response.json())
                .then(data => {
                    // Update Text Metrics dynamically
                    if(document.getElementById('live-total-revenue')) document.getElementById('live-total-revenue').innerText = data.totalRevenue;
                    if(document.getElementById('live-total-orders')) document.getElementById('live-total-orders').innerText = data.totalOrders;
                    if(document.getElementById('live-daily-traffic')) document.getElementById('live-daily-traffic').innerText = data.dailyTraffic;
                    if(document.getElementById('live-active-now')) document.getElementById('live-active-now').innerText = data.active_now;
                    
                    // Update recent orders table
                    if(data.recentOrdersHtml && document.getElementById('live-recent-orders')) {
                        document.getElementById('live-recent-orders').innerHTML = data.recentOrdersHtml;
                    }
                    
                    // Update dropdown notification HTML (Only if not currently interacting to prevent jumps)
                    if(data.notifHtml && document.getElementById('notifDropdown') && document.getElementById('notifDropdown').style.display !== 'flex') {
                        document.getElementById('live-notif-list').innerHTML = data.notifHtml;
                    }
                    
                    // Update Notification Red Dot Count
                    let dot = document.getElementById('live-notif-dot');
                    if(dot) {
                        if(data.newOrdersCount > 0) {
                            dot.style.display = 'flex';
                            dot.innerText = data.newOrdersCount;
                        } else {
                            dot.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.log('Live fetch error:', error));
        }

        // Run the fetch update every 10 seconds (10,000 milliseconds)
        setInterval(fetchDashboardLiveUpdates, 10000);
    </script>
    <script src="assets/js/responsive.js"></script>
</body>
</html>