<?php
// inventory.php - Dynamic MySQL Product & Inventory Management
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/analytics.php';

Auth::requireAdmin();

$db = Database::getInstance();

// Load Global Settings
$allSettings = Settings::getAll();
$storeName = Settings::get('store_name', 'KAMS HEMP');
$currencySymbol = Settings::getCurrencySymbol();
$settings = $allSettings;

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
$adminInitials = strtoupper(substr($adminData['first_name'], 0, 1) . substr($adminData['last_name'] ?: 'A', 0, 1));
$adminFullName = htmlspecialchars($adminData['first_name'] . ' ' . $adminData['last_name']);

$successMsg = '';
$errorMsg = '';

// Process File Uploads Function 
function handleImageUploads() {
    $uploadedPaths = [];
    $uploadDirDisk = __DIR__ . '/uploads/products/';

    $hasPrimary = isset($_FILES['img_primary']) && $_FILES['img_primary']['error'] !== UPLOAD_ERR_NO_FILE;
    $hasSecondary = isset($_FILES['img_secondary']) && $_FILES['img_secondary']['error'] !== UPLOAD_ERR_NO_FILE;
    $hasRemaining = isset($_FILES['images']) && !empty($_FILES['images']['name'][0]);

    if ($hasPrimary || $hasSecondary || $hasRemaining) {
        if (!is_dir($uploadDirDisk)) {
            @mkdir($uploadDirDisk, 0755, true);
        }

        $processUpload = function($fileArray, $indexKey, $isMultiple = false, $multipleIndex = 0) use ($uploadDirDisk) {
            $error = $isMultiple ? $fileArray['error'][$multipleIndex] : $fileArray['error'];
            if ($error === UPLOAD_ERR_OK) {
                $tmpName = $isMultiple ? $fileArray['tmp_name'][$multipleIndex] : $fileArray['tmp_name'];
                $name = $isMultiple ? $fileArray['name'][$multipleIndex] : $fileArray['name'];
                
                $cleanName = preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($name));
                $fileName = time() . '_' . $indexKey . '_' . $cleanName;
                $targetFile = $uploadDirDisk . $fileName;
                
                if (move_uploaded_file($tmpName, $targetFile)) {
                    return 'uploads/products/' . $fileName;
                }
            }
            return null;
        };

        if ($hasPrimary) {
            $path = $processUpload($_FILES['img_primary'], 'pri');
            if ($path) $uploadedPaths[] = $path;
        }
        if ($hasSecondary) {
            $path = $processUpload($_FILES['img_secondary'], 'sec');
            if ($path) $uploadedPaths[] = $path;
        }
        if ($hasRemaining) {
            $fileCount = count($_FILES['images']['name']);
            $limit = min($fileCount, 5);
            for ($i = 0; $i < $limit; $i++) {
                $path = $processUpload($_FILES['images'], "rem_$i", true, $i);
                if ($path) $uploadedPaths[] = $path;
            }
        }
    }
    return $uploadedPaths;
}

// Helper to resolve or create category ID
function getOrCreateCategoryId($db, $catName) {
    $catName = trim($catName ?: 'General');
    $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$catName]);
    $catId = $stmt->fetchColumn();
    if (!$catId) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $catName));
        $ins = $db->prepare("INSERT INTO categories (name, slug, is_active, created_at) VALUES (?, ?, 1, NOW())");
        $ins->execute([$catName, $slug]);
        $catId = $db->lastInsertId();
    }
    return (int)$catId;
}

