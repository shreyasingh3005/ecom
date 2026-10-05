<?php
// includes/mobile_nav.php
// Redesigned, perfectly aligned mobile drawer component for KAMS HEMP

if (!isset($storeName)) {
    $storeName = Settings::get('store_name', 'KAMS HEMP');
}
$isLoggedIn = Auth::isCustomerLoggedIn();
$currentUser = $isLoggedIn ? Auth::getUser(true) : null;
$cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
$userWallet = (float)($currentUser['wallet_balance'] ?? 0);
?>
<!-- Mobile Drawer Backdrop -->
<div class="theme-mobile-backdrop" id="themeMobileBackdrop" aria-hidden="true"></div>

<!-- Mobile Drawer Container -->
<aside class="theme-mobile-drawer" id="themeMobileDrawer" aria-label="Mobile Navigation" role="dialog" aria-modal="true">
    <!-- Drawer Header -->
    <div class="theme-drawer-header">
        <div class="theme-drawer-logo-text">
            <span><?= htmlspecialchars($storeName) ?></span>
        </div>
        <button class="theme-drawer-close-btn" id="themeDrawerClose" type="button" aria-label="Close Mobile Navigation">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.2" fill="none">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <!-- Search in Drawer -->
    <div class="theme-drawer-search">
        <form action="cbd-products.php" method="GET" class="theme-drawer-search-form">
            <input type="text" name="search" placeholder="Search formulations, balms, oils..." class="theme-drawer-search-input">
            <button type="submit" class="theme-drawer-search-submit" aria-label="Search">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
        </form>
    </div>

    <!-- User Status Banner -->
    <?php if ($isLoggedIn): ?>
        <div class="theme-drawer-user-card">
            <div class="theme-drawer-user-info">
                <h5>Hello, <?= htmlspecialchars($currentUser['first_name'] ?? 'Friend') ?></h5>
                <p>Wallet Cashback: ₹<?= number_format($userWallet) ?></p>
            </div>
            <a href="profile.php" style="font-size:12px; font-weight:700; color:var(--theme-primary); text-decoration:underline;">View</a>
        </div>
    <?php endif; ?>

    <!-- Drawer Navigation Links (strictly left-aligned with equal spacing) -->
    <nav class="theme-drawer-nav">
        <a href="cbd.php" class="theme-drawer-link">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>Home</span>
        </a>

        <a href="cbd-products.php" class="theme-drawer-link">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
            <span>Shop All Products</span>
        </a>

        <!-- Category Accordion -->
        <div>
            <button type="button" class="theme-drawer-accordion-btn" aria-expanded="false">
                <div class="theme-drawer-accordion-btn-left">
                    <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Product Categories</span>
                </div>
                <svg class="theme-drawer-chevron" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>
            <div class="theme-drawer-accordion-content">
                <a href="category.php?cat=oils-tinctures" class="theme-drawer-sublink">Oils & Tinctures (Vijaya, CBD)</a>
                <a href="category.php?cat=sleep-restorative" class="theme-drawer-sublink">Sleep & Restorative Drops</a>
                <a href="category.php?cat=pain-relief-balms" class="theme-drawer-sublink">Pain Relief Balms & Topicals</a>
                <a href="category.php?cat=pet-wellness" class="theme-drawer-sublink">Calming Pet Extracts</a>
                <a href="category.php?cat=superfoods-nutrition" class="theme-drawer-sublink">Superfoods & Nutrition</a>
            </div>
        </div>

        <a href="cart.php" class="theme-drawer-link">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
            <span>My Cart</span>
            <?php if ($cartCount > 0): ?>
                <span style="margin-left:auto; background:var(--theme-primary); color:#000; font-size:11px; font-weight:800; padding:2px 8px; border-radius:999px;"><?= $cartCount ?></span>
            <?php endif; ?>
        </a>

        <a href="b2b.php" class="theme-drawer-link">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
            <span>B2B / Bulk Orders</span>
        </a>

        <a href="order-tracking.php" class="theme-drawer-link">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="10" r="3"></circle><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"></path></svg>
            <span>Track Order</span>
        </a>

        <a href="blog.php" class="theme-drawer-link">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            <span>Research & Articles</span>
        </a>

        <a href="faq.php" class="theme-drawer-link">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <span>FAQs</span>
        </a>

        <a href="testimonials.php" class="theme-drawer-link">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            <span>Customer Reviews</span>
        </a>

        <a href="cbd-about.php" class="theme-drawer-link">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"></line><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            <span>About Us</span>
        </a>

        <a href="cbd-contact.php" class="theme-drawer-link">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            <span>Contact Support</span>
        </a>

        <!-- Auth Section inside Drawer -->
        <?php if ($isLoggedIn): ?>
            <a href="profile.php" class="theme-drawer-link">
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>My Profile & Orders</span>
            </a>
            <a href="profile.php?logout=1" class="theme-drawer-link" style="color:var(--theme-danger);">
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <span>Sign Out</span>
            </a>
        <?php else: ?>
            <a href="profile.php?tab=login" class="theme-drawer-link">
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                <span>Sign In</span>
            </a>
            <a href="profile.php?tab=register" class="theme-drawer-link" style="color:var(--theme-primary);">
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                <span>Create Account (10% OFF)</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Drawer Quick Support Footer -->
    <div class="theme-drawer-footer">
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', Settings::get('whatsapp_number', '+919876543210')) ?>" target="_blank" class="theme-drawer-link" style="background:#25d366; color:#000; font-weight:700;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771z"></path></svg>
            <span>WhatsApp Direct Help</span>
        </a>
        <div class="theme-drawer-footer-row">
            <span>AYUSH Licensed</span>
            <span>100% NABL Tested</span>
        </div>
    </div>
</aside>
