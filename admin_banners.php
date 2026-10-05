<?php
// admin_banners.php - Hero & Promotional Banners Management
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

Auth::requireAdmin();

$db = Database::getInstance();
$storeName = Settings::get('store_name', 'KAMS HEMP');

$alertSuccess = '';
$alertError = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $alertError = "Security token mismatch. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. ADD BANNER
        if ($action === 'add_banner') {
            $title = trim($_POST['title'] ?? '');
            $subtitle = trim($_POST['subtitle'] ?? '');
            $badge = trim($_POST['badge'] ?? '');
            $linkUrl = trim($_POST['link_url'] ?? 'cbd-products.php');
            $buttonText = trim($_POST['button_text'] ?? 'Explore Now');
            $position = in_array($_POST['position'] ?? '', ['hero', 'promo', 'footer_bar']) ? $_POST['position'] : 'hero';
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';
            $imageUrl = trim($_POST['image_url'] ?? '');

            // File upload
            if (!empty($_FILES['image_file']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $newName = 'banner_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $dest = __DIR__ . '/uploads/' . $newName;
                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) {
                        $imageUrl = 'uploads/' . $newName;
                    }
                }
            }

            if (empty($title)) {
                $alertError = "Banner title is required.";
            } else {
                $stmt = $db->prepare("
                    INSERT INTO banners (title, subtitle, badge, image_url, link_url, button_text, position, sort_order, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$title, $subtitle, $badge, $imageUrl, $linkUrl, $buttonText, $position, $sortOrder, $status]);
                $alertSuccess = "Banner added successfully!";
            }
        }
        // 2. EDIT BANNER
        elseif ($action === 'edit_banner') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $subtitle = trim($_POST['subtitle'] ?? '');
            $badge = trim($_POST['badge'] ?? '');
            $linkUrl = trim($_POST['link_url'] ?? 'cbd-products.php');
            $buttonText = trim($_POST['button_text'] ?? 'Explore Now');
            $position = in_array($_POST['position'] ?? '', ['hero', 'promo', 'footer_bar']) ? $_POST['position'] : 'hero';
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';
            $imageUrl = trim($_POST['image_url'] ?? '');

            if (!empty($_FILES['image_file']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $newName = 'banner_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $dest = __DIR__ . '/uploads/' . $newName;
                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) {
                        $imageUrl = 'uploads/' . $newName;
                    }
                }
            }

            if ($id > 0 && !empty($title)) {
                if (!empty($imageUrl)) {
                    $stmt = $db->prepare("
                        UPDATE banners 
                        SET title = ?, subtitle = ?, badge = ?, image_url = ?, link_url = ?, button_text = ?, position = ?, sort_order = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $subtitle, $badge, $imageUrl, $linkUrl, $buttonText, $position, $sortOrder, $status, $id]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE banners 
                        SET title = ?, subtitle = ?, badge = ?, link_url = ?, button_text = ?, position = ?, sort_order = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $subtitle, $badge, $linkUrl, $buttonText, $position, $sortOrder, $status, $id]);
                }
                $alertSuccess = "Banner updated successfully!";
            } else {
                $alertError = "Failed to update banner.";
            }
        }
        // 3. TOGGLE STATUS
        elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $cur = $db->prepare("SELECT status FROM banners WHERE id = ?");
                $cur->execute([$id]);
                $currentStatus = $cur->fetchColumn();
                $newStatus = ($currentStatus === 'Active') ? 'Inactive' : 'Active';
                $db->prepare("UPDATE banners SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                $alertSuccess = "Banner status switched to {$newStatus}.";
            }
        }
        // 4. DELETE BANNER
        elseif ($action === 'delete_banner') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM banners WHERE id = ?")->execute([$id]);
                $alertSuccess = "Banner deleted successfully.";
            }
        }
    }
}

// Fetch Metrics
$totalBanners = (int)$db->query("SELECT COUNT(*) FROM banners")->fetchColumn();
$activeBanners = (int)$db->query("SELECT COUNT(*) FROM banners WHERE status = 'Active'")->fetchColumn();
$heroBanners = (int)$db->query("SELECT COUNT(*) FROM banners WHERE position = 'hero'")->fetchColumn();
$promoBanners = (int)$db->query("SELECT COUNT(*) FROM banners WHERE position = 'promo'")->fetchColumn();

// Filter
$filterPos = trim($_GET['position'] ?? 'All');
$query = "SELECT * FROM banners WHERE 1=1";
$params = [];