// Handle Form Actions (Admin Profile + Inventory CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        $errorMsg = "Security token validation failed. Please refresh.";
    } else {
        // --- Admin Update ---
        if ($_POST['action'] === 'update_admin') {
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
                    $successMsg = "Admin profile updated successfully.";
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

        // --- Add Product ---
        elseif ($_POST['action'] === 'add_product') {
            try {
                $pName = trim($_POST['name'] ?? '');
                $sku = trim($_POST['sku'] ?? '');
                $catName = trim($_POST['category'] ?? 'General');
                $price = floatval(preg_replace('/[^0-9.]/', '', $_POST['price'] ?? 0));
                $mrpInput = floatval(preg_replace('/[^0-9.]/', '', $_POST['mrp'] ?? 0));
                $saleBadge = trim($_POST['sale_badge'] ?? '');
                $stock = (int)($_POST['stock'] ?? 0);
                $desc = trim($_POST['description'] ?? '');

                $status = ($stock <= 0) ? 'Out of Stock' : ($stock < 20 ? 'Low Stock' : 'Active');
                $catId = getOrCreateCategoryId($db, $catName);
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $pName)) . '-' . rand(100, 999);

                $stmtIns = $db->prepare("
                    INSERT INTO products (category_id, category_name, name, slug, sku, description, price, mrp, sale_badge, stock, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmtIns->execute([
                    $catId,
                    $catName,
                    $pName,
                    $slug,
                    $sku,
                    $desc,
                    $price,
                    $mrpInput > 0 ? $mrpInput : null,
                    $saleBadge,
                    $stock,
                    $status
                ]);
                $newProdId = (int)$db->lastInsertId();

                // Handle image uploads
                $uploadedImages = handleImageUploads();
                if (empty($uploadedImages)) {
                    $uploadedImages = [
                        'uploads/products/default.webp'
                    ];
                }

                $imgStmt = $db->prepare("INSERT INTO product_images (product_id, image_url, is_primary, is_secondary, sort_order) VALUES (?, ?, ?, ?, ?)");
                foreach ($uploadedImages as $idx => $imgUrl) {
                    $isPri = ($idx === 0) ? 1 : 0;
                    $isSec = ($idx === 1) ? 1 : 0;
                    $imgStmt->execute([$newProdId, $imgUrl, $isPri, $isSec, $idx]);
                }

                // Log inventory transaction
                if ($stock > 0) {
                    Inventory::adjustStock($newProdId, $stock, 'Initial product creation stock');
                }

                $successMsg = "Product added successfully.";
            } catch (\Throwable $e) {
                $errorMsg = "Error adding product: " . $e->getMessage();
            }
        }

        // --- Edit Product ---
        elseif ($_POST['action'] === 'edit_product') {
            try {
                $idToEdit = (int)($_POST['product_id'] ?? 0);
                $pName = trim($_POST['name'] ?? '');
                $sku = trim($_POST['sku'] ?? '');
                $catName = trim($_POST['category'] ?? 'General');
                $price = floatval(preg_replace('/[^0-9.]/', '', $_POST['price'] ?? 0));
                $mrpInput = floatval(preg_replace('/[^0-9.]/', '', $_POST['mrp'] ?? 0));
                $saleBadge = trim($_POST['sale_badge'] ?? '');
                $newStock = (int)($_POST['stock'] ?? 0);
                $desc = trim($_POST['description'] ?? '');

                // Get current stock
                $curStmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
                $curStmt->execute([$idToEdit]);
                $oldStock = (int)$curStmt->fetchColumn();

                $status = ($newStock <= 0) ? 'Out of Stock' : ($newStock < 20 ? 'Low Stock' : 'Active');
                $catId = getOrCreateCategoryId($db, $catName);

                $stmtUpd = $db->prepare("
                    UPDATE products 
                    SET category_id = ?, category_name = ?, name = ?, sku = ?, description = ?, price = ?, mrp = ?, sale_badge = ?, stock = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmtUpd->execute([
                    $catId,
                    $catName,
                    $pName,
                    $sku,
                    $desc,
                    $price,
                    $mrpInput > 0 ? $mrpInput : null,
                    $saleBadge,
                    $newStock,
                    $status,
                    $idToEdit
                ]);

                // If stock changed, adjust inventory
                if ($newStock !== $oldStock) {
                    Inventory::adjustStock($idToEdit, $newStock, 'Admin manual inventory edit');
                }

                // Capture kept existing images and newly uploaded images
                $existingImages = $_POST['existing_images'] ?? [];
                $uploadedImages = handleImageUploads();
                $finalImages = array_values(array_unique(array_merge($existingImages, $uploadedImages)));

                if (!empty($finalImages)) {
                    // Delete old image records and replace with fresh list
                    $db->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$idToEdit]);
                    $imgStmt = $db->prepare("INSERT INTO product_images (product_id, image_url, is_primary, is_secondary, sort_order) VALUES (?, ?, ?, ?, ?)");
                    foreach ($finalImages as $idx => $imgUrl) {
                        $isPri = ($idx === 0) ? 1 : 0;
                        $isSec = ($idx === 1) ? 1 : 0;
                        $imgStmt->execute([$idToEdit, $imgUrl, $isPri, $isSec, $idx]);
                    }
                }

                $successMsg = "Product updated successfully.";
            } catch (\Throwable $e) {
                $errorMsg = "Error updating product: " . $e->getMessage();
            }
        }

        // --- Delete Product ---
        elseif ($_POST['action'] === 'delete_product') {
            $idToDelete = (int)($_POST['product_id'] ?? 0);
            $db->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$idToDelete]);
            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$idToDelete]);
            $successMsg = "Product deleted successfully.";
        }
    }
}

