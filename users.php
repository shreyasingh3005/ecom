<?php
// users.php
session_start();

// Set default timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/referral.php';

Auth::requireAdmin();

$db = Database::getInstance();
$adminUser = Auth::admin();

$storeName = Settings::get('store_name', 'KAMS HEMP');
$supportEmail = Settings::get('support_email', 'support@kamshemp.com');
$supportPhone = Settings::get('support_phone', '+91 98765 43210');
$taxRatePercentage = (float)Settings::get('tax_percentage', 18);
$taxRateMultiplier = $taxRatePercentage / 100;
$referralDiscountPercent = (float)Settings::get('referral_discount_percent', 15);

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

// Handle Form Actions (Admin Profile + User CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    
    // --- Admin Update ---
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

    // --- Add User ---
    elseif ($_POST['action'] === 'add_user') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($firstName) || empty($password)) {
            $errorMsg = "First name, email and password are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Please enter a valid email address.";
        } else {
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $errorMsg = "A user with this email address already exists.";
            } else {
                $refCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $firstName), 0, 4)) . rand(100, 999);
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password, referral_code, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $ins->execute([$firstName, $lastName, $email, $phone, $passHash, $refCode]);
                $successMsg = "User added successfully.";
            }
        }
    }

    // --- Edit User ---
    elseif ($_POST['action'] === 'edit_user') {
        $origEmail = trim($_POST['original_email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $errorMsg = "Password must be at least 8 characters long.";
            } else {
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $up = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, password = ? WHERE email = ?");
                $up->execute([$firstName, $lastName, $phone, $passHash, $origEmail]);
                $successMsg = "User updated successfully.";
            }
        } else {
            $up = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE email = ?");
            $up->execute([$firstName, $lastName, $phone, $origEmail]);
            $successMsg = "User updated successfully.";
        }
    }

    // --- Delete User ---
    elseif ($_POST['action'] === 'delete_user') {
        $delEmail = trim($_POST['delete_email'] ?? '');
        $del = $db->prepare("DELETE FROM users WHERE email = ?");
        $del->execute([$delEmail]);
        $successMsg = "User deleted successfully.";
    }
}

// Fetch all registered users from MySQL
$usersStmt = $db->query("SELECT * FROM users ORDER BY id DESC");
$dbUsers = $usersStmt->fetchAll();

// Fetch addresses grouped by user_id
$addrStmt = $db->query("SELECT * FROM user_addresses ORDER BY id ASC");
$allAddresses = $addrStmt->fetchAll();
$addrsByUserId = [];
foreach ($allAddresses as $addr) {
    $line = trim(($addr['street'] ?? '') . ', ' . ($addr['city'] ?? '') . ', ' . ($addr['state'] ?? '') . ' - ' . ($addr['zip'] ?? ''));
    $addrsByUserId[$addr['user_id']][] = $line;
}

// Fetch referral counts per user
$refStmt = $db->query("SELECT referrer_id, COUNT(*) as cnt FROM referrals WHERE status = 'completed' GROUP BY referrer_id");
$refCounts = [];
while ($row = $refStmt->fetch()) {
    $refCounts[$row['referrer_id']] = (int)$row['cnt'];
}

// Status Color Mapping
$statusColors = [
    'Processing' => '#e5c378',
    'Shipped'    => '#66b3ff',
    'Delivered'  => '#b3ffb3',
    'Cancelled'  => '#ffb3b3',
    'Refunded'   => '#aaa'
];

// Fetch orders for history and notification dropdown
$ordersStmt = $db->query("SELECT * FROM orders ORDER BY id DESC");
$allOrders = $ordersStmt->fetchAll();
$ordersByUserId = [];
$ordersByEmail = [];
$newOrdersCount = 0;
$recentOrders = [];

