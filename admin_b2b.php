<?php
// admin_b2b.php - Dedicated B2B Wholesale Leads & Catalog Management
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/b2b_service.php';

Auth::requireAdmin();

$storeName = Settings::get('store_name', 'KAMS HEMP');
$admin = Auth::getAdmin();
$adminFullName = trim(($admin['first_name'] ?? 'Admin') . ' ' . ($admin['last_name'] ?? ''));
$adminInitials = strtoupper(substr($admin['first_name'] ?? 'A', 0, 1) . substr($admin['last_name'] ?? 'D', 0, 1));

// Action Handling
$alertSuccess = '';
$alertError = '';

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $allLeads = B2BService::getInquiries();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=kams_b2b_wholesale_leads_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Date', 'Company Name', 'Contact Person', 'Email', 'Phone', 'GST Number', 'City', 'State', 'Business Type', 'Monthly Volume', 'Status', 'Admin Notes']);
    foreach ($allLeads as $l) {
        fputcsv($out, [
            $l['id'],
            $l['created_at'],
            $l['company_name'],
            $l['contact_person'],
            $l['email'],
            $l['phone'],
            $l['gst_number'],
            $l['city'],
            $l['state'],
            $l['business_type'],
            $l['estimated_monthly_volume'],
            $l['status'],
            $l['admin_notes']
        ]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $alertError = "Security token mismatch. Please try again.";
    } else {
        $postAction = $_POST['action'] ?? '';

        // 1. Update Lead Status / Notes
        if ($postAction === 'update_lead') {
            $leadId = (int)($_POST['lead_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? 'New');
            $adminNotes = trim($_POST['admin_notes'] ?? '');

            if ($leadId > 0) {
                if (B2BService::updateInquiry($leadId, $newStatus, $adminNotes)) {
                    $alertSuccess = "Lead #B2B-{$leadId} updated successfully!";
                } else {
                    $alertError = "Failed to update lead status.";
                }
            }
        }
        // 2. Delete Lead
        elseif ($postAction === 'delete_lead') {
            $leadId = (int)($_POST['lead_id'] ?? 0);
            if ($leadId > 0) {
                if (B2BService::deleteInquiry($leadId)) {
                    $alertSuccess = "Lead #B2B-{$leadId} deleted successfully.";
                } else {
                    $alertError = "Failed to delete lead.";
                }
            }
        }
        // 3. Upload / Replace B2B Catalog
        elseif ($postAction === 'upload_catalog') {
            $catalogTitle = trim($_POST['catalog_title'] ?? 'KAMS HEMP B2B Wholesale Catalog');
            if (!empty($_FILES['catalog_file']['tmp_name'])) {
                $uploadRes = B2BService::uploadCatalog($_FILES['catalog_file'], $catalogTitle);
                if ($uploadRes['success']) {
                    $alertSuccess = "Wholesale catalog uploaded and published successfully! ({$uploadRes['size']})";
                } else {
                    $alertError = $uploadRes['error'] ?? "Failed to upload catalog.";
                }
            } else {
                Settings::set('b2b_catalog_title', $catalogTitle);
                $alertSuccess = "Catalog settings updated.";
            }

            // Also update contact settings if provided
            if (isset($_POST['b2b_email'])) {
                Settings::set('b2b_contact_email', trim($_POST['b2b_email']));
            }
            if (isset($_POST['b2b_phone'])) {
                Settings::set('b2b_contact_phone', trim($_POST['b2b_phone']));
            }
            if (isset($_POST['b2b_whatsapp'])) {
                Settings::set('b2b_whatsapp_number', trim($_POST['b2b_whatsapp']));
            }
        }
    }
}

// Fetch Stats and Leads
$stats = B2BService::getInquiryStats();
$catalog = B2BService::getCatalogInfo();

$filterStatus = $_GET['status'] ?? 'All';
$filterType = $_GET['type'] ?? 'All';
$searchQuery = trim($_GET['search'] ?? '');

$leads = B2BService::getInquiries([
    'status' => $filterStatus,
    'business_type' => $filterType,
    'search' => $searchQuery
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B2B Wholesale Inquiries & Leads | <?= htmlspecialchars($storeName) ?> Admin</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/responsive.css">

    <style>
        :root {
            --admin-bg: #0b0c10;
            --admin-card: rgba(18, 20, 29, 0.85);
            --admin-border: rgba(255, 255, 255, 0.08);
            --accent-cyan: #00ffcc;
            --accent-gold: #e5c378;
            --accent-green: #25d366;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--admin-bg);
            color: var(--text-primary);
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
        }

        /* Admin Sidebar */
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
            padding: 0 24px 24px;
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

        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 20px 16px;
            flex-grow: 1;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.25s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(0, 255, 204, 0.08);
            color: var(--accent-cyan);
        }

        .nav-link.active {
            border: 1px solid rgba(0, 255, 204, 0.25);
        }

        .nav-link svg {
            width: 18px;
            height: 18px;
            stroke-width: 2;
            fill: none;
            stroke: currentColor;
        }

        .badge-count {
            margin-left: auto;
            background: #ffaa00;
            color: #000;
            font-size: 11px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 999px;
        }

        .sidebar-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--admin-border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-avatar {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #00ffcc, #3b82f6);
            color: #000;
            font-weight: 800;
            font-size: 13px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Main Container */
        .admin-main {
            flex-grow: 1;
            padding: 30px 40px;
            overflow-y: auto;
            max-width: 1500px;
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
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            color: #fff;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00ffcc, #00b386);
            color: #06080c;
            font-weight: 700;
        }

        .btn-primary:hover {
            opacity: 0.9;
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(16px);
        }

        .stat-val {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .stat-lbl {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Catalog Card in Admin */
        .catalog-box {
            background: linear-gradient(135deg, rgba(20, 26, 40, 0.9), rgba(15, 18, 28, 0.9));
            border: 1px solid rgba(0, 255, 204, 0.3);
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .catalog-meta-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .catalog-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(0, 255, 204, 0.1);
            color: var(--accent-cyan);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Filters and Search Bar */
        .filter-bar {
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 9px 14px;
            color: #fff;
            font-size: 13px;
            outline: none;
        }

        .filter-input:focus {
            border-color: var(--accent-cyan);
        }

        /* Table */
        .table-card {
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        .leads-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .leads-table th {
            background: rgba(255, 255, 255, 0.03);
            padding: 14px 18px;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 11px;
            border-bottom: 1px solid var(--admin-border);
        }

        .leads-table td {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            vertical-align: middle;
        }

        .leads-table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge-status {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-status.new { background: rgba(255, 170, 0, 0.15); color: #ffaa00; border: 1px solid rgba(255, 170, 0, 0.3); }
        .badge-status.contacted { background: rgba(96, 165, 250, 0.15); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); }
        .badge-status.in_discussion { background: rgba(192, 132, 252, 0.15); color: #c084fc; border: 1px solid rgba(192, 132, 252, 0.3); }
        .badge-status.approved { background: rgba(74, 222, 128, 0.15); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.3); }
        .badge-status.rejected { background: rgba(248, 113, 113, 0.15); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.3); }

        .btn-wa {
            background: #25d366;
            color: #000;
            padding: 4px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Modal */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-card {
            background: #11141c;
            border: 1px solid var(--admin-border);
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            padding: 30px;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 20px;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <a href="admin.php" class="brand-logo">
            <?= htmlspecialchars($storeName) ?>
            <span>ADMINISTRATION</span>
        </a>

        <nav class="nav-menu">
            <a href="admin.php" class="nav-link">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Dashboard
            </a>
            <a href="admin_orders.php" class="nav-link">
                <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                Orders
            </a>
            <a href="inventory.php" class="nav-link">
                <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                Inventory
            </a>
            <a href="admin_b2b.php" class="nav-link active">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
                B2B Wholesale Leads
                <?php if ($stats['new'] > 0): ?>
                    <span class="badge-count"><?= $stats['new'] ?></span>
                <?php endif; ?>
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
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Users
            </a>
            <a href="analytics.php" class="nav-link">
                <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                Traffic & Analytics
            </a>
            <a href="settings.php" class="nav-link">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Settings
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="admin-avatar"><?= $adminInitials ?></div>
            <div>
                <div style="font-size: 13px; font-weight: 700;"><?= htmlspecialchars($adminFullName) ?></div>
                <div style="font-size: 11px; color: var(--text-secondary);">Administrator</div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">B2B Wholesale Leads & Distribution</h1>
                <p style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">
                    Manage wholesale applications, lead statuses, WhatsApp outreach, and downloadable B2B catalogs.
                </p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn btn-secondary" onclick="openCatalogModal()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                    Manage Wholesale Catalog
                </button>
                <a href="admin_b2b.php?action=export_csv" class="btn btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Export Leads (CSV)
                </a>
            </div>
        </div>

        <?php if (!empty($alertSuccess)): ?>
            <div style="background: rgba(37, 211, 102, 0.15); border: 1px solid #25d366; color: #4ade80; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 14px;">
                <?= htmlspecialchars($alertSuccess) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($alertError)): ?>
            <div style="background: rgba(255, 77, 77, 0.15); border: 1px solid #ff4d4d; color: #ff4d4d; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 14px;">
                <?= htmlspecialchars($alertError) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-val" style="color: #fff;"><?= $stats['total'] ?></div>
                <div class="stat-lbl">Total Wholesale Leads</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #ffaa00;"><?= $stats['new'] ?></div>
                <div class="stat-lbl">New / Pending Leads</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #60a5fa;"><?= $stats['contacted'] + $stats['in_discussion'] ?></div>
                <div class="stat-lbl">Active In Outreach</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #4ade80;"><?= $stats['approved'] ?></div>
                <div class="stat-lbl">Approved B2B Partners</div>
            </div>
        </div>

        <!-- Catalog Status Bar -->
        <div class="catalog-box">
            <div class="catalog-meta-info">
                <div class="catalog-icon">
                    <svg width="26" height="26" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                </div>
                <div>
                    <h3 style="color: #fff; font-size: 16px; margin-bottom: 4px;"><?= htmlspecialchars($catalog['title']) ?></h3>
                    <p style="color: var(--text-secondary); font-size: 13px;">
                        Status: <?= $catalog['exists'] ? "<span style='color:#4ade80; font-weight:bold;'>Active ({$catalog['size']})</span> • Last updated {$catalog['updated']}" : "<span style='color:#ffaa00;'>Not uploaded yet</span>" ?>
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if ($catalog['exists']): ?>
                    <a href="<?= htmlspecialchars($catalog['file_path']) ?>" target="_blank" class="btn btn-secondary">
                        Preview Catalog
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-primary" onclick="openCatalogModal()">
                    Upload / Replace Catalog
                </button>
            </div>
        </div>

        <!-- Filters Form -->
        <form method="GET" action="admin_b2b.php" class="filter-bar">
            <input type="text" name="search" class="filter-input" placeholder="Search company, person, phone, GST..." value="<?= htmlspecialchars($searchQuery) ?>" style="flex-grow: 1; min-width: 200px;">
            
            <select name="status" class="filter-input" onchange="this.form.submit()">
                <option value="All" <?= $filterStatus === 'All' ? 'selected' : '' ?>>All Statuses</option>
                <option value="New" <?= $filterStatus === 'New' ? 'selected' : '' ?>>New</option>
                <option value="Contacted" <?= $filterStatus === 'Contacted' ? 'selected' : '' ?>>Contacted</option>
                <option value="In Discussion" <?= $filterStatus === 'In Discussion' ? 'selected' : '' ?>>In Discussion</option>
                <option value="Approved" <?= $filterStatus === 'Approved' ? 'selected' : '' ?>>Approved</option>
                <option value="Rejected" <?= $filterStatus === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>

            <select name="type" class="filter-input" onchange="this.form.submit()">
                <option value="All" <?= $filterType === 'All' ? 'selected' : '' ?>>All Business Types</option>
                <option value="Retail Pharmacy" <?= $filterType === 'Retail Pharmacy' ? 'selected' : '' ?>>Retail Pharmacy</option>
                <option value="Ayurvedic Clinic / Doctor" <?= $filterType === 'Ayurvedic Clinic / Doctor' ? 'selected' : '' ?>>Clinic / Doctor</option>
                <option value="Distributor / Stockist" <?= $filterType === 'Distributor / Stockist' ? 'selected' : '' ?>>Distributor / Stockist</option>
                <option value="E-Commerce Marketplace" <?= $filterType === 'E-Commerce Marketplace' ? 'selected' : '' ?>>E-Commerce</option>
                <option value="Gym & Fitness Center" <?= $filterType === 'Gym & Fitness Center' ? 'selected' : '' ?>>Gym & Fitness</option>
                <option value="White-Label / OEM" <?= $filterType === 'White-Label / OEM' ? 'selected' : '' ?>>White-Label / OEM</option>
            </select>

            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if (!empty($searchQuery) || $filterStatus !== 'All' || $filterType !== 'All'): ?>
                <a href="admin_b2b.php" class="btn btn-secondary" style="color: #ffaa00;">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Leads Table -->
        <div class="table-card">
            <table class="leads-table">
                <thead>
                    <tr>
                        <th>ID & Date</th>
                        <th>Company & Contact</th>
                        <th>Contact Channels</th>
                        <th>Location & GST</th>
                        <th>Type & Volume</th>
                        <th>Documents</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                No wholesale inquiries found matching your filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leads as $lead): 
                            $cleanPhone = preg_replace('/[^0-9]/', '', $lead['phone']);
                            if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
                            $waMsg = urlencode("Hello {$lead['contact_person']}! This is {$adminFullName} from {$storeName} Wholesale Team regarding your B2B application #B2B-{$lead['id']}.");
                            $waLink = "https://api.whatsapp.com/send?phone={$cleanPhone}&text={$waMsg}";
                            $statusSlug = strtolower(str_replace(' ', '_', $lead['status']));
                        ?>
                            <tr>
                                <td>
                                    <strong style="color: #fff;">#B2B-<?= $lead['id'] ?></strong><br>
                                    <span style="color: var(--text-secondary); font-size: 11px;"><?= date('M d, Y', strtotime($lead['created_at'])) ?></span>
                                </td>
                                <td>
                                    <strong style="color: #00ffcc; font-size: 14px;"><?= htmlspecialchars($lead['company_name']) ?></strong><br>
                                    <span style="color: #cbd5e1;"><?= htmlspecialchars($lead['contact_person']) ?></span>
                                </td>
                                <td>
                                    <div><a href="mailto:<?= htmlspecialchars($lead['email']) ?>" style="color: #60a5fa; text-decoration: none;"><?= htmlspecialchars($lead['email']) ?></a></div>
                                    <div style="margin-top: 4px; display: flex; align-items: center; gap: 8px;">
                                        <a href="tel:<?= htmlspecialchars($lead['phone']) ?>" style="color: #cbd5e1; text-decoration: none;"><?= htmlspecialchars($lead['phone']) ?></a>
                                        <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" class="btn-wa">
                                            WhatsApp
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($lead['city']) ?>, <?= htmlspecialchars($lead['state']) ?></div>
                                    <small style="color: var(--accent-gold); font-family: monospace;"><?= htmlspecialchars($lead['gst_number'] ?: 'No GST') ?></small>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($lead['business_type']) ?></div>
                                    <small style="color: var(--text-secondary);"><?= htmlspecialchars($lead['estimated_monthly_volume'] ?: 'Not specified') ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($lead['attachment_path'])): ?>
                                        <a href="<?= htmlspecialchars($lead['attachment_path']) ?>" target="_blank" style="color: #4ade80; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            View Doc
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #64748b;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $statusSlug ?>">
                                        <?= htmlspecialchars($lead['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick='openLeadModal(<?= json_encode($lead) ?>)'>
                                        Manage Lead
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Lead Management Modal -->
    <div class="modal-backdrop" id="leadModal">
        <div class="modal-card">
            <button class="modal-close" onclick="closeLeadModal()">×</button>
            <h2 id="modalLeadTitle" style="font-family: var(--font-display); font-size: 20px; margin-bottom: 16px;">Manage B2B Lead</h2>
            
            <div id="modalLeadDetails" style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 16px; margin-bottom: 20px; font-size: 13px; line-height: 1.6; color: #cbd5e1;"></div>

            <form method="POST" action="admin_b2b.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_lead">
                <input type="hidden" name="lead_id" id="modalLeadId" value="">

                <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 20px;">
                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px;">Update Lead Status</label>
                        <select name="status" id="modalLeadStatus" class="filter-input" style="width: 100%;">
                            <option value="New">New</option>
                            <option value="Contacted">Contacted</option>
                            <option value="In Discussion">In Discussion</option>
                            <option value="Approved">Approved (Wholesale Partner)</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>

                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px;">Internal Admin Notes</label>
                        <textarea name="admin_notes" id="modalLeadNotes" class="filter-input" style="width: 100%; min-height: 80px; resize: vertical;" placeholder="Add internal follow-up notes, discount agreements, or call summaries..."></textarea>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn" style="background: rgba(255,77,77,0.15); color: #ff4d4d;" onclick="deleteCurrentLead()">Delete Lead</button>
                </div>
            </form>

            <form method="POST" action="admin_b2b.php" id="deleteLeadForm" style="display: none;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_lead">
                <input type="hidden" name="lead_id" id="deleteLeadId" value="">
            </form>
        </div>
    </div>

    <!-- Catalog Upload Modal -->
    <div class="modal-backdrop" id="catalogModal">
        <div class="modal-card">
            <button class="modal-close" onclick="closeCatalogModal()">×</button>
            <h2 style="font-family: var(--font-display); font-size: 20px; margin-bottom: 16px;">Wholesale Catalog & B2B Settings</h2>

            <form method="POST" action="admin_b2b.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_catalog">

                <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px;">Catalog Display Title</label>
                        <input type="text" name="catalog_title" class="filter-input" style="width: 100%;" value="<?= htmlspecialchars($catalog['title']) ?>" required>
                    </div>

                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px;">Upload New Catalog File (PDF / Image, Max 50MB)</label>
                        <input type="file" name="catalog_file" class="filter-input" style="width: 100%;" accept=".pdf,.png,.jpg,.jpeg,.webp">
                        <small style="color: var(--text-secondary); font-size: 11px;">Current: <?= $catalog['exists'] ? htmlspecialchars($catalog['file_path']) . " ({$catalog['size']})" : 'None uploaded' ?></small>
                    </div>

                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px;">B2B Desk Contact Email</label>
                        <input type="email" name="b2b_email" class="filter-input" style="width: 100%;" value="<?= htmlspecialchars(Settings::get('b2b_contact_email', 'b2b@kamshemp.com')) ?>">
                    </div>

                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px;">B2B Desk Phone</label>
                        <input type="text" name="b2b_phone" class="filter-input" style="width: 100%;" value="<?= htmlspecialchars(Settings::get('b2b_contact_phone', '+91 98765 43210')) ?>">
                    </div>

                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px;">B2B WhatsApp Number (With Country Code)</label>
                        <input type="text" name="b2b_whatsapp" class="filter-input" style="width: 100%;" value="<?= htmlspecialchars(Settings::get('b2b_whatsapp_number', '919876543210')) ?>">
                    </div>
                </div>

                <div style="text-align: right;">
                    <button type="submit" class="btn btn-primary">Save & Upload Catalog</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openLeadModal(lead) {
            document.getElementById('modalLeadId').value = lead.id;
            document.getElementById('deleteLeadId').value = lead.id;
            document.getElementById('modalLeadTitle').innerText = 'Manage Lead #' + lead.id + ' - ' + lead.company_name;
            document.getElementById('modalLeadStatus').value = lead.status;
            document.getElementById('modalLeadNotes').value = lead.admin_notes || '';

            let detailsHtml = `
                <div><strong>Contact:</strong> ${lead.contact_person} (${lead.phone} | ${lead.email})</div>
                <div><strong>Location:</strong> ${lead.city}, ${lead.state}</div>
                <div><strong>GSTIN:</strong> ${lead.gst_number || 'N/A'}</div>
                <div><strong>Business Type:</strong> ${lead.business_type}</div>
                <div><strong>Est. Volume:</strong> ${lead.estimated_monthly_volume || 'N/A'}</div>
                ${lead.message ? `<div style="margin-top:8px; background:rgba(0,0,0,0.3); padding:8px; border-radius:6px;"><strong>Requirement:</strong> ${lead.message}</div>` : ''}
            `;
            document.getElementById('modalLeadDetails').innerHTML = detailsHtml;
            document.getElementById('leadModal').style.display = 'flex';
        }

        function closeLeadModal() {
            document.getElementById('leadModal').style.display = 'none';
        }

        function deleteCurrentLead() {
            if (confirm('Are you sure you want to delete this B2B inquiry? This cannot be undone.')) {
                document.getElementById('deleteLeadForm').submit();
            }
        }

        function openCatalogModal() {
            document.getElementById('catalogModal').style.display = 'flex';
        }

        function closeCatalogModal() {
            document.getElementById('catalogModal').style.display = 'none';
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-backdrop')) {
                e.target.style.display = 'none';
            }
        };
    </script>
</body>
</html>