// Fetch all inventory items from MySQL
$stmtProducts = $db->query("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.id DESC
");
$rawProducts = $stmtProducts->fetchAll();
$inventoryItems = [];

$totalProducts = count($rawProducts);
$lowStock = 0;
$outOfStock = 0;
$totalUnits = 0;

foreach ($rawProducts as $p) {
    $pId = (int)$p['id'];
    $stmtImg = $db->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
    $stmtImg->execute([$pId]);
    $images = $stmtImg->fetchAll(PDO::FETCH_COLUMN);

    $stock = (int)($p['stock'] ?? ($p['stock_quantity'] ?? 0));
    $totalUnits += $stock;
    if ($stock <= 0) {
        $status = 'Out of Stock';
        $outOfStock++;
    } elseif ($stock < 20) {
        $status = 'Low Stock';
        $lowStock++;
    } else {
        $status = 'Active';
    }

    $mrpFormatted = !empty($p['mrp']) ? '₹' . number_format((float)$p['mrp']) : (!empty($p['compare_at_price']) ? '₹' . number_format((float)$p['compare_at_price']) : '');

    $inventoryItems[] = [
        'id' => $pId,
        'name' => $p['name'],
        'sku' => $p['sku'],
        'category' => $p['category_name'] ?: 'General',
        'price' => '₹' . number_format((float)$p['price']),
        'raw_price' => (float)$p['price'],
        'mrp' => $mrpFormatted,
        'raw_mrp' => (float)($p['mrp'] ?? ($p['compare_at_price'] ?? 0)),
        'sale_badge' => $p['sale_badge'] ?? ($p['badge'] ?? ''),
        'stock' => $stock,
        'status' => $status,
        'description' => $p['description'] ?? '',
        'img' => !empty($images) ? $images[0] : 'uploads/products/default.jpg',
        'images' => $images
    ];
}

// Notification Data Preparation from MySQL
$stmtNotif = $db->query("
    SELECT o.order_number, o.total_amount, o.order_status, o.created_at,
           (SELECT product_name FROM order_items WHERE order_id = o.id LIMIT 1) as prod_name
    FROM orders o 
    ORDER BY o.id DESC LIMIT 5
");
$recentOrders = [];
while ($ro = $stmtNotif->fetch()) {
    $recentOrders[] = [
        'id' => $ro['order_number'],
        'product_name' => $ro['prod_name'] ?: 'Order #' . $ro['order_number'],
        'amount' => '₹' . number_format((float)$ro['total_amount'], 2),
        'time_str' => date('h:i A', strtotime($ro['created_at'])),
        'status' => $ro['order_status']
    ];
}

$newOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Processing', 'Pending', 'New')")->fetchColumn();

// Traffic stats
$dailyVisits = (int)$db->query("SELECT COUNT(*) FROM traffic WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 15;
$trafficStats = [
    'active_now' => rand(3, 15),
    'today_visits' => $dailyVisits,
    'status' => 'Normal'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management | Admin | KAMS HEMP</title>
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
            cursor: pointer;
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

        .trend-danger { color: #ffb3b3; }
        .trend-warning { color: #e5c378; }
        .trend-success { color: #b3ffb3; }
        .trend-info { color: #66b3ff; }

        /* 6. Dashboard Panel (Table) */
        .panel {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            margin-bottom: 30px;
            flex-grow: 1;
            flex-shrink: 0;
            min-height: fit-content;
            overflow-x: auto; /* Handles horizontal scrolling for smaller screens */
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
            justify-content: center;
            gap: 8px;
        }

        .btn-solid:hover {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.8) 0%, rgba(138, 43, 226, 0.8) 100%);
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.4);
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

        /* Inventory Table */
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px; /* Prevents squishing, forces scroll on mobile */
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
            white-space: nowrap;
        }

        tr.table-row-item {
            transition: background 0.3s;
        }
        
        tr.table-row-item:hover td {
            background: rgba(255,255,255,0.02);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .product-img {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .product-name {
            font-weight: 600;
            color: #fff;
            display: block;
        }

        .product-sku {
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
        .status-low { color: #e5c378; border-color: #e5c378; }
        .status-out { color: #ffb3b3; border-color: #ffb3b3; }

        .stock-bar-container {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
            max-width: 100px;
        }

        .stock-bar {
            height: 100%;
            border-radius: 3px;
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
            background: rgba(255,0,255,0.1);
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

        /* Modals Overlay Base */
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

        /* Wider modal specifically for Product Add/Edit/View */
        .product-modal-content {
            max-width: 600px; 
            max-height: 90vh;
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

        select.form-input option {
            background: #151515;
            color: #fff;
        }

        /* Custom Input File Styling */
        input[type="file"].form-input {
            padding: 8px 12px;
            line-height: 1.5;
        }

        input[type="file"]::file-selector-button {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            margin-right: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        input[type="file"]::file-selector-button:hover {
            background: rgba(255,0,255,0.4);
            border-color: rgba(255,0,255,0.8);
        }

        .image-preview-thumbnail {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .img-wrapper {
            position: relative;
            display: inline-block;
        }

        .img-remove-btn {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4d4d;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            box-shadow: 0 0 5px rgba(0,0,0,0.5);
        }

        /* Responsive Breakpoints - Strictly handled via CSS */
        @media (max-width: 1200px) {
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
                <a href="admin.php" class="nav-link">
                    <svg><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard
                </a>
                <a href="admin_orders.php" class="nav-link">
                    <svg><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    Orders
                </a>
                <a href="inventory.php" class="nav-link active">
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
                    <input type="text" id="tableSearchInput" placeholder="Search products by SKU or Name..." onkeyup="filterInventoryTable()">
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
                <div class="metric-card" onclick="triggerFilter('All')" style="cursor:pointer;">
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
                
                <div class="metric-card" onclick="triggerFilter('All')" style="cursor:pointer;">
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

                <div class="metric-card" onclick="triggerFilter('Low Stock')" style="cursor:pointer;">
                    <div class="metric-header">
                        <span>Low Stock Alerts</span>
                        <div class="metric-icon" style="color: #e5c378;"><svg><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></div>
                    </div>
                    <h2 class="metric-value"><?= $lowStock ?></h2>
                    <div class="metric-trend trend-warning">
                        Items below 20 units
                    </div>
                </div>

                <div class="metric-card" onclick="triggerFilter('Out of Stock')" style="cursor:pointer;">
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

            <div class="panel" id="inventoryTablePanel">
                <div class="panel-header">
                    <h3>Inventory Overview</h3>
                    <a href="#" class="btn-solid" style="width:auto; margin:0;" onclick="openProductModal('add'); return false;">
                        <svg viewBox="0 0 24 24" style="width:16px; height:16px; fill:none; stroke:currentColor; stroke-width:2;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Add Product
                    </a>
                </div>
                
                <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventoryItems as $item): ?>
                            <?php 
                                // Determine styling based on status
                                $statusClass = '';
                                $barWidth = min(100, ($item['stock'] / 100) * 100) . '%'; // Cap visual bar at 100px roughly
                                $barColor = '#b3ffb3'; // Green
                                
                                if ($item['status'] === 'Active') {
                                    $statusClass = 'status-active';
                                } elseif ($item['status'] === 'Low Stock') {
                                    $statusClass = 'status-low';
                                    $barColor = '#e5c378'; // Yellow
                                } else {
                                    $statusClass = 'status-out';
                                    $barWidth = '0%';
                                    $barColor = '#ffb3b3'; // Red
                                }
                                
                                $showBadge = (!empty($item['mrp']) && $item['mrp'] !== $item['price']) || !empty($item['sale_badge']);
                                $badgeText = !empty($item['sale_badge']) ? htmlspecialchars($item['sale_badge']) : 'SALE';
                            ?>
                            <tr class="table-row-item" data-status="<?= htmlspecialchars($item['status']) ?>">
                                <td>
                                    <div class="product-cell">
                                        <?php if ($showBadge): ?>
                                            <div style="position: absolute; top: -5px; left: -5px; background: linear-gradient(135deg, #ff00ff 0%, #8a2be2 100%); color: #fff; font-size: 8px; font-weight: bold; padding: 2px 4px; border-radius: 4px; z-index: 10; text-transform: uppercase;"><?= $badgeText ?></div>
                                        <?php endif; ?>
                                        <img src="<?= htmlspecialchars($item['img'] ?? '') ?>" alt="Product" class="product-img">
                                        <div>
                                            <span class="product-name search-target"><?= htmlspecialchars($item['name']) ?></span>
                                            <span class="product-sku search-target"><?= htmlspecialchars($item['sku']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($item['category']) ?></td>
                                <td>
                                    <?php if (!empty($item['mrp']) && $item['mrp'] !== $item['price']): ?>
                                        <div style="font-size:12px; color:#aaa; text-decoration:line-through;"><?= htmlspecialchars($item['mrp']) ?></div>
                                    <?php endif; ?>
                                    <div style="color:#e5c378; font-weight:bold;"><?= htmlspecialchars($item['price']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($item['stock']) ?> in stock</div>
                                    <div class="stock-bar-container">
                                        <div class="stock-bar" style="width: <?= $barWidth ?>; background: <?= $barColor ?>;"></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($item['status']) ?></span>
                                </td>
                                <td>
                                    <div class="action-icons">
                                        <button class="icon-btn" title="View" onclick='viewProduct(<?= json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </button>
                                        <button class="icon-btn" title="Edit" onclick='openProductModal("edit", <?= json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <svg><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </button>
                                        <button class="icon-btn delete" title="Delete" onclick='confirmDeleteModal(<?= $item['id'] ?>, <?= json_encode($item['name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <svg><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
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
            <form method="POST" action="inventory.php">
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

                <button type="submit" class="btn-solid" style="margin-top: 20px; width: 100%; justify-content:center;">Save Admin Profile</button>
                <a href="logout.php" class="btn-outline" style="margin-top: 10px; color: #ff6666; border-color: rgba(255, 102, 102, 0.4); text-align: center; display: block; text-decoration: none;">Log Out of Admin</a>
            </form>
        </div>
    </div>

    <div id="productModal" class="modal-overlay">
        <div class="modal-content product-modal-content">
            <div class="modal-header">
                <h3 id="productModalTitle">Add Product</h3>
                <button type="button" class="close-btn" onclick="closeProductModal()">&times;</button>
            </div>
            <form method="POST" action="inventory.php" id="productForm" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="productFormAction" value="add_product">
                <input type="hidden" name="product_id" id="productId" value="">
                
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" id="prodName" class="form-input" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>SKU</label>
                        <input type="text" name="sku" id="prodSku" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="prodCategory" class="form-input" required>
                            <option value="Oils & Tinctures">Oils & Tinctures</option>
                            <option value="Edibles & Wellness">Edibles & Wellness</option>
                            <option value="Topicals">Topicals</option>
                            <option value="Pet Care">Pet Care</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Regular Price (MRP ₹)</label>
                        <input type="number" name="mrp" id="prodMrp" class="form-input" min="0" step="1" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label>Sale Price (₹)</label>
                        <input type="number" name="price" id="prodPrice" class="form-input" min="0" step="1" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Sale / Hot Deal Name</label>
                        <input type="text" name="sale_badge" id="prodSaleBadge" class="form-input" placeholder="e.g. SUMMER SALE">
                    </div>
                    <div class="form-group">
                        <label>Stock Quantity</label>
                        <input type="number" name="stock" id="prodStock" class="form-input" min="0" step="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="prodDescription" class="form-input" rows="3" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Primary Image (Main View)</label>
                        <input type="file" name="img_primary" id="imgPrimary" class="form-input" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label>Secondary Image (Hover View)</label>
                        <input type="file" name="img_secondary" id="imgSecondary" class="form-input" accept="image/*">
                    </div>
                </div>

                <div class="form-group">
                    <label>Remaining Images (Select 1 to 3 images)</label>
                    <input type="file" name="images[]" id="prodImages" class="form-input" multiple accept="image/*">
                    <div id="currentImagesContainer" style="display:flex; gap:10px; margin-top:10px; overflow-x:auto; padding-top:5px;"></div>
                </div>

                <button type="submit" class="btn-solid" style="margin-top: 20px; width: 100%; justify-content:center;">Save Product</button>
            </form>
        </div>
    </div>

    <div id="viewProductModal" class="modal-overlay">
        <div class="modal-content product-modal-content">
            <div class="modal-header">
                <h3>Product Details</h3>
                <button type="button" class="close-btn" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="viewProductDetails">
                </div>
        </div>
    </div>

    <div id="deleteConfirmModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div style="color: #ffb3b3; margin-bottom: 20px;">
                <svg style="width:64px; height:64px; stroke:currentColor; fill:none; stroke-width:2;" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <h3 style="margin:0 0 10px 0; font-size:20px; letter-spacing:1px; text-transform:uppercase;">Confirm Deletion</h3>
            <p style="color:#aaa; font-size:14px; margin:0 0 25px 0; line-height:1.5;">
                Are you sure you want to delete <strong id="deleteProductName" style="color:#fff;"></strong>? This action cannot be undone.
            </p>
            <div style="display:flex; gap:15px; justify-content:center;">
                <button class="btn-outline" style="width:100%; border-color:rgba(255,255,255,0.2); color:#ccc;" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn-solid" style="width:100%; background:rgba(255,0,0,0.2); border-color:rgba(255,0,0,0.5); color:#ffb3b3; justify-content:center;" onclick="executeDelete()">Delete Product</button>
            </div>
        </div>
    </div>

    <script>
        // --- Table Filtering Logic ---
        function filterInventoryTable(forcedStatus = null) {
            const searchInput = document.getElementById('tableSearchInput');
            
            if (forcedStatus === 'All') {
                searchInput.value = '';
                forcedStatus = null;
            } else if (forcedStatus) {
                searchInput.value = forcedStatus;
            }
            
            const filterValue = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('.table-row-item');
            
            rows.forEach(row => {
                const textContent = row.textContent.toLowerCase();
                const statusContent = row.getAttribute('data-status').toLowerCase();
                
                // If it's a forced status click from top cards, match EXACTLY against the data-status attribute
                if (forcedStatus) {
                    if (statusContent === filterValue) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                } else {
                    // Standard text search logic
                    if (textContent.includes(filterValue)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }

        // Trigger filter and scroll down smoothly when clicking metric cards
        function triggerFilter(status) {
            filterInventoryTable(status);
            document.getElementById('inventoryTablePanel').scrollIntoView({ behavior: 'smooth' });
        }


        // Form Validation for 3-5 images constraint across separated inputs
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const action = document.getElementById('productFormAction').value;
            const pri = document.getElementById('imgPrimary').files.length;
            const sec = document.getElementById('imgSecondary').files.length;
            const rem = document.getElementById('prodImages').files.length;
            
            const newlyUploadedTotal = pri + sec + rem;
            const keptExistingTotal = document.querySelectorAll('input[name="existing_images[]"]').length;
            const finalTotal = keptExistingTotal + newlyUploadedTotal;
            
            if (action === 'add_product') {
                if (pri === 0 || sec === 0) {
                    e.preventDefault();
                    alert('Please select both a Primary and Secondary image.');
                } else if (newlyUploadedTotal < 3 || newlyUploadedTotal > 5) {
                    e.preventDefault();
                    alert('Please upload between 3 and 5 images total (Primary, Secondary, and Remaining).');
                }
            } else if (action === 'edit_product') {
                if (finalTotal < 3 || finalTotal > 5) {
                    e.preventDefault();
                    alert('A product must have between 3 and 5 images total. You currently have ' + finalTotal + ' selected/kept.');
                }
            }
        });

        // Admin Profile Modal Logic
        function openAdminModal() {
            document.getElementById('adminModal').style.display = 'flex';
        }
        function closeAdminModal() {
            document.getElementById('adminModal').style.display = 'none';
        }

        // View Product Modal Logic
        function viewProduct(data) {
            let imagesHtml = '';
            if (data.images && data.images.length > 0) {
                data.images.forEach(img => {
                    imagesHtml += `<img src="${img}" style="width:80px; height:80px; object-fit:cover; border-radius:8px; border:1px solid rgba(255,255,255,0.2);">`;
                });
            } else if (data.img) {
                imagesHtml = `<img src="${data.img}" style="width:80px; height:80px; object-fit:cover; border-radius:8px; border:1px solid rgba(255,255,255,0.2);">`;
            }

            const statusClass = data.status === 'Active' ? 'status-active' : (data.status === 'Low Stock' ? 'status-low' : 'status-out');

            let priceDisplay = `<div style="font-size:22px; color:#e5c378; font-weight:bold; margin-bottom:12px;">${data.price}</div>`;
            let saleBadgeHtml = '';

            let showBadge = (data.mrp && data.mrp !== data.price) || data.sale_badge;
            let badgeText = data.sale_badge ? data.sale_badge : 'SALE';

            if (data.mrp && data.mrp !== data.price) {
                priceDisplay = `
                    <div style="font-size:14px; color:rgba(255,255,255,0.5); text-decoration:line-through; margin-bottom:2px;">${data.mrp}</div>
                    <div style="font-size:22px; color:#e5c378; font-weight:bold; margin-bottom:12px;">${data.price}</div>
                `;
            }
            if (showBadge) {
                saleBadgeHtml = `<div style="position: absolute; top: 10px; left: 10px; background: linear-gradient(135deg, #ff00ff 0%, #8a2be2 100%); color: #fff; padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: bold; z-index: 10; box-shadow: 0 2px 5px rgba(255,0,255,0.4); text-transform: uppercase;">${badgeText}</div>`;
            }

            const html = `
                <div style="display:flex; gap:20px; margin-bottom:20px; flex-wrap:wrap;">
                    <div style="flex-shrink:0; position:relative;">
                        ${saleBadgeHtml}
                        <img src="${data.img}" style="width:120px; height:120px; object-fit:cover; border-radius:12px; border:1px solid rgba(255,0,255,0.3); box-shadow: 0 0 15px rgba(255,0,255,0.1);">
                    </div>
                    <div style="flex-grow:1;">
                        <h2 style="margin:0 0 8px 0; font-size:20px;">${data.name}</h2>
                        <div style="color:#aaa; font-size:13px; margin-bottom:12px;">SKU: <strong style="color:#ccc;">${data.sku}</strong> | Category: <strong style="color:#ccc;">${data.category}</strong></div>
                        ${priceDisplay}
                        <span class="status-badge ${statusClass}">${data.status}</span>
                        <span style="font-size:14px; margin-left:10px; font-weight:600; color:#ccc;">Stock: <span style="color:#fff;">${data.stock} units</span></span>
                    </div>
                </div>
                <div style="margin-bottom:20px;">
                    <h4 style="margin:0 0 10px 0; font-size:13px; text-transform:uppercase; letter-spacing:1px; color:#aaa; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:5px;">Description</h4>
                    <p style="font-size:14px; line-height:1.6; color:#ccc; margin:0;">${data.description || 'No description available.'}</p>
                </div>
                <div>
                    <h4 style="margin:0 0 10px 0; font-size:13px; text-transform:uppercase; letter-spacing:1px; color:#aaa; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:5px;">Product Gallery</h4>
                    <div style="display:flex; gap:15px; overflow-x:auto; padding-bottom:10px;">
                        ${imagesHtml}
                    </div>
                </div>
            `;
            document.getElementById('viewProductDetails').innerHTML = html;
            document.getElementById('viewProductModal').style.display = 'flex';
        }

        function closeViewModal() {
            document.getElementById('viewProductModal').style.display = 'none';
        }

        // Product CRUD Modal Logic
        function openProductModal(mode, data = null) {
            document.getElementById('productModal').style.display = 'flex';
            const form = document.getElementById('productForm');
            form.reset();
            const imagesContainer = document.getElementById('currentImagesContainer');
            imagesContainer.innerHTML = ''; 
            
            if (mode === 'add') {
                document.getElementById('productModalTitle').innerText = 'Add Product';
                document.getElementById('productFormAction').value = 'add_product';
                document.getElementById('productId').value = '';
                document.getElementById('prodMrp').value = '';
                document.getElementById('prodSaleBadge').value = '';
            } else if (mode === 'edit' && data) {
                document.getElementById('productModalTitle').innerText = 'Edit Product';
                document.getElementById('productFormAction').value = 'edit_product';
                document.getElementById('productId').value = data.id;
                
                document.getElementById('prodName').value = data.name;
                document.getElementById('prodSku').value = data.sku;
                document.getElementById('prodCategory').value = data.category;
                
                let rawPrice = (data.raw_price !== undefined) ? data.raw_price : (data.price ? data.price.toString().replace(/[^0-9.]/g, '') : '');
                document.getElementById('prodPrice').value = rawPrice;

                let rawMrp = (data.raw_mrp !== undefined && data.raw_mrp > 0) ? data.raw_mrp : (data.mrp ? data.mrp.toString().replace(/[^0-9.]/g, '') : '');
                document.getElementById('prodMrp').value = rawMrp || '';
                
                document.getElementById('prodSaleBadge').value = data.sale_badge || '';
                
                document.getElementById('prodStock').value = data.stock;
                document.getElementById('prodDescription').value = data.description || '';

                if (data.images && Array.isArray(data.images)) {
                    data.images.forEach((imgSrc) => {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'img-wrapper';

                        const img = document.createElement('img');
                        img.src = imgSrc;
                        img.className = 'image-preview-thumbnail';
                        img.title = 'Current Image';

                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'existing_images[]';
                        hiddenInput.value = imgSrc;

                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'img-remove-btn';
                        removeBtn.innerHTML = '✕';
                        removeBtn.title = 'Remove Image';
                        
                        removeBtn.onclick = function() {
                            wrapper.remove();
                        };

                        wrapper.appendChild(img);
                        wrapper.appendChild(hiddenInput);
                        wrapper.appendChild(removeBtn);
                        imagesContainer.appendChild(wrapper);
                    });
                }
            }
        }

        function closeProductModal() {
            document.getElementById('productModal').style.display = 'none';
        }

        // Beautiful Delete Confirmation Modal Logic
        let productToDeleteId = null;

        function confirmDeleteModal(id, name) {
            productToDeleteId = id;
            document.getElementById('deleteProductName').innerText = name;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            productToDeleteId = null;
        }

        function executeDelete() {
            if(productToDeleteId !== null) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'inventory.php';
                form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="product_id" value="'+productToDeleteId+'">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Notification System Logic
        const notifBtn = document.getElementById('notifBtn');
        const notifDropdown = document.getElementById('notifDropdown');

        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (notifDropdown.style.display === 'flex') {
                notifDropdown.style.display = 'none';
            } else {
                notifDropdown.style.display = 'flex';
            }
        });

        // Dismiss individual notification
        function dismissNotif(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.transition = 'opacity 0.3s, transform 0.3s, margin 0.3s, height 0.3s, padding 0.3s';
                el.style.opacity = '0';
                el.style.transform = 'translateX(10px)';
                setTimeout(() => {
                    el.style.display = 'none';
                    
                    // Check if any notifications remain, if not show empty message
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
                        
                        // Clear the red dot indicator
                        const notifDot = notifBtn.querySelector('.notification-dot');
                        if (notifDot) notifDot.style.display = 'none';
                    } else {
                        // Update numeric count
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

        // Close modals and dropdowns when clicking outside
        window.onclick = function(event) {
            const adminModal = document.getElementById('adminModal');
            const productModal = document.getElementById('productModal');
            const viewModal = document.getElementById('viewProductModal');
            const deleteModal = document.getElementById('deleteConfirmModal');
            
            if (event.target === adminModal) closeAdminModal();
            if (event.target === productModal) closeProductModal();
            if (event.target === viewModal) closeViewModal();
            if (event.target === deleteModal) closeDeleteModal();
            
            // Close Notification Dropdown if clicking outside
            if (!notifBtn.contains(event.target) && !notifDropdown.contains(event.target)) {
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