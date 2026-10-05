<?php
/**
 * Universal Admin Sidebar Component
 * KAMS HEMP Luxury Administration System
 * 
 * Usage:
 * $currentAdminPage = 'dashboard'; // or 'orders', 'inventory', 'b2b', 'blogs', 'faqs', 'testimonials', 'banners', 'contact', 'users', 'analytics', 'settings'
 * include __DIR__ . '/includes/admin_sidebar.php';
 */

if (!isset($db)) {
    require_once __DIR__ . '/db.php';
    $db = Database::getInstance();
}

// Ensure admin session data
$adminInfo = $_SESSION['admin_user'] ?? [
    'first_name' => 'Admin',
    'last_name' => 'User',
    'email' => 'admin@kamshemp.com'
];
$adminName = trim(($adminInfo['first_name'] ?? 'Admin') . ' ' . ($adminInfo['last_name'] ?? ''));
$adminInitials = strtoupper(substr($adminInfo['first_name'] ?? 'A', 0, 1) . substr($adminInfo['last_name'] ?? 'D', 0, 1));

// Calculate Dynamic Badges
$sidebarBadges = [
    'orders' => 0,
    'b2b' => 0,
    'contact' => 0,
    'testimonials' => 0
];

try {
    $sidebarBadges['orders'] = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Processing', 'Pending', 'New')")->fetchColumn();
} catch (\Throwable $e) {}

try {
    $sidebarBadges['b2b'] = (int)$db->query("SELECT COUNT(*) FROM b2b_inquiries WHERE status = 'New'")->fetchColumn();
} catch (\Throwable $e) {}

try {
    $sidebarBadges['contact'] = (int)$db->query("SELECT COUNT(*) FROM contact_submissions WHERE status = 'New'")->fetchColumn();
} catch (\Throwable $e) {}

try {
    $sidebarBadges['testimonials'] = (int)$db->query("SELECT COUNT(*) FROM testimonials WHERE status = 'Pending'")->fetchColumn();
} catch (\Throwable $e) {}

$active = $currentAdminPage ?? 'dashboard';
?>

<aside class="admin-sidebar" id="mainAdminSidebar">
    <button type="button" class="admin-sidebar-close" id="adminSidebarCloseBtn" aria-label="Close admin menu">✕</button>
    <a href="cbd.php" class="brand-logo" target="_blank" title="View Storefront">
        KAMS HEMP
        <span>ADMINISTRATION</span>
    </a>

    <nav class="nav-menu">
        <a href="admin.php" class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            <span>Dashboard</span>
        </a>

        <a href="admin_orders.php" class="nav-link <?= $active === 'orders' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
            <span>Orders</span>
            <?php if ($sidebarBadges['orders'] > 0): ?>
                <span class="badge-count" style="background: #e5c378; color: #000; font-weight: 800; font-size: 11px; padding: 2px 7px; border-radius: 999px; margin-left: auto;"><?= $sidebarBadges['orders'] ?></span>
            <?php endif; ?>
        </a>

        <a href="inventory.php" class="nav-link <?= $active === 'inventory' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            <span>Inventory</span>
        </a>

        <a href="admin_b2b.php" class="nav-link <?= $active === 'b2b' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
            <span>B2B Wholesale</span>
            <?php if ($sidebarBadges['b2b'] > 0): ?>
                <span class="badge-count" style="background: #00ffcc; color: #000; font-weight: 800; font-size: 11px; padding: 2px 7px; border-radius: 999px; margin-left: auto;"><?= $sidebarBadges['b2b'] ?></span>
            <?php endif; ?>
        </a>

        <div style="height: 1px; background: rgba(255,255,255,0.08); margin: 6px 12px;"></div>

        <a href="admin_blogs.php" class="nav-link <?= $active === 'blogs' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            <span>Blogs & Science</span>
        </a>

        <a href="admin_faqs.php" class="nav-link <?= $active === 'faqs' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <span>FAQs</span>
        </a>

        <a href="admin_testimonials.php" class="nav-link <?= $active === 'testimonials' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            <span>Testimonials</span>
            <?php if ($sidebarBadges['testimonials'] > 0): ?>
                <span class="badge-count" style="background: #ffaa00; color: #000; font-weight: 800; font-size: 11px; padding: 2px 7px; border-radius: 999px; margin-left: auto;"><?= $sidebarBadges['testimonials'] ?></span>
            <?php endif; ?>
        </a>

        <a href="admin_banners.php" class="nav-link <?= $active === 'banners' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
            <span>Hero & Banners</span>
        </a>

        <a href="admin_contact.php" class="nav-link <?= $active === 'contact' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            <span>Contact Leads</span>
            <?php if ($sidebarBadges['contact'] > 0): ?>
                <span class="badge-count" style="background: #ff4d4d; color: #fff; font-weight: 800; font-size: 11px; padding: 2px 7px; border-radius: 999px; margin-left: auto;"><?= $sidebarBadges['contact'] ?></span>
            <?php endif; ?>
        </a>

        <div style="height: 1px; background: rgba(255,255,255,0.08); margin: 6px 12px;"></div>

        <a href="users.php" class="nav-link <?= $active === 'users' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            <span>Users & Customers</span>
        </a>

        <a href="analytics.php" class="nav-link <?= $active === 'analytics' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
            <span>Traffic & Analytics</span>
        </a>

        <a href="settings.php" class="nav-link <?= $active === 'settings' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            <span>Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-profile" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="admin-avatar"><?= htmlspecialchars($adminInitials) ?></div>
                <div class="admin-info">
                    <h4 style="margin: 0; font-size: 13px; font-weight: 700; color: #fff;"><?= htmlspecialchars($adminName) ?></h4>
                    <p style="margin: 0; font-size: 11px; color: #888;">Administrator</p>
                </div>
            </div>
            <a href="logout.php" title="Logout" style="color: #ff6666; display: flex; align-items: center; padding: 6px; border-radius: 6px; transition: background 0.2s;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>
    </div>
</aside>