foreach ($allOrders as $ord) {
    $statusKey = ucfirst(strtolower($ord['order_status']));
    if (in_array($statusKey, ['Processing', 'New', 'Pending'])) {
        $newOrdersCount++;
    }
    
    $dt = date('M d, Y', strtotime($ord['created_at']));
    $cName = trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? ''));
    $cEmail = $ord['email'] ?? '';
    $fmtOrder = [
        'id' => $ord['order_number'],
        'customer' => $cName ?: ($cEmail ?: 'Customer'),
        'date' => $dt,
        'amount' => '₹' . number_format((float)($ord['total_amount'] ?? 0), 2),
        'items' => 1,
        'status' => $statusKey,
        'color' => $statusColors[$statusKey] ?? '#ccc'
    ];
    
    if (count($recentOrders) < 4) {
        $recentOrders[] = $fmtOrder;
    }
    
    if (!empty($ord['user_id'])) {
        $ordersByUserId[$ord['user_id']][] = $fmtOrder;
    }
    if (!empty($cEmail)) {
        $ordersByEmail[strtolower($cEmail)][] = $fmtOrder;
    }
}

// Build registered users display list
$registeredUsers = [];
$usersWithAddresses = 0;
$totalReferrals = 0;

foreach ($dbUsers as $u) {
    $uid = $u['id'];
    $uAddrs = $addrsByUserId[$uid] ?? [];
    if (!empty($uAddrs)) {
        $usersWithAddresses++;
    }
    $uRefs = $refCounts[$uid] ?? 0;
    $totalReferrals += $uRefs;
    
    $uHistory = $ordersByUserId[$uid] ?? ($ordersByEmail[strtolower($u['email'])] ?? []);
    
    $registeredUsers[] = [
        'id' => $u['id'],
        'first_name' => $u['first_name'],
        'last_name' => $u['last_name'] ?? '',
        'email' => $u['email'],
        'phone' => $u['phone'] ?? '',
        'wallet_balance' => (float)$u['wallet_balance'],
        'addresses' => $uAddrs,
        'referral_code' => $u['referral_code'] ?: 'N/A',
        'referral_count' => $uRefs,
        'order_history' => $uHistory,
        'created_at' => $u['created_at']
    ];
}

$totalUsers = count($registeredUsers);
$conversionRate = $totalUsers > 0 ? round(($usersWithAddresses / $totalUsers) * 100) : 0;

// New signups in last 7 days
$recentSignupsStmt = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$recentSignupsCount = (int)$recentSignupsStmt->fetchColumn();

