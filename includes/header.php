<?php
// includes/header.php
// Single Reusable Global Header Component for KAMS HEMP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/seo.php';

$storeName = Settings::get('store_name', 'KAMS HEMP');
$announcementText = Settings::get('announcement_bar_text', '🌿 100% Certified Ayurvedic Vijaya Extracts • Free Express Shipping on orders above ₹3,999 • AYUSH Licensed');
$isLoggedIn = Auth::isCustomerLoggedIn();
$headerUser = $isLoggedIn ? Auth::getUser(true) : null;
$cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!-- Preconnect & Modern Web Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">

<!-- Universal Theme Styles -->
<link rel="stylesheet" href="assets/css/responsive.css">
<link rel="stylesheet" href="assets/css/theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/theme.css') ?>">
<?php if (in_array($currentPage, ['cbd-about.php', 'about.php', 'cbd-contact.php', 'contact.php', 'faq.php', 'privacy-policy.php', 'terms.php', 'shipping-policy.php', 'refund-policy.php'], true)): ?>
<link rel="stylesheet" href="assets/css/information.css?v=<?= filemtime(__DIR__ . '/../assets/css/information.css') ?>">
<?php endif; ?>

<!-- Announcement Bar -->
<div class="theme-announcement" role="region" aria-label="Store Announcement">
    <div class="theme-announcement-inner">
        <span class="badge-pill">AYUSH VERIFIED</span>
        <span class="theme-announcement-text"><?= htmlspecialchars($announcementText) ?></span>
    </div>
</div>

<!-- Global Main Header -->
<header class="theme-header" role="banner">
    <div class="theme-container theme-header-inner">
        <!-- Mobile Hamburger Button -->
        <button class="theme-icon-btn theme-hamburger-btn" type="button" aria-label="Open Navigation Menu" aria-controls="themeMobileDrawer" aria-expanded="false">
            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <!-- Brand Logo -->
        <a href="cbd.php" class="theme-logo-wrapper" aria-label="<?= htmlspecialchars($storeName) ?> Home">
            <img src="logo.png" alt="<?= htmlspecialchars($storeName) ?> Logo" class="theme-logo-img" onerror="this.style.display='none';">
            <div class="theme-logo-text">
                <?= htmlspecialchars($storeName) ?>
                <span>Ayurvedic Healing</span>
            </div>
        </a>

        <!-- Desktop Navigation Menu -->
        <nav class="theme-nav" role="navigation" aria-label="Main Navigation">
            <a href="cbd.php" class="theme-nav-link <?= in_array($currentPage, ['cbd.php', 'index.php']) ? 'active' : '' ?>">Home</a>
            
            <div class="theme-nav-item-dropdown">
                <a href="cbd-products.php" class="theme-nav-link <?= $currentPage === 'cbd-products.php' ? 'active' : '' ?>">
                    Shop ▾
                </a>
                <div class="theme-dropdown-menu">
                    <a href="cbd-products.php" class="theme-dropdown-link">All Products</a>
                    <a href="category.php?cat=oils-tinctures" class="theme-dropdown-link">Oils & Tinctures</a>
                    <a href="category.php?cat=sleep-restorative" class="theme-dropdown-link">Sleep & Restorative Drops</a>
                    <a href="category.php?cat=pain-relief-balms" class="theme-dropdown-link">Pain Relief Balms & Care</a>
                    <a href="category.php?cat=pet-wellness" class="theme-dropdown-link">Pet Wellness Extracts</a>
                </div>
            </div>

            <a href="cbd-products.php" class="theme-nav-link">Categories</a>
            <a href="b2b.php" class="theme-nav-link <?= $currentPage === 'b2b.php' ? 'active' : '' ?>">B2B / Bulk</a>
            <a href="blog.php" class="theme-nav-link <?= in_array($currentPage, ['blog.php', 'blog_post.php']) ? 'active' : '' ?>">Blog</a>
            <a href="testimonials.php" class="theme-nav-link <?= $currentPage === 'testimonials.php' ? 'active' : '' ?>">Reviews</a>
            <a href="faq.php" class="theme-nav-link <?= $currentPage === 'faq.php' ? 'active' : '' ?>">FAQs</a>
            <a href="cbd-about.php" class="theme-nav-link <?= $currentPage === 'cbd-about.php' ? 'active' : '' ?>">About Us</a>
            <a href="cbd-contact.php" class="theme-nav-link <?= $currentPage === 'cbd-contact.php' ? 'active' : '' ?>">Contact</a>
        </nav>

        <!-- Right Quick Actions -->
        <div class="theme-header-actions">
            <!-- Search Box -->
            <div class="theme-header-search">
                <form action="cbd-products.php" method="GET" style="margin:0;">
                    <input type="text" name="search" class="theme-search-input" aria-label="Search products" placeholder="Search Vijaya, drops, balms..." autocomplete="off">
                    <button type="submit" class="theme-search-btn" aria-label="Submit Search">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                </form>
            </div>

            <!-- Account / User Menu -->
            <div class="theme-nav-item-dropdown">
                <a href="profile.php" class="theme-icon-btn" aria-label="Account & Profile">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </a>
                <div class="theme-dropdown-menu" style="right:0; left:auto; transform:none; min-width:200px;">
                    <?php if ($isLoggedIn): ?>
                        <div style="padding:8px 14px; font-size:12px; color:var(--theme-text-muted); border-bottom:1px solid var(--theme-border);">
                            Signed in as<br><strong style="color:#fff;"><?= htmlspecialchars($headerUser['first_name'] ?? 'User') ?></strong>
                        </div>
                        <a href="profile.php" class="theme-dropdown-link">Dashboard / Orders</a>
                        <a href="addresses.php" class="theme-dropdown-link">Saved Addresses</a>
                        <a href="profile.php?tab=wallet" class="theme-dropdown-link">Referrals & Wallet</a>
                        <a href="profile.php?logout=1" class="theme-dropdown-link" style="color:var(--theme-danger);">Sign Out</a>
                    <?php else: ?>
                        <a href="profile.php?tab=login" class="theme-dropdown-link">Sign In</a>
                        <a href="profile.php?tab=register" class="theme-dropdown-link" style="color:var(--theme-primary); font-weight:700;">Create Account (10% OFF)</a>
                        <a href="order-tracking.php" class="theme-dropdown-link">Track an Order</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cart Trigger Button -->
            <a href="cart.php" class="theme-icon-btn" aria-label="View Shopping Cart">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <?php if ($cartCount > 0): ?>
                    <span class="theme-cart-badge" id="themeHeaderCartBadge"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</header>

<!-- Mobile Navigation Drawer Component -->
<?php require_once __DIR__ . '/mobile_nav.php'; ?>