if ($filterPos !== 'All' && !empty($filterPos)) {
    $query .= " AND position = ?";
    $params[] = $filterPos;
}

$query .= " ORDER BY position ASC, sort_order ASC, id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$banners = $stmt->fetchAll();

$currentAdminPage = 'banners';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hero & Banner Management | <?= htmlspecialchars($storeName) ?> Admin</title>
    <style>
        :root {
            --admin-bg: #07090e;
            --admin-surface: #0e121a;
            --admin-card: rgba(18, 24, 38, 0.7);
            --admin-border: rgba(255, 255, 255, 0.08);
            --admin-border-focus: rgba(0, 255, 204, 0.4);
            --accent-cyan: #00ffcc;
            --accent-purple: #8a2be2;
            --accent-gold: #e5c378;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --font-main: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --font-display: "Cinzel", Georgia, serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--admin-bg);
            color: var(--text-primary);
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
        }

        .admin-sidebar {
            width: 270px;
            background: rgba(12, 14, 20, 0.95);
            border-right: 1px solid var(--admin-border);
            display: flex;
            flex-direction: column;
            padding: 24px 0;
            flex-shrink: 0;
            min-height: 100vh;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .brand-logo {
            padding: 0 24px 20px;
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            text-decoration: none;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--admin-border);
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .brand-logo span {
            font-size: 11px;
            color: var(--accent-cyan);
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .admin-sidebar-close {
            display: none;
            position: absolute;
            top: 15px;
            right: 15px;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 16px 12px;
            flex-grow: 1;
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(0, 255, 204, 0.08);
            color: var(--accent-cyan);
        }
        .nav-link.active {
            border: 1px solid rgba(0, 255, 204, 0.25);
            font-weight: 600;
        }
        .nav-link svg {
            width: 17px;
            height: 17px;
            stroke-width: 2;
            fill: none;
            stroke: currentColor;
            flex-shrink: 0;
        }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--admin-border);
        }
        .admin-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-purple));
            color: #000;
            font-weight: 800;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-main {
            flex-grow: 1;
            padding: 32px 40px;
            overflow-y: auto;
            max-width: calc(100vw - 270px);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #fff;
        }
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #00ffcc 0%, #00b4d8 100%);
            color: #000;
        }
        .btn-primary:hover {
            box-shadow: 0 0 20px rgba(0, 255, 204, 0.4);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--admin-border);
            color: #fff;
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .btn-danger {
            background: rgba(255, 77, 77, 0.15);
            border: 1px solid rgba(255, 77, 77, 0.3);
            color: #ff6666;
        }
        .btn-danger:hover {
            background: rgba(255, 77, 77, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--admin-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--admin-border);
            border-radius: 14px;
            padding: 20px;
        }
        .stat-val {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .stat-lbl {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
        }

        .filter-bar {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            gap: 14px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-input {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            padding: 10px 14px;
            color: #fff;
            font-size: 13.5px;
            outline: none;
        }

        .data-table-container {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: 14px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }
        th {
            background: rgba(255, 255, 255, 0.02);
            color: var(--text-secondary);
            font-weight: 600;
            padding: 14px 18px;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--admin-border);
        }
        td {
            padding: 16px 18px;
            border-bottom: 1px solid var(--admin-border);
            vertical-align: middle;
            color: #e2e8f0;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-active {
            background: rgba(74, 222, 128, 0.15);
            color: #4ade80;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }
        .badge-inactive {
            background: rgba(148, 163, 184, 0.15);
            color: #94a3b8;
            border: 1px solid rgba(148, 163, 184, 0.3);
        }

        .banner-thumb {
            width: 90px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            background: #1e293b;
            border: 1px solid var(--admin-border);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-card {
            background: #0f141f;
            border: 1px solid var(--admin-border);
            border-radius: 18px;
            width: 100%;
            max-width: 660px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.8);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .modal-title {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .form-input {
            width: 100%;
            background: rgba(0,0,0,0.4);
            border: 1px solid var(--admin-border);
            border-radius: 10px;
            padding: 12px 14px;
            color: #fff;
            font-size: 14px;
            font-family: inherit;
        }
        .form-input:focus {
            border-color: var(--accent-cyan);
            outline: none;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .alert-msg {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background: rgba(74, 222, 128, 0.15);
            border: 1px solid rgba(74, 222, 128, 0.3);
            color: #4ade80;
        }
        .alert-error {
            background: rgba(255, 77, 77, 0.15);
            border: 1px solid rgba(255, 77, 77, 0.3);
            color: #ff6666;
        }

        @media (max-width: 900px) {
            .admin-sidebar {
                position: fixed;
                left: -270px;
                z-index: 100;
                transition: left 0.3s ease;
            }
            .admin-sidebar.show {
                left: 0;
            }
            .admin-sidebar-close {
                display: block;
            }
            .admin-main {
                max-width: 100vw;
                padding: 20px;
            }
            .mobile-toggle {
                display: inline-flex !important;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 14px;">
                <button type="button" class="btn btn-secondary mobile-toggle" style="display:none;" onclick="document.getElementById('mainAdminSidebar').classList.toggle('show')">
                    ☰ Menu
                </button>
                <div>
                    <h1 class="page-title">Hero & Banner Management</h1>
                    <p style="color: var(--text-secondary); font-size: 13.5px; margin-top: 4px;">
                        Manage homepage hero sliders, promotional highlight cards, call-to-actions, and discount banners.
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <a href="cbd.php" target="_blank" class="btn btn-secondary">
                    View Live Homepage ↗
                </a>
                <button type="button" class="btn btn-primary" onclick="openAddModal()">
                    + Add New Banner
                </button>
            </div>
        </div>

        <?php if (!empty($alertSuccess)): ?>
            <div class="alert-msg alert-success"><?= htmlspecialchars($alertSuccess) ?></div>
        <?php endif; ?>
        <?php if (!empty($alertError)): ?>
            <div class="alert-msg alert-error"><?= htmlspecialchars($alertError) ?></div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-val" style="color: #fff;"><?= $totalBanners ?></div>
                <div class="stat-lbl">Total Banners</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #4ade80;"><?= $activeBanners ?></div>
                <div class="stat-lbl">Active & Live</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: var(--accent-cyan);"><?= $heroBanners ?></div>
                <div class="stat-lbl">Hero Slides</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: var(--accent-purple);"><?= $promoBanners ?></div>
                <div class="stat-lbl">Promotional Cards</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="admin_banners.php" class="filter-bar">
            <label style="font-size: 13px; color: var(--text-secondary);">Position:</label>
            <select name="position" class="filter-input" onchange="this.form.submit()">
                <option value="All" <?= $filterPos === 'All' ? 'selected' : '' ?>>All Placements</option>
                <option value="hero" <?= $filterPos === 'hero' ? 'selected' : '' ?>>Hero Slider</option>
                <option value="promo" <?= $filterPos === 'promo' ? 'selected' : '' ?>>Promo Section</option>
                <option value="footer_bar" <?= $filterPos === 'footer_bar' ? 'selected' : '' ?>>Footer Highlights</option>
            </select>
            <?php if ($filterPos !== 'All'): ?>
                <a href="admin_banners.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Banners Table -->
        <div class="data-table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 110px;">Preview</th>
                        <th>Banner Content</th>
                        <th style="width: 110px;">Position</th>
                        <th style="width: 80px;">Order</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 180px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($banners)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                No banners found. Click "+ Add New Banner" to create a featured hero slider or promo card.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($banners as $b): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($b['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($b['image_url']) ?>" alt="banner" class="banner-thumb" onerror="this.src='logo.png'">
                                    <?php else: ?>
                                        <div class="banner-thumb" style="display:flex; align-items:center; justify-content:center; color:#555; font-size:11px;">No Img</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($b['badge'])): ?>
                                        <span style="display: inline-block; font-size: 10px; font-weight: 800; color: var(--accent-cyan); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 4px;">
                                            <?= htmlspecialchars($b['badge']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <strong style="color: #fff; font-size: 15px; display: block;"><?= htmlspecialchars($b['title']) ?></strong>
                                    <?php if (!empty($b['subtitle'])): ?>
                                        <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;">
                                            <?= htmlspecialchars($b['subtitle']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="font-size: 11px; color: var(--accent-gold); margin-top: 4px;">
                                        CTA: <?= htmlspecialchars($b['button_text'] ?: 'Shop Now') ?> → <?= htmlspecialchars($b['link_url'] ?: '#') ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: #fff; background: rgba(255,255,255,0.06); padding: 4px 10px; border-radius: 6px;">
                                        <?= htmlspecialchars($b['position']) ?>
                                    </span>
                                </td>
                                <td style="font-weight: bold; color: #fff;"><?= (int)$b['sort_order'] ?></td>
                                <td>
                                    <span class="badge-status badge-<?= strtolower($b['status']) ?>">
                                        <?= htmlspecialchars($b['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px;" onclick='openEditModal(<?= json_encode($b) ?>)'>
                                        Edit
                                    </button>

                                    <form method="POST" action="admin_banners.php" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px;">
                                            <?= $b['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <form method="POST" action="admin_banners.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this banner?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_banner">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;">
                                            ✕
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal for Add / Edit Banner -->
    <div class="modal" id="bannerModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New Promotional Banner</h3>
                <button type="button" onclick="closeBannerModal()" style="background:transparent; border:none; color:#aaa; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" action="admin_banners.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="bannerFormAction" value="add_banner">
                <input type="hidden" name="id" id="bannerId" value="">

                <div class="form-group">
                    <label>Main Headline Title *</label>
                    <input type="text" name="title" id="banTitle" class="form-input" placeholder="e.g. Pure Himalayan Vijaya Extract" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Subheading / Description</label>
                        <input type="text" name="subtitle" id="banSubtitle" class="form-input" placeholder="e.g. Cultivated at 8,000 ft, NABL Lab Certified">
                    </div>
                    <div class="form-group">
                        <label>Badge / Tagline (Optional)</label>
                        <input type="text" name="badge" id="banBadge" class="form-input" placeholder="e.g. 100% AYUSH CERTIFIED">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Button Label</label>
                        <input type="text" name="button_text" id="banBtnText" class="form-input" placeholder="e.g. Explore Oils" value="Explore Now">
                    </div>
                    <div class="form-group">
                        <label>Target URL Link</label>
                        <input type="text" name="link_url" id="banLinkUrl" class="form-input" placeholder="e.g. cbd-products.php" value="cbd-products.php">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Placement Position *</label>
                        <select name="position" id="banPosition" class="form-input">
                            <option value="hero">Hero Main Slider</option>
                            <option value="promo">Promotional Banner Section</option>
                            <option value="footer_bar">Footer Bar Highlight</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Display Sort Order</label>
                        <input type="number" name="sort_order" id="banSortOrder" class="form-input" value="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="banStatus" class="form-input">
                            <option value="Active">Active (Visible)</option>
                            <option value="Inactive">Inactive (Hidden)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Banner Image URL (or upload below)</label>
                        <input type="text" name="image_url" id="banImageUrl" class="form-input" placeholder="https://... or uploads/...">
                    </div>
                </div>

                <div class="form-group">
                    <label>Upload Banner Image</label>
                    <input type="file" name="image_file" class="form-input" accept="image/*">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary" onclick="closeBannerModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Banner</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('bannerModal');
        const formAction = document.getElementById('bannerFormAction');
        const bannerId = document.getElementById('bannerId');
        const modalTitle = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');

        function openAddModal() {
            formAction.value = 'add_banner';
            bannerId.value = '';
            modalTitle.innerText = 'Add New Promotional Banner';
            submitBtn.innerText = 'Save Banner';
            document.getElementById('banTitle').value = '';
            document.getElementById('banSubtitle').value = '';
            document.getElementById('banBadge').value = '';
            document.getElementById('banBtnText').value = 'Explore Now';
            document.getElementById('banLinkUrl').value = 'cbd-products.php';
            document.getElementById('banPosition').value = 'hero';
            document.getElementById('banSortOrder').value = '0';
            document.getElementById('banStatus').value = 'Active';
            document.getElementById('banImageUrl').value = '';
            modal.style.display = 'flex';
        }

        function openEditModal(ban) {
            formAction.value = 'edit_banner';
            bannerId.value = ban.id;
            modalTitle.innerText = 'Edit Banner #' + ban.id;
            submitBtn.innerText = 'Save Changes';
            document.getElementById('banTitle').value = ban.title || '';
            document.getElementById('banSubtitle').value = ban.subtitle || '';
            document.getElementById('banBadge').value = ban.badge || '';
            document.getElementById('banBtnText').value = ban.button_text || 'Explore Now';
            document.getElementById('banLinkUrl').value = ban.link_url || 'cbd-products.php';
            document.getElementById('banPosition').value = ban.position || 'hero';
            document.getElementById('banSortOrder').value = ban.sort_order || 0;
            document.getElementById('banStatus').value = ban.status || 'Active';
            document.getElementById('banImageUrl').value = ban.image_url || '';
            modal.style.display = 'flex';
        }

        function closeBannerModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(e) {
            if (e.target === modal) {
                closeBannerModal();
            }
        };

        const closeBtn = document.getElementById('adminSidebarCloseBtn');
        if (closeBtn) {
            closeBtn.onclick = function() {
                document.getElementById('mainAdminSidebar').classList.remove('show');
            };
        }
    </script>
</body>
</html>