// Sort for Referral Leaderboard
$referralLeaders = $registeredUsers;
usort($referralLeaders, function($a, $b) {
    return $b['referral_count'] <=> $a['referral_count'];
});

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
    <title>Customers & Users | Admin | KAMS HEMP</title>
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

        .admin-layout {
            display: flex;
            width: 100vw;
            height: 100vh;
            position: relative;
            z-index: 1;
            overflow: hidden;
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
            min-width: 0; 
            overflow-y: auto;
            overflow-x: hidden;
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

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            width: 100%;
            flex-shrink: 0;
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

        .trend-success { color: #b3ffb3; }
        .trend-info { color: #66b3ff; }
        .trend-warning { color: #e5c378; }
        .trend-referral { color: #ffb3ff; }

        .panel {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            margin-bottom: 30px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            flex-shrink: 0;
            min-height: fit-content;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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

        .btn-solid {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.6) 0%, rgba(138, 43, 226, 0.6) 100%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-solid:hover {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.8) 0%, rgba(138, 43, 226, 0.8) 100%);
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.4);
            transform: translateY(-2px);
        }

        /* Improved Table Responsiveness */
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px; /* Reduced from 800px to better fit laptops */
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
            vertical-align: middle;
            word-wrap: break-word; /* Allows long text (like emails) to wrap */
            line-height: 1.4;
        }

        tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.4), rgba(138, 43, 226, 0.4));
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
            flex-shrink: 0;
        }

        .user-name {
            font-weight: 600;
            color: #fff;
            display: block;
        }

        .user-id {
            font-size: 12px;
            color: #aaa;
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

        .status-active { color: #b3ffb3; border-color: #b3ffb3; }
        .status-pending { color: #e5c378; border-color: #e5c378; }

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
            color: #fff;
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

            /* Adjust table density slightly for mobile */
            table {
                min-width: 500px;
            }
            td {
                font-size: 13px; 
                padding: 10px 8px;
                white-space: normal; /* Forces text wrapping to prevent crazy horizontal scroll */
            }
            th {
                font-size: 11px;
                padding: 10px 8px;
            }
            .user-avatar-small {
                width: 32px;
                height: 32px;
                font-size: 11px;
            }
            .user-cell {
                gap: 10px;
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
                <a href="users.php" class="nav-link active">
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
                    <input type="text" placeholder="Search users by Name, Email, or Phone...">
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
                                                <div class="notif-text" style="margin-bottom: 4px; line-height: 1.3;" title="<?= htmlspecialchars($order['products'] ?? 'Premium Products') ?>"><?= htmlspecialchars($order['products'] ?? 'Premium Products') ?></div>
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

            <?php if(!empty($errorMsg)): ?>
                <div class="alert-msg alert-error"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <?php if(!empty($successMsg)): ?>
                <div class="alert-msg alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">
                        <span>Total Users</span>
                        <div class="metric-icon" style="color: #66b3ff;"><svg><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
                    </div>
                    <h2 class="metric-value"><?= number_format($totalUsers) ?></h2>
                    <div class="metric-trend trend-info">
                        <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                        Registered accounts
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-header">
                        <span>Active Buyers</span>
                        <div class="metric-icon" style="color: #b3ffb3;"><svg><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
                    </div>
                    <h2 class="metric-value"><?= number_format($usersWithAddresses) ?></h2>
                    <div class="metric-trend trend-success">
                        <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                        Users with saved details
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <span>Conversion Rate</span>
                        <div class="metric-icon" style="color: #e5c378;"><svg><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
                    </div>
                    <h2 class="metric-value"><?= $conversionRate ?>%</h2>
                    <div class="metric-trend trend-warning">
                        Sign-up to Profile setup
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <span>New Signups</span>
                        <div class="metric-icon" style="color: #ff99ff;"><svg><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg></div>
                    </div>
                    <h2 class="metric-value"><?= number_format($recentSignupsCount) ?></h2>
                    <div class="metric-trend trend-info">
                        Joined in the last 7 days
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <span>Total Referrals</span>
                        <div class="metric-icon" style="color: #ffb3ff;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg></div>
                    </div>
                    <h2 class="metric-value"><?= number_format($totalReferrals) ?></h2>
                    <div class="metric-trend trend-referral">
                        Successful code uses
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Referral Tracking Leaderboard</h3>
                    <div style="font-size: 13px; color: #aaa;">Current Store Discount: <?= $referralDiscountPercent ?>%</div>
                </div>
                
                <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Customer (Referrer)</th>
                            <th>Referral Code</th>
                            <th>Successful Shares / Uses</th>
                            <th>Discount Value Generated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $displayLeaders = array_slice($referralLeaders, 0, 5);
                        if (!empty($displayLeaders)):
                            foreach ($displayLeaders as $leader): 
                                $uCount = (int)($leader['referral_count'] ?? 0);
                        ?>
                            <tr>
                                <td style="color: #fff; font-weight: 500;">
                                    <div class="user-cell">
                                        <div class="user-avatar-small" style="width: 30px; height: 30px; font-size: 12px;">
                                            <?= strtoupper(substr($leader['first_name'], 0, 1) . substr($leader['last_name'] ?? '', 0, 1)) ?>
                                        </div>
                                        <?= htmlspecialchars($leader['first_name'] . ' ' . ($leader['last_name'] ?? '')) ?>
                                    </div>
                                </td>
                                <td style="color: #e5c378; font-family: monospace; font-size: 15px; font-weight: bold; letter-spacing: 1px;">
                                    <?= htmlspecialchars($leader['referral_code'] ?: 'N/A') ?>
                                </td>
                                <td><?= $uCount ?> Uses</td>
                                <td style="color: #b3ffb3;">
                                    <?= $uCount * $referralDiscountPercent ?>% Total Value
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        else:
                        ?>
                            <tr><td colspan="4" style="text-align: center; color: #aaa;">No referral data found yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Customer Database</h3>
                    <a href="#" class="btn-solid" style="width:auto; margin:0;" onclick="openUserModal('add'); return false;">
                        <svg viewBox="0 0 24 24" style="width:16px; height:16px; fill:none; stroke:currentColor; stroke-width:2;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                        Add User
                    </a>
                </div>
                
                <?php if (empty($registeredUsers)): ?>
                    <div style="text-align: center; padding: 30px; color: #aaa;">No users found in database yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Email Address</th>
                                <th>Phone Number</th>
                                <th>Saved Addresses</th>
                                <th>Account Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1000;
                            foreach ($registeredUsers as $user): 
                                $counter++;
                                $userId = '#USR-' . $counter;
                                $name = htmlspecialchars($user['first_name'] . ' ' . ($user['last_name'] ?? ''));
                                $avatarInitials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'] ?? '', 0, 1));
                                $email = htmlspecialchars($user['email']);
                                $phone = htmlspecialchars($user['phone']);
                                
                                $addrCount = isset($user['addresses']) ? count($user['addresses']) : 0;
                                $status = ($addrCount > 0) ? 'Active' : 'Pending Setup';
                                $statusClass = ($status === 'Active') ? 'status-active' : 'status-pending';
                            ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar-small"><?= $avatarInitials ?></div>
                                            <div>
                                                <span class="user-name"><?= $name ?></span>
                                                <span class="user-id"><?= $userId ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= $email ?></td>
                                    <td><?= $phone ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: <?= $addrCount > 0 ? '#e5c378' : '#aaa' ?>;"><?= $addrCount > 0 ? $addrCount . ' Address(es)' : 'N/A' ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $statusClass ?>"><?= $status ?></span>
                                    </td>
                                    <td>
                                        <div class="action-icons">
                                            <button class="icon-btn" title="View Profile" onclick='viewUser(<?= json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                <svg><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </button>
                                            <button class="icon-btn" title="Edit User" onclick='openUserModal("edit", <?= json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                <svg><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </button>
                                            <button class="icon-btn delete" title="Delete User" onclick='confirmDeleteUser("<?= $email ?>", "<?= $name ?>")'>
                                                <svg><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
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
            <form method="POST" action="users.php">
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

    <div id="userModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="userModalTitle">Add New User</h3>
                <button type="button" class="close-btn" onclick="closeUserModal()">&times;</button>
            </div>
            <form method="POST" action="users.php" id="userForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="userFormAction" value="add_user">
                <input type="hidden" name="original_email" id="originalEmail" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="userFirstName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="userLastName" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="userEmail" class="form-input" required>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" id="userPhone" class="form-input" required>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>Password</label>
                    <input type="password" name="password" id="userPassword" class="form-input" placeholder="Min 8 characters">
                    <span id="passHelp" style="font-size:11px; color:#aaa; margin-top:5px; display:block;"></span>
                </div>

                <button type="submit" class="btn-solid" style="margin-top: 20px; width:100%; justify-content:center;">Save User</button>
            </form>
        </div>
    </div>

    <div id="viewUserModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px; max-height: 85vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>User Details</h3>
                <button type="button" class="close-btn" onclick="closeViewUserModal()">&times;</button>
            </div>
            <div id="viewUserDetails" style="font-size: 14px; line-height: 1.6; color: #ccc;">
                </div>
        </div>
    </div>

    <div id="deleteConfirmModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div style="color: #ffb3b3; margin-bottom: 20px;">
                <svg style="width:64px; height:64px; stroke:currentColor; fill:none; stroke-width:2;" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            </div>
            <h3 style="margin:0 0 10px 0; font-size:20px; letter-spacing:1px; text-transform:uppercase;">Delete User</h3>
            <p style="color:#aaa; font-size:14px; margin:0 0 25px 0; line-height:1.5;">
                Are you sure you want to permanently delete <strong id="deleteUserName" style="color:#fff;"></strong>? This action cannot be undone.
            </p>
            <div style="display:flex; gap:15px; justify-content:center;">
                <button class="btn-outline" style="width:100%; border-color:rgba(255,255,255,0.2); color:#ccc;" onclick="closeDeleteUserModal()">Cancel</button>
                <button class="btn-solid" style="width:100%; background:rgba(255,0,0,0.2); border-color:rgba(255,0,0,0.5); color:#ffb3b3; justify-content:center;" onclick="executeDeleteUser()">Delete User</button>
            </div>
        </div>
    </div>

    <script>
        // Admin Profile Modal Logic
        function openAdminModal() { document.getElementById('adminModal').style.display = 'flex'; }
        function closeAdminModal() { document.getElementById('adminModal').style.display = 'none'; }

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

        // User CRUD Modal Logic
        function openUserModal(mode, data = null) {
            document.getElementById('userModal').style.display = 'flex';
            document.getElementById('userForm').reset();
            
            if (mode === 'add') {
                document.getElementById('userModalTitle').innerText = 'Add New User';
                document.getElementById('userFormAction').value = 'add_user';
                document.getElementById('originalEmail').value = '';
                document.getElementById('userEmail').readOnly = false;
                document.getElementById('userEmail').style.opacity = '1';
                document.getElementById('userPassword').required = true;
                document.getElementById('passHelp').innerText = "Password is required for new users.";
            } else if (mode === 'edit' && data) {
                document.getElementById('userModalTitle').innerText = 'Edit User';
                document.getElementById('userFormAction').value = 'edit_user';
                document.getElementById('originalEmail').value = data.email;
                
                document.getElementById('userFirstName').value = data.first_name;
                document.getElementById('userLastName').value = data.last_name || '';
                document.getElementById('userEmail').value = data.email;
                
                // Make email readonly to prevent primary key issues in this mock setup
                document.getElementById('userEmail').readOnly = true;
                document.getElementById('userEmail').style.opacity = '0.6';
                
                document.getElementById('userPhone').value = data.phone || '';
                
                document.getElementById('userPassword').required = false;
                document.getElementById('passHelp').innerText = "Leave blank to keep the current password.";
            }
        }
        function closeUserModal() { document.getElementById('userModal').style.display = 'none'; }

        // View User Logic with Addresses and Order History
        function viewUser(user) {
            const addrCount = user.addresses ? user.addresses.length : 0;
            const status = addrCount > 0 ? 'Active' : 'Pending Setup';
            const statusColor = addrCount > 0 ? '#b3ffb3' : '#e5c378';
            
            const lastNameSafe = user.last_name ? user.last_name : '';
            const initialLast = lastNameSafe ? lastNameSafe.charAt(0) : '';

            let baseHtml = `
                <div style="display:flex; flex-direction:column; align-items:center; margin-bottom:20px;">
                    <div style="width:70px; height:70px; border-radius:50%; background:linear-gradient(135deg, rgba(255,0,255,0.4), rgba(138,43,226,0.4)); border:2px solid rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:bold; color:#fff; margin-bottom:15px;">
                        ${user.first_name.charAt(0)}${initialLast}
                    </div>
                    <h2 style="margin:0 0 5px 0; font-size:20px; color:#fff;">${user.first_name} ${lastNameSafe}</h2>
                    <span class="status-badge" style="color:${statusColor}; border-color:${statusColor};">${status}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px; border-top:1px solid rgba(255,255,255,0.1); padding-top:15px;">
                    <strong style="color:#fff; font-size:14px;">Email Address:</strong>
                    <span>${user.email}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
                    <strong style="color:#fff; font-size:14px;">Phone Number:</strong>
                    <span>${user.phone || 'N/A'}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
                    <strong style="color:#fff; font-size:14px;">Referral Code:</strong>
                    <span style="color:#e5c378; font-family:monospace; font-size:15px;">${user.referral_code || 'N/A'}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
                    <strong style="color:#fff; font-size:14px;">Successful Referrals:</strong>
                    <span>${user.referral_count || 0}</span>
                </div>
            `;

            let addressesHtml = '';
            if (user.addresses && Array.isArray(user.addresses) && user.addresses.length > 0) {
                addressesHtml = '<div style="margin-top:20px; border-top:1px solid rgba(255,255,255,0.1); padding-top:15px;"><h4 style="margin:0 0 10px 0; color:#aaa; font-size:13px; text-transform:uppercase;">Saved Addresses</h4>';
                let validAddresses = 0;
                user.addresses.forEach((addr) => {
                    let addrText = addr;
                    if (typeof addr === 'object' && addr !== null) {
                        addrText = Object.values(addr).filter(val => {
                            if (val === null || val === '') return false;
                            if (typeof val === 'boolean') return false;
                            if (typeof val === 'string' && val.startsWith('addr_')) return false;
                            return true;
                        }).join(', ');
                    }
                    if (!addrText || addrText.toString().trim() === '' || addrText === '[object Object]') {
                        addrText = 'N/A';
                    }
                    
                    if (addrText !== 'N/A') {
                        addressesHtml += `<div style="background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; margin-bottom:8px; color:#ccc; font-size:13px;">${addrText}</div>`;
                        validAddresses++;
                    }
                });
                if (validAddresses === 0) {
                    addressesHtml += '<div style="background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; margin-bottom:8px; color:#ccc; font-size:13px;">N/A</div>';
                }
                addressesHtml += '</div>';
            } else {
                addressesHtml = '<div style="margin-top:20px; border-top:1px solid rgba(255,255,255,0.1); padding-top:15px;"><h4 style="margin:0 0 10px 0; color:#aaa; font-size:13px; text-transform:uppercase;">Saved Addresses</h4><div style="background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; margin-bottom:8px; color:#ccc; font-size:13px;">N/A</div></div>';
            }

            let ordersHtml = '';
            if (user.order_history && user.order_history.length > 0) {
                ordersHtml = '<div style="margin-top:20px; border-top:1px solid rgba(255,255,255,0.1); padding-top:15px;"><h4 style="margin:0 0 10px 0; color:#aaa; font-size:13px; text-transform:uppercase;">Order History</h4>';
                user.order_history.forEach(ord => {
                    ordersHtml += `
                        <div style="background:rgba(255,255,255,0.05); padding:12px; border-radius:8px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="color:#fff; font-weight:600; margin-bottom:4px;">${ord.id}</div>
                                <div style="color:#aaa; font-size:12px;">${ord.date} &bull; ${ord.items} item(s)</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="color:#e5c378; font-weight:bold; margin-bottom:4px;">${ord.amount}</div>
                                <span class="status-badge" style="color:${ord.color}; border-color:${ord.color}; font-size:10px; padding:2px 6px;">${ord.status}</span>
                            </div>
                        </div>`;
                });
                ordersHtml += '</div>';
            } else {
                 ordersHtml = '<div style="margin-top:20px; border-top:1px solid rgba(255,255,255,0.1); padding-top:15px;"><h4 style="margin:0 0 10px 0; color:#aaa; font-size:13px; text-transform:uppercase;">Order History</h4><div style="color:#777; font-size:13px; font-style:italic;">No past orders found for this user.</div></div>';
            }

            document.getElementById('viewUserDetails').innerHTML = baseHtml + addressesHtml + ordersHtml;
            document.getElementById('viewUserModal').style.display = 'flex';
        }
        function closeViewUserModal() { document.getElementById('viewUserModal').style.display = 'none'; }

        // Delete User Confirmation Logic
        let userToDeleteEmail = null;
        function confirmDeleteUser(email, name) {
            userToDeleteEmail = email;
            document.getElementById('deleteUserName').innerText = name;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
        }
        function closeDeleteUserModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            userToDeleteEmail = null;
        }
        function executeDeleteUser() {
            if (userToDeleteEmail) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'users.php';
                form.innerHTML = '<?= csrf_field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="delete_email" value="'+userToDeleteEmail+'">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target === document.getElementById('adminModal')) closeAdminModal();
            if (event.target === document.getElementById('userModal')) closeUserModal();
            if (event.target === document.getElementById('viewUserModal')) closeViewUserModal();
            if (event.target === document.getElementById('deleteConfirmModal')) closeDeleteUserModal();
            
            // Close Notification Dropdown if clicking outside
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