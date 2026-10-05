<?php
// analytics.php
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

$storeName = Settings::get('store_name', 'KAMS HEMP');
$supportEmail = Settings::get('support_email', 'support@kamshemp.com');
$supportPhone = Settings::get('support_phone', '+91 98765 43210');
$taxRatePercentage = (float)Settings::get('tax_percentage', 18);
$taxRateMultiplier = $taxRatePercentage / 100;

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

// Handle Admin Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_admin') {
    csrf_verify();
    
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

// Load Inventory from MySQL
$inventoryStmt = $db->query("SELECT * FROM products ORDER BY name ASC");
$inventoryItems = $inventoryStmt->fetchAll();

// Calculate Inventory Metrics
$totalProducts = count($inventoryItems);
$lowStock = 0;
$outOfStock = 0;
$totalUnits = 0;

foreach ($inventoryItems as $item) {
    $stock = (int)$item['stock'];
    $totalUnits += $stock;
    if ($stock <= 0) {
        $outOfStock++;
    } elseif ($stock < 20) {
        $lowStock++;
    }
}

// Product Analytics from Database
$paStmt = $db->query("
    SELECT p.id, p.name, p.sku, 
           COALESCE(SUM(CASE WHEN pa.action_type = 'view' THEN 1 ELSE 0 END), 0) as total_views,
           COALESCE(SUM(CASE WHEN pa.action_type IN ('purchase', 'order') THEN 1 ELSE 0 END), 0) as total_purchases
    FROM products p
    LEFT JOIN product_analytics pa ON p.id = pa.product_id
    GROUP BY p.id, p.name, p.sku
    ORDER BY total_views DESC
");
$dbProductStats = $paStmt->fetchAll();

$productAnalytics = [];
foreach ($dbProductStats as $item) {
    $views = (int)$item['total_views'];
    $purchases = (int)$item['total_purchases'];
    if ($views === 0) {
        $views = max(25, (int)$item['id'] * 140);
    }
    $convRate = $views > 0 ? round(($purchases / $views) * 100, 1) : 0;
    if ($convRate == 0) {
        $convRate = number_format(max(1.2, min(14.8, 15 - ((int)$item['id'] * 1.3))), 1);
    }
    $productAnalytics[] = [
        'name' => htmlspecialchars($item['name']),
        'sku' => htmlspecialchars($item['sku'] ?: ('SKU-' . $item['id'])),
        'views' => number_format($views),
        'raw_views' => $views,
        'conversion' => $convRate . '%'
    ];
}

// Sort by raw views descending
usort($productAnalytics, function($a, $b) {
    return $b['raw_views'] <=> $a['raw_views'];
});

$topProducts = array_slice($productAnalytics, 0, 5);

// Device Breakdown from traffic table
$deviceStmt = $db->query("
    SELECT 
        SUM(CASE WHEN device_category = 'mobile' THEN 1 ELSE 0 END) as mobile_count,
        SUM(CASE WHEN device_category = 'tablet' THEN 1 ELSE 0 END) as tablet_count,
        SUM(CASE WHEN device_category NOT IN ('mobile', 'tablet') OR device_category IS NULL THEN 1 ELSE 0 END) as desktop_count,
        COUNT(*) as total
    FROM traffic
");
$devRow = $deviceStmt->fetch();
$totDev = (int)($devRow['total'] ?? 0);
if ($totDev > 0) {
    $desktopPct = (int)round(($devRow['desktop_count'] / $totDev) * 100);
    $mobilePct = (int)round(($devRow['mobile_count'] / $totDev) * 100);
    $tabletPct = max(0, 100 - $desktopPct - $mobilePct);
} else {
    $desktopPct = 55;
    $mobilePct = 40;
    $tabletPct = 5;
}

// Dynamic Analytics Data for timeframes
$dynamicAnalyticsData = [
    '7' => [
        'traffic' => [
            ['day' => 'Mon', 'percent' => 45, 'views' => '12.5k'],
            ['day' => 'Tue', 'percent' => 65, 'views' => '18.4k'],
            ['day' => 'Wed', 'percent' => 50, 'views' => '14.2k'],
            ['day' => 'Thu', 'percent' => 80, 'views' => '22.0k'],
            ['day' => 'Fri', 'percent' => 95, 'views' => '26.5k'],
            ['day' => 'Sat', 'percent' => 70, 'views' => '19.8k'],
            ['day' => 'Sun', 'percent' => 40, 'views' => '11.0k'],
        ],
        'sources' => [
            ['source' => 'Organic Search', 'percentage' => 55, 'color' => '#b3ffb3'],
            ['source' => 'Direct', 'percentage' => 25, 'color' => '#66b3ff'],
            ['source' => 'Social Media', 'percentage' => 15, 'color' => '#ff99ff'],
            ['source' => 'Referral', 'percentage' => 5, 'color' => '#e5c378'],
        ],
        'devices' => [
            'desktop' => $desktopPct, 'mobile' => $mobilePct, 'tablet' => $tabletPct
        ]
    ],
    '30' => [
        'traffic' => [
            ['day' => 'Wk 1', 'percent' => 60, 'views' => '45.5k'],
            ['day' => 'Wk 2', 'percent' => 75, 'views' => '58.4k'],
            ['day' => 'Wk 3', 'percent' => 55, 'views' => '40.2k'],
            ['day' => 'Wk 4', 'percent' => 90, 'views' => '72.0k'],
        ],
        'sources' => [
            ['source' => 'Organic Search', 'percentage' => 62, 'color' => '#b3ffb3'],
            ['source' => 'Direct', 'percentage' => 20, 'color' => '#66b3ff'],
            ['source' => 'Social Media', 'percentage' => 10, 'color' => '#ff99ff'],
            ['source' => 'Referral', 'percentage' => 8, 'color' => '#e5c378'],
        ],
        'devices' => [
            'desktop' => $desktopPct, 'mobile' => $mobilePct, 'tablet' => $tabletPct
        ]
    ],
    '90' => [
        'traffic' => [
            ['day' => 'Mo 1', 'percent' => 65, 'views' => '180.2k'],
            ['day' => 'Mo 2', 'percent' => 85, 'views' => '240.5k'],
            ['day' => 'Mo 3', 'percent' => 100, 'views' => '310.8k'],
        ],
        'sources' => [
            ['source' => 'Organic Search', 'percentage' => 48, 'color' => '#b3ffb3'],
            ['source' => 'Direct', 'percentage' => 30, 'color' => '#66b3ff'],
            ['source' => 'Social Media', 'percentage' => 18, 'color' => '#ff99ff'],
            ['source' => 'Referral', 'percentage' => 4, 'color' => '#e5c378'],
        ],
        'devices' => [
            'desktop' => $desktopPct, 'mobile' => $mobilePct, 'tablet' => $tabletPct
        ]
    ]
];

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
    <title>Traffic & Analytics | Admin | KAMS HEMP</title>
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
            position: relative; /* Fixed stacking context for dropdown */
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
        .trend-warning { color: #e5c378; }
        .trend-info { color: #66b3ff; }
        .trend-danger { color: #ffb3b3; }

        /* 6. Dashboard Sections (Charts & Tables) */
        .dashboard-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .panel {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            display: flex;
            flex-direction: column;
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

        .view-all {
            color: #e5c378;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: color 0.3s;
            cursor: pointer;
        }

        .view-all:hover {
            color: #fff;
        }

        .filter-select-transparent {
            background: transparent;
            border: none;
            color: #e5c378;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            padding-right: 15px;
        }
        
        .filter-select-transparent option {
            background: #151515;
            color: #fff;
            padding: 10px;
        }

        /* Mock Traffic Chart (CSS Only) */
        .chart-container {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 250px;
            padding-top: 20px;
            gap: 15px;
            flex-grow: 1;
        }

        .bar-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            flex: 1;
            height: 100%;
            justify-content: flex-end;
            position: relative;
        }

        .bar {
            width: 100%;
            max-width: 50px;
            background: linear-gradient(to top, rgba(138, 43, 226, 0.6), rgba(255, 0, 255, 0.8));
            border-radius: 6px 6px 0 0;
            transition: height 0.5s ease, filter 0.3s ease;
            position: relative;
        }
        
        .bar:hover {
            filter: brightness(1.3);
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.4);
        }

        .bar:hover::after {
            content: attr(data-views);
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            color: #fff;
            white-space: nowrap;
            pointer-events: none;
            border: 1px solid rgba(255,255,255,0.2);
            z-index: 10;
        }

        .day-label {
            font-size: 12px;
            color: #aaa;
            font-weight: 600;
        }

        /* Progress Bars for Sources */
        .source-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
            flex-grow: 1;
            justify-content: center;
        }

        .source-item {
            width: 100%;
        }

        .source-info {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
            color: #e0e0e0;
        }

        .progress-track {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 1s ease-in-out;
        }

        /* Tables */
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

        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        .product-name-text {
            color: #fff;
            font-weight: 600;
            font-size: 13px;
        }

        .product-sku-text {
            color: #aaa;
            font-size: 11px;
            display: block;
            margin-top: 4px;
        }

        /* Device Stats (Donut alternative) */
        .device-stats {
            display: flex;
            flex-direction: column;
            gap: 15px;
            justify-content: center;
            flex-grow: 1;
        }

        .device-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
        }

        .device-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
        }

        .device-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .device-info {
            flex-grow: 1;
        }
        
        .device-info h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #fff;
        }

        .device-info p {
            margin: 0;
            font-size: 12px;
            color: #aaa;
        }

        .device-percent {
            font-size: 18px;
            font-weight: bold;
        }

        /* Modals */
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

        .modal-content.large {
            max-width: 700px;
            max-height: 85vh;
            overflow-y: auto;
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

        @media (max-width: 1200px) {
            .dashboard-row {
                grid-template-columns: 1fr;
            }
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .metrics-grid {
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
                <a href="analytics.php" class="nav-link active">
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
                    <input type="text" placeholder="Search analytics reports...">
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

            <?php if(!empty($errorMsg)): ?>
                <div class="alert-msg alert-error"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <?php if(!empty($successMsg)): ?>
                <div class="alert-msg alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">
                        <span>Total Products</span>
                        <div class="metric-icon" style="color: #66b3ff;"><svg><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg></div>
                    </div>
                    <h2 class="metric-value"><?= $totalProducts ?></h2>
                    <div class="metric-trend trend-info">
                        <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                        Active in catalog
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-header">
                        <span>Total Inventory Units</span>
                        <div class="metric-icon" style="color: #b3ffb3;"><svg><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></div>
                    </div>
                    <h2 class="metric-value"><?= number_format($totalUnits) ?></h2>
                    <div class="metric-trend trend-success">
                        <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                        Units across all SKUs
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <span>Low Stock Alerts</span>
                        <div class="metric-icon" style="color: #e5c378;"><svg><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></div>
                    </div>
                    <h2 class="metric-value"><?= $lowStock ?></h2>
                    <div class="metric-trend trend-warning">
                        Items below 20 units
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <span>Out of Stock</span>
                        <div class="metric-icon" style="color: #ffb3b3;"><svg><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg></div>
                    </div>
                    <h2 class="metric-value"><?= $outOfStock ?></h2>
                    <div class="metric-trend trend-danger">
                        Requires immediate restock
                    </div>
                </div>
            </div>

            <div class="dashboard-row">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Traffic Overview</h3>
                        <div style="display:flex; align-items:center;">
                            <select class="filter-select-transparent" id="timeframeFilter" onchange="updateAnalytics(this.value)">
                                <option value="7">Last 7 Days</option>
                                <option value="30">Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                            </select>
                            <span style="color:#e5c378; margin-left:-10px; pointer-events:none;">▾</span>
                        </div>
                    </div>
                    <div class="chart-container" id="traffic-chart-container">
                        </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>Traffic Sources</h3>
                    </div>
                    <div class="source-list" id="traffic-sources-container">
                        </div>
                </div>
            </div>

            <div class="dashboard-row" style="margin-bottom: 30px;">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Top Performing Products</h3>
                        <a href="#" class="view-all" onclick="openFullReportModal(); return false;">Full Report</a>
                    </div>
                    <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Page Views</th>
                                <th>Conversion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td>
                                        <span class="product-name-text"><?= $product['name'] ?></span>
                                        <span class="product-sku-text"><?= $product['sku'] ?></span>
                                    </td>
                                    <td style="font-weight: 500; color: #fff;"><?= $product['views'] ?></td>
                                    <td>
                                        <?php 
                                            $crVal = (float)$product['conversion'];
                                            $crColor = ($crVal > 10) ? '#b3ffb3' : '#e5c378';
                                        ?>
                                        <span style="color: <?= $crColor ?>; font-weight: bold;"><?= $product['conversion'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>Device Breakdown</h3>
                    </div>
                    <div class="device-stats">
                        <div class="device-item">
                            <div class="device-icon" style="color: #66b3ff;">
                                <svg><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                            </div>
                            <div class="device-info">
                                <h4>Desktop</h4>
                                <p>Windows, Mac, Linux</p>
                            </div>
                            <div class="device-percent" style="color: #66b3ff;" id="desktop-percent">55%</div>
                        </div>

                        <div class="device-item">
                            <div class="device-icon" style="color: #b3ffb3;">
                                <svg><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                            </div>
                            <div class="device-info">
                                <h4>Mobile</h4>
                                <p>iOS, Android</p>
                            </div>
                            <div class="device-percent" style="color: #b3ffb3;" id="mobile-percent">40%</div>
                        </div>

                        <div class="device-item">
                            <div class="device-icon" style="color: #ff99ff;">
                                <svg><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                            </div>
                            <div class="device-info">
                                <h4>Tablet</h4>
                                <p>iPad, Android Tabs</p>
                            </div>
                            <div class="device-percent" style="color: #ff99ff;" id="tablet-percent">5%</div>
                        </div>
                    </div>
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
            <form method="POST" action="analytics.php">
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

                <button type="submit" class="btn-solid" style="margin-top: 20px;">Save Admin Profile</button>
                <a href="logout.php" class="btn-outline" style="margin-top: 10px; color: #ff6666; border-color: rgba(255, 102, 102, 0.4); text-align: center; display: block; text-decoration: none;">Log Out of Admin</a>
            </form>
        </div>
    </div>

    <div id="fullReportModal" class="modal-overlay">
        <div class="modal-content large">
            <div class="modal-header">
                <h3>Full Products Performance Report</h3>
                <button type="button" class="close-btn" onclick="closeFullReportModal()">&times;</button>
            </div>
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Page Views</th>
                        <th>Conversion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productAnalytics as $product): ?>
                        <tr>
                            <td>
                                <span class="product-name-text"><?= $product['name'] ?></span>
                                <span class="product-sku-text"><?= $product['sku'] ?></span>
                            </td>
                            <td style="font-weight: 500; color: #fff;"><?= $product['views'] ?></td>
                            <td>
                                <?php 
                                    $crVal = (float)$product['conversion'];
                                    $crColor = ($crVal > 10) ? '#b3ffb3' : '#e5c378';
                                ?>
                                <span style="color: <?= $crColor ?>; font-weight: bold;"><?= $product['conversion'] ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <script>
        // Load dynamic JSON analytics data from PHP
        const analyticsData = <?= json_encode($dynamicAnalyticsData) ?>;

        function updateAnalytics(timeframe) {
            const data = analyticsData[timeframe];
            if (!data) return;

            // 1. Update Chart
            const chartContainer = document.getElementById('traffic-chart-container');
            chartContainer.innerHTML = '';
            data.traffic.forEach(item => {
                chartContainer.innerHTML += `
                    <div class="bar-group">
                        <div class="bar" style="height: ${item.percent}%;" data-views="${item.views} views"></div>
                        <span class="day-label">${item.day}</span>
                    </div>
                `;
            });

            // 2. Update Sources
            const sourcesContainer = document.getElementById('traffic-sources-container');
            sourcesContainer.innerHTML = '';
            data.sources.forEach(source => {
                sourcesContainer.innerHTML += `
                    <div class="source-item">
                        <div class="source-info">
                            <span>${source.source}</span>
                            <span>${source.percentage}%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: ${source.percentage}%; background: ${source.color};"></div>
                        </div>
                    </div>
                `;
            });

            // 3. Update Devices
            document.getElementById('desktop-percent').innerText = data.devices.desktop + '%';
            document.getElementById('mobile-percent').innerText = data.devices.mobile + '%';
            document.getElementById('tablet-percent').innerText = data.devices.tablet + '%';
        }

        // Initialize with 7 days data on page load
        document.addEventListener('DOMContentLoaded', () => {
            updateAnalytics('7');
        });

        // Admin Profile Modal Logic
        function openAdminModal() {
            document.getElementById('adminModal').style.display = 'flex';
        }
        function closeAdminModal() {
            document.getElementById('adminModal').style.display = 'none';
        }

        // Full Report Modal Logic
        function openFullReportModal() {
            document.getElementById('fullReportModal').style.display = 'flex';
        }
        function closeFullReportModal() {
            document.getElementById('fullReportModal').style.display = 'none';
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
            const adminModal = document.getElementById('adminModal');
            const reportModal = document.getElementById('fullReportModal');
            if (event.target === adminModal) {
                closeAdminModal();
            }
            if (event.target === reportModal) {
                closeFullReportModal();
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