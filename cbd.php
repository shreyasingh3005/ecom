<?php
// cbd.php (Flagship Storefront Homepage)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/referral.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';

Analytics::trackPage('Home | KAMS HEMP');

// Load Global Settings
$storeName = Settings::get('store_name', 'KAMS HEMP');
$heroBadge = Settings::get('hero_badge', '🌿 100% Certified Organic Vijaya Extract');
$heroTitle = Settings::get('hero_title', 'Smart Botanical Remedies. Better Everyday Living.');
$heroSubtitle = Settings::get('hero_subtitle', "Premium Ayurvedic Vijaya & CBD formulations designed to ease daily stress, promote restorative sleep, and soothe body aches naturally.");
$heroCtaText = Settings::get('hero_cta_text', 'Shop Products');
$heroCtaLink = Settings::get('hero_cta_link', 'cbd-products.php');
$heroBannerImg = Settings::get('hero_banner_image', 'uploads/banners/hero_banner_main.jpg');

$referralDiscountPercent = (float)Settings::get('referral_discount_percent', 10);
$referrerRewardPercent = (float)Settings::get('referrer_reward_percent', 10);

// Current User & Referral
$isLoggedIn = Auth::isCustomerLoggedIn();
$currentUser = $isLoggedIn ? Auth::getUser(true) : null;
$userReferralCode = $currentUser['referral_code'] ?? '';

// Load Dynamic Products from MySQL Database
$products = [];
$db = Database::getInstance();
try {
    $prodStmt = $db->query("SELECT p.*, c.name as cat_name 
                            FROM `products` p 
                            LEFT JOIN `categories` c ON p.category_id = c.id 
                            WHERE p.status != 'Inactive' 
                            ORDER BY p.id ASC");
    $dbProducts = $prodStmt->fetchAll();

    foreach ($dbProducts as $item) {
        $images = Inventory::getProductImages($item['id']);
        $primaryImg = Inventory::getPrimaryImage($images);
        $secondaryImg = Inventory::getSecondaryImage($images);

        $rawPrice = (float)$item['price'];
        $mrpVal = !empty($item['mrp']) ? (float)$item['mrp'] : round($rawPrice * 1.25);
        $discountPercent = ($mrpVal > $rawPrice) ? round((($mrpVal - $rawPrice) / $mrpVal) * 100) : 0;
        $badge = !empty($item['sale_badge']) ? $item['sale_badge'] : ($discountPercent > 0 ? "{$discountPercent}% OFF" : '');
        $card = get_product_card_data($item);

        $products[] = array_merge($card, [
            "id" => (int)$item['id'],
            "title" => $item['name'],
            "name" => $item['name'],
            "price" => '₹' . number_format($rawPrice),
            "raw_price" => $rawPrice,
            "mrp" => '₹' . number_format($mrpVal),
            "discount_percent" => $discountPercent,
            "badge" => $badge,
            "image" => $primaryImg,
            "image2" => $secondaryImg,
            "stock" => (int)$item['stock'],
            "status" => $item['status'],
            "category" => $item['category_name'] ?: ($item['cat_name'] ?: 'Ayurvedic Formulations')
        ]);
    }
} catch (Exception $e) {
    error_log("Error loading products: " . $e->getMessage());
}

// Load Featured Reviews
$testimonials = [];
try {
    $tStmt = $db->query("SELECT * FROM `testimonials` WHERE `status` = 'Published' ORDER BY `id` DESC LIMIT 3");
    $testimonials = $tStmt->fetchAll();
} catch (Exception $e) {}

// Load Latest Blog Insights
$blogs = [];
try {
    $bStmt = $db->query("SELECT * FROM `blogs` WHERE `status` = 'Published' ORDER BY `id` DESC LIMIT 3");
    $blogs = $bStmt->fetchAll();
} catch (Exception $e) {}

// Brand Partner Logos
$brandLogos = [
    "uploads/Ananta-Hemp-Work-.png",
    "uploads/cannablithe.png",
    "uploads/Cannazo-India-150x150-1.webp",
    "uploads/Noigra-150x150-1.webp",
    "uploads/qurist-CBD-150x150-1.webp",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Home - Certified Ayurvedic Vijaya & CBD Extracts',
        'description' => "Explore India's leading AYUSH certified Full-Spectrum Vijaya formulations, doctor prescribed cannabis drops, and natural restorative sleep oils."
    ]);
    ?>
    <style>
        /* Hero Section */
        .home-hero {
            padding: 60px 0 54px 0;
            background: radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.08) 0%, transparent 70%);
            position: relative;
            overflow: hidden;
        }
        .home-hero-grid {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 48px;
            align-items: center;
        }
        .hero-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(229, 195, 120, 0.12);
            border: 1px solid var(--theme-gold);
            color: var(--theme-gold);
            font-size: 12.5px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: var(--radius-full);
            margin-bottom: 18px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .hero-title {
            font-family: var(--font-heading);
            font-size: 44px;
            font-weight: 900;
            color: #ffffff;
            line-height: 1.2;
            margin-bottom: 18px;
            letter-spacing: -0.5px;
        }
        .hero-desc {
            color: var(--theme-text-secondary);
            font-size: 16.5px;
            line-height: 1.7;
            margin-bottom: 30px;
            max-width: 540px;
        }
        .hero-actions-row {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .hero-img-box {
            position: relative;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: 0 25px 60px -15px rgba(0, 255, 204, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: #0f131d;
        }
        .hero-img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.5s ease;
        }
        .hero-img-box:hover .hero-img {
            transform: scale(1.03);
        }

        /* USPs Bar */
        .usps-strip {
            padding: 26px 0;
            border-top: 1px solid var(--theme-border);
            border-bottom: 1px solid var(--theme-border);
            background: rgba(255, 255, 255, 0.015);
        }
        .usps-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        .usp-item {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .usp-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: rgba(0, 255, 204, 0.08);
            border: 1px solid rgba(0, 255, 204, 0.25);
            color: var(--theme-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .usp-text h4 {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 2px;
        }
        .usp-text p {
            font-size: 12px;
            color: var(--theme-text-muted);
        }

        /* Section Headings */
        .section-header {
            text-align: center;
            margin-bottom: 44px;
        }
        .section-subtitle {
            font-size: 12px;
            font-weight: 800;
            color: var(--theme-primary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .section-title {
            font-family: var(--font-heading);
            font-size: 34px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
        }
        .section-desc {
            color: var(--theme-text-secondary);
            font-size: 15.5px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Benefits Grid (Simple English) */
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
            margin-bottom: 60px;
        }
        .benefit-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 26px 22px;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .benefit-card:hover {
            border-color: var(--theme-primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-card);
        }
        .benefit-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(0, 255, 204, 0.08);
            border: 1px solid rgba(0, 255, 204, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .benefit-title {
            font-family: var(--font-heading);
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
        }
        .benefit-desc {
            font-size: 13.5px;
            color: var(--theme-text-secondary);
            line-height: 1.6;
        }

        /* Category Grid */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 70px;
        }
        .category-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 28px 20px;
            text-align: center;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
        }
        .category-card:hover {
            transform: translateY(-6px);
            border-color: var(--theme-primary);
            box-shadow: var(--shadow-hover);
        }
        .cat-icon-circle {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: rgba(0, 255, 204, 0.1);
            border: 1px solid var(--theme-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }
        .category-card h3 {
            font-family: var(--font-heading);
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .category-card p {
            font-size: 12.5px;
            color: var(--theme-text-muted);
            margin-bottom: 14px;
        }
        .cat-link-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--theme-gold);
            margin-top: auto;
        }

        /* Trust Pillars Grid */
        .trust-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 36px;
        }
        .trust-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 24px 20px;
            text-align: center;
        }
        .trust-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }
        .trust-card h4 {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .trust-card p {
            font-size: 12.5px;
            color: var(--theme-text-secondary);
            line-height: 1.55;
        }

        /* B2B Bulk Order Banner (Secondary Prominence) */
        .b2b-bulk-box {
            background: linear-gradient(135deg, rgba(19, 26, 41, 0.95) 0%, rgba(15, 21, 34, 0.95) 100%);
            border: 1px solid rgba(0, 255, 204, 0.25);
            border-radius: var(--radius-xl);
            padding: 40px;
            display: grid;
            grid-template-columns: 1.25fr 1fr;
            gap: 36px;
            align-items: center;
            margin: 60px 0;
            box-shadow: var(--shadow-card);
        }
        .b2b-points-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 20px 0 26px 0;
        }
        .b2b-point {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 13px;
            color: #cbd5e1;
        }
        .b2b-point svg {
            color: var(--theme-primary);
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Promotional Referral Banner */
        .promo-banner {
            background: linear-gradient(135deg, #131a29 0%, #172439 50%, #0f1522 100%);
            border: 1px solid rgba(0, 255, 204, 0.25);
            border-radius: var(--radius-xl);
            padding: 44px;
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 36px;
            align-items: center;
            margin: 40px 0 60px 0;
            box-shadow: var(--shadow-card);
        }
        .promo-banner h2 {
            font-family: var(--font-heading);
            font-size: 30px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.3;
            margin-bottom: 12px;
        }
        .promo-banner p {
            color: #cbd5e1;
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 24px;
        }

        /* Brand Logos Strip */
        .brands-strip {
            padding: 40px 0 60px 0;
            text-align: center;
        }
        .brands-grid {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 36px;
            opacity: 0.8;
            filter: grayscale(0.5);
            transition: filter 0.3s ease;
        }
        .brands-grid:hover {
            filter: grayscale(0);
        }
        .brand-logo-img {
            max-height: 48px;
            object-fit: contain;
        }

        @media (max-width: 1024px) {
            .home-hero-grid {
                grid-template-columns: 1fr;
            }
            .benefits-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .categories-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .usps-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            .trust-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .b2b-bulk-box {
                grid-template-columns: 1fr;
                padding: 30px;
            }
            .promo-banner {
                grid-template-columns: 1fr;
                padding: 30px;
            }
        }

        @media (max-width: 640px) {
            .hero-title {
                font-size: 32px;
            }
            .benefits-grid {
                grid-template-columns: 1fr;
            }
            .categories-grid {
                grid-template-columns: 1fr;
            }
            .usps-grid {
                grid-template-columns: 1fr;
            }
            .trust-grid {
                grid-template-columns: 1fr;
            }
            .b2b-points-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <!-- Hero Section -->
        <section class="home-hero">
            <div class="theme-container">
                <div class="home-hero-grid">
                    <div>
                        <div class="hero-badge-pill">
                            <?= htmlspecialchars($heroBadge) ?>
                        </div>
                        <h1 class="hero-title"><?= htmlspecialchars($heroTitle) ?></h1>
                        <p class="hero-desc"><?= htmlspecialchars($heroSubtitle) ?></p>

                        <div class="hero-actions-row">
                            <a href="<?= htmlspecialchars($heroCtaLink) ?>" class="btn-primary" style="height:50px; font-size:15px; padding:0 30px;">
                                <?= htmlspecialchars($heroCtaText) ?> →
                            </a>
                            <a href="#featured-products" class="btn-outline" style="height:50px; font-size:14px; padding:0 24px;">
                                Explore Collection
                            </a>
                        </div>

                        <div style="display:flex; align-items:center; gap:16px; margin-top:32px; font-size:13px; color:var(--theme-text-muted); flex-wrap:wrap;">
                            <span>✓ NABL Lab Tested</span>
                            <span>•</span>
                            <span>✓ Doctor Guided</span>
                            <span>•</span>
                            <span>✓ Pan-India Delivery</span>
                        </div>
                    </div>

                    <div class="hero-img-box">
                        <img src="<?= htmlspecialchars($heroBannerImg) ?>" alt="<?= htmlspecialchars($heroTitle) ?>" class="hero-img">
                    </div>
                </div>
            </div>
        </section>

        <!-- USPs Bar -->
        <section class="usps-strip">
            <div class="theme-container">
                <div class="usps-grid">
                    <div class="usp-item">
                        <div class="usp-icon-wrap">
                            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        </div>
                        <div class="usp-text">
                            <h4>AYUSH Licensed</h4>
                            <p>Ministry authorized extracts</p>
                        </div>
                    </div>
                    <div class="usp-item">
                        <div class="usp-icon-wrap">
                            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        </div>
                        <div class="usp-text">
                            <h4>NABL Lab Verified</h4>
                            <p>Every batch purity COA</p>
                        </div>
                    </div>
                    <div class="usp-item">
                        <div class="usp-icon-wrap">
                            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        </div>
                        <div class="usp-text">
                            <h4>Free Express Shipping</h4>
                            <p>On orders above ₹3,999</p>
                        </div>
                    </div>
                    <div class="usp-item">
                        <div class="usp-icon-wrap">
                            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        </div>
                        <div class="usp-text">
                            <h4>Ayurvedic Doctor Desk</h4>
                            <p>Free dosage consultation</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Product Benefits Section (Simple English) -->
        <section style="padding: 70px 0 20px 0;">
            <div class="theme-container">
                <div class="section-header">
                    <div class="section-subtitle">Real Customer Benefits</div>
                    <h2 class="section-title">Designed for Everyday Healing & Calm</h2>
                    <p class="section-desc">Clear, clinically supported plant wellness made to restore your natural balance without complex routines or synthetic additives.</p>
                </div>

                <div class="benefits-grid">
                    <div class="benefit-card">
                        <div class="benefit-icon">🧘</div>
                        <h3 class="benefit-title">Stress Relief</h3>
                        <p class="benefit-desc">Designed to help you relax after a long and busy day, calming nervous chatter and helping your mind unwind naturally.</p>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon">🌙</div>
                        <h3 class="benefit-title">Restful Deep Sleep</h3>
                        <p class="benefit-desc">Calms your mind for uninterrupted, restorative night sleep so you wake up refreshed without morning grogginess.</p>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon">⚡</div>
                        <h3 class="benefit-title">Joint & Muscle Ease</h3>
                        <p class="benefit-desc">Targeted botanical soothing for morning stiffness, neck and back discomfort, and post-workout muscle soreness.</p>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon">💧</div>
                        <h3 class="benefit-title">Easy to Use</h3>
                        <p class="benefit-desc">Simple calibrated droppers and fast-absorbing balms make your daily dosage convenient and hassle-free.</p>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon">🌿</div>
                        <h3 class="benefit-title">100% Ayurvedic & Pure</h3>
                        <p class="benefit-desc">Wild-crafted Himalayan botanicals with sub-zero CO₂ extraction. Completely free from synthetic binders or harsh chemicals.</p>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon">✈️</div>
                        <h3 class="benefit-title">Travel Friendly</h3>
                        <p class="benefit-desc">Compact, leak-proof amber glass containers designed to slip easily into your travel kit or work bag wherever you go.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Product Categories Showcase -->
        <section style="padding: 20px 0 20px 0;">
            <div class="theme-container">
                <div class="section-header">
                    <div class="section-subtitle">Therapeutic Spectrum</div>
                    <h2 class="section-title">Explore by Targeted Wellness</h2>
                    <p class="section-desc">Ayurvedic formulations created to align your doshas, restore neural homeostasis, and encourage cellular healing.</p>
                </div>

                <div class="categories-grid">
                    <a href="category.php?cat=oils-tinctures" class="category-card">
                        <div class="cat-icon-circle">🌿</div>
                        <h3>Oils & Tinctures</h3>
                        <p>Full-spectrum sublingual drops for chronic inflammation and anxiety.</p>
                        <span class="cat-link-label">Shop Tinctures →</span>
                    </a>
                    <a href="category.php?cat=sleep-restorative" class="category-card">
                        <div class="cat-icon-circle">🌙</div>
                        <h3>Sleep & Restorative</h3>
                        <p>Herbal adaptogens & CBN drops for deep REM sleep recovery.</p>
                        <span class="cat-link-label">Shop Sleep Drops →</span>
                    </a>
                    <a href="category.php?cat=pain-relief-balms" class="category-card">
                        <div class="cat-icon-circle">⚡</div>
                        <h3>Pain Relief Balms</h3>
                        <p>Targeted transdermal roll-ons and balms for joint and muscle relief.</p>
                        <span class="cat-link-label">Shop Topicals →</span>
                    </a>
                    <a href="category.php?cat=pet-wellness" class="category-card">
                        <div class="cat-icon-circle">🐾</div>
                        <h3>Veterinary Care</h3>
                        <p>Gentle calming botanical drops formulated for dogs and cats.</p>
                        <span class="cat-link-label">Shop Pet Care →</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Featured Best Sellers Grid (All Active Formulations) -->
        <section id="featured-products" style="padding: 20px 0 70px 0;">
            <div class="theme-container">
                <div class="section-header">
                    <div class="section-subtitle">Top Formulations</div>
                    <h2 class="section-title">Practitioner-Recommended Extracts</h2>
                    <p class="section-desc">Our highest-rated, clinically validated Ayurvedic preparations for everyday wellness.</p>
                </div>

                <div class="theme-products-grid">
                    <?php foreach ($products as $p): ?>
                        <div class="theme-product-card">
                            <a href="product_details.php?id=<?= $p['id'] ?>" class="theme-product-img-box">
                                <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" class="theme-product-img" loading="lazy">
                                <?php if (!empty($p['badge'])): ?>
                                    <span class="theme-badge-sale"><?= htmlspecialchars($p['badge']) ?></span>
                                <?php endif; ?>
                                <span class="theme-badge-stock"><?= htmlspecialchars($p['status']) ?></span>
                            </a>

                            <div class="theme-product-body">
                                <div class="theme-product-cat"><?= htmlspecialchars($p['category']) ?></div>
                                <a href="product_details.php?id=<?= $p['id'] ?>" class="theme-product-title">
                                    <?= htmlspecialchars($p['title']) ?>
                                </a>

                                <div class="theme-product-benefit">
                                    <?= htmlspecialchars($p['short_benefit']) ?>
                                </div>

                                <div class="theme-product-rating">
                                    <span class="theme-stars">★★★★★</span>
                                    <span class="theme-rating-val"><?= $p['rating'] ?></span>
                                    <span class="theme-review-count">(<?= $p['reviews_count'] ?> reviews)</span>
                                </div>

                                <div class="theme-product-pricing">
                                    <span class="theme-price-current"><?= $p['price'] ?></span>
                                    <?php if ($p['discount_percent'] > 0): ?>
                                        <span class="theme-price-mrp"><?= $p['mrp'] ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="theme-product-actions">
                                    <?php if ($p['stock'] > 0): ?>
                                        <a href="cart.php?action=add&id=<?= $p['id'] ?>" class="theme-btn-add" title="Add to cart">
                                            <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" stroke-width="2" fill="none"><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                            Add to Cart
                                        </a>
                                        <a href="cart.php?action=add&id=<?= $p['id'] ?>&redirect=checkout" class="theme-btn-buy" title="Buy now with instant checkout">
                                            Buy Now
                                        </a>
                                    <?php else: ?>
                                        <button class="theme-btn-add" style="opacity:0.5; cursor:not-allowed;" disabled>Out of Stock</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="text-align:center; margin-top:40px;">
                    <a href="cbd-products.php" class="btn-primary" style="padding:14px 32px; font-size:15px;">
                        Explore Complete Catalog (<?= count($products) ?> Products) →
                    </a>
                </div>
            </div>
        </section>

        <!-- Trust & Quality Standards Section -->
        <section style="padding: 30px 0 70px 0; border-top: 1px solid var(--theme-border);">
            <div class="theme-container">
                <div class="section-header">
                    <div class="section-subtitle">Clinical Integrity</div>
                    <h2 class="section-title">Why 25,000+ Customers Trust Our Formulations</h2>
                    <p class="section-desc">Rooted in authentic Charaka Samhita wisdom and backed by accredited laboratory analytics.</p>
                </div>

                <div class="trust-grid">
                    <div class="trust-card">
                        <div class="trust-icon">🔬</div>
                        <h4>NABL Lab Certified</h4>
                        <p>Every single batch is tested for cannabinoid potency, pesticide residues, and heavy metals.</p>
                    </div>
                    <div class="trust-card">
                        <div class="trust-icon">⚕️</div>
                        <h4>Doctor Prescribed</h4>
                        <p>Access free dosage guidance from certified Ayurvedic Vaidyas to match your personal body type.</p>
                    </div>
                    <div class="trust-card">
                        <div class="trust-icon">📦</div>
                        <h4>Discreet Express Shipping</h4>
                        <p>Odorless, tamper-evident plain packaging delivered securely to your doorstep across India.</p>
                    </div>
                    <div class="trust-card">
                        <div class="trust-icon">🛡️</div>
                        <h4>100% Authentic Guarantee</h4>
                        <p>Licensed under Ministry of AYUSH with verifiable batch QR codes on every single carton.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Dedicated B2B / Bulk Orders Section (Priority 2, Clean Placement) -->
        <section style="padding: 10px 0;">
            <div class="theme-container">
                <div class="b2b-bulk-box">
                    <div>
                        <div style="font-size:11.5px; font-weight:800; color:var(--theme-primary); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">
                            B2B & Wholesale Orders
                        </div>
                        <h2 style="font-family:var(--font-heading); font-size:28px; font-weight:800; color:#fff; line-height:1.3; margin-bottom:12px;">
                            Looking for Products in Bulk?
                        </h2>
                        <p style="color:#cbd5e1; font-size:14.5px; line-height:1.7;">
                            We partner with licensed retailers, clinics, medical practitioners, and corporate buyers across India. Enjoy competitive wholesale margins, batch COAs, and reliable nationwide supply.
                        </p>

                        <div class="b2b-points-list">
                            <div class="b2b-point">
                                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>Tiered bulk pricing starting from 10 units</span>
                            </div>
                            <div class="b2b-point">
                                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>Full AYUSH compliance & NABL COAs</span>
                            </div>
                            <div class="b2b-point">
                                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>GST tax invoices & business accounts</span>
                            </div>
                            <div class="b2b-point">
                                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>Dedicated B2B account support</span>
                            </div>
                        </div>

                        <div style="display:flex; gap:14px; flex-wrap:wrap;">
                            <a href="b2b.php" class="btn-primary" style="padding:12px 24px; font-size:14px;">
                                Enquire for Bulk Order / Get B2B Price →
                            </a>
                            <a href="b2b.php#catalog" class="btn-outline" style="padding:12px 20px; font-size:13.5px;">
                                View Wholesale Requirements
                            </a>
                        </div>
                    </div>

                    <div style="background:rgba(255,255,255,0.03); border:1px solid var(--theme-border); border-radius:var(--radius-lg); padding:26px; text-align:center;">
                        <div style="font-size:38px; margin-bottom:10px;">📋</div>
                        <h4 style="color:#fff; font-size:17px; font-weight:700; margin-bottom:6px;">Wholesale Product Catalog</h4>
                        <p style="color:var(--theme-text-muted); font-size:13px; line-height:1.6; margin-bottom:18px;">
                            Download our complete wholesale specification sheet, bulk pricing tiers, and regulatory documentation.
                        </p>
                        <a href="b2b.php" class="btn-gold" style="width:100%; font-size:13px;">
                            Access Wholesale Portal
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Referral & Recurring Cashback Promo Banner -->
        <section>
            <div class="theme-container">
                <div class="promo-banner">
                    <div>
                        <div style="font-size:11.5px; font-weight:800; color:var(--theme-primary); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Referral Ecosystem</div>
                        <h2>Give 10% Discount, Earn 10% Lifetime Cashback</h2>
                        <p>Share the gift of plant-based healing with friends and family. When they use your referral link, they receive an immediate 10% discount, and you get 10% cash reward into your wallet on every purchase they ever make.</p>
                        <div style="display:flex; gap:14px; flex-wrap:wrap;">
                            <a href="profile.php?tab=wallet" class="btn-gold">Get Your Referral Link</a>
                            <a href="faq.php" class="btn-outline">How Cashback Works</a>
                        </div>
                    </div>
                    <div style="background:rgba(255,255,255,0.04); border:1px solid var(--theme-border); border-radius:var(--radius-lg); padding:28px; text-align:center;">
                        <div style="font-size:42px; margin-bottom:10px;">🎁</div>
                        <h4 style="color:#fff; font-size:18px; font-weight:700; margin-bottom:6px;">Instant ₹100 Welcome Credit</h4>
                        <p style="color:var(--theme-text-muted); font-size:13px; margin-bottom:18px;">Create a free customer account today to claim your launch reward.</p>
                        <a href="profile.php?tab=register" class="btn-primary" style="width:100%;">Create Account</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Verified Testimonials Grid -->
        <?php if (!empty($testimonials)): ?>
            <section style="padding: 20px 0 80px 0;">
                <div class="theme-container">
                    <div class="section-header">
                        <div class="section-subtitle">Real Patient Outcomes</div>
                        <h2 class="section-title">Tested by Patients, Backed by Doctors</h2>
                        <p class="section-desc">Authentic experiences from individuals seeking natural, plant-based remedies across India.</p>
                    </div>

                    <div class="theme-testimonials-grid">
                        <?php foreach ($testimonials as $t): ?>
                            <div style="background:var(--theme-surface-card); border:1px solid var(--theme-border); border-radius:var(--radius-lg); padding:26px; display:flex; flex-direction:column;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                                    <div>
                                        <h4 style="color:#fff; font-size:15px; font-weight:700; margin-bottom:2px;"><?= htmlspecialchars($t['customer_name']) ?></h4>
                                        <span style="color:var(--theme-text-muted); font-size:12px;"><?= htmlspecialchars($t['location'] ?? 'Verified Buyer') ?></span>
                                    </div>
                                    <span style="color:#ffaa00; font-size:15px;">★★★★★</span>
                                </div>
                                <p style="color:#cbd5e1; font-size:14px; line-height:1.7; margin-bottom:16px; flex-grow:1;">
                                    "<?= htmlspecialchars($t['review']) ?>"
                                </p>
                                <div style="font-size:12px; color:var(--theme-gold); font-weight:600;">
                                    🌿 <?= htmlspecialchars($t['product_name'] ?? 'Ayurvedic Vijaya Extract') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="text-align:center; margin-top:36px;">
                        <a href="testimonials.php" class="btn-outline">Read All Verified Reviews →</a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Latest Research Articles -->
        <?php if (!empty($blogs)): ?>
            <section style="padding: 20px 0 80px 0; border-top: 1px solid var(--theme-border);">
                <div class="theme-container">
                    <div class="section-header">
                        <div class="section-subtitle">Science & Pharmacology</div>
                        <h2 class="section-title">Clinical Insights & Vedic Research</h2>
                        <p class="section-desc">Learn about the endocannabinoid system, Ayurvedic pharmacopoeia, and sleep architecture.</p>
                    </div>

                    <div class="theme-blogs-grid">
                        <?php foreach ($blogs as $b): ?>
                            <article class="theme-product-card">
                                <a href="blog_post.php?slug=<?= urlencode($b['slug']) ?>" class="theme-product-img-box" style="padding-top:56.25%;">
                                    <img src="<?= htmlspecialchars($b['featured_image'] ?: 'uploads/banners/hero_banner_main.jpg') ?>" alt="<?= htmlspecialchars($b['title']) ?>" class="theme-product-img" loading="lazy">
                                </a>
                                <div class="theme-product-body">
                                    <div class="theme-product-cat"><?= htmlspecialchars($b['category']) ?></div>
                                    <a href="blog_post.php?slug=<?= urlencode($b['slug']) ?>" class="theme-product-title">
                                        <?= htmlspecialchars($b['title']) ?>
                                    </a>
                                    <p style="color:var(--theme-text-secondary); font-size:13px; line-height:1.6; margin-bottom:16px;">
                                        <?= htmlspecialchars($b['excerpt']) ?>
                                    </p>
                                    <a href="blog_post.php?slug=<?= urlencode($b['slug']) ?>" style="color:var(--theme-gold); font-size:13px; font-weight:700; margin-top:auto;">Read Article →</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Partner Brands Strip -->
        <section class="brands-strip">
            <div class="theme-container">
                <div style="font-size:12px; font-weight:700; color:var(--theme-text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:24px;">
                    Accredited Laboratory & Production Partners
                </div>
                <div class="brands-grid">
                    <?php foreach ($brandLogos as $bLogo): ?>
                        <img src="<?= htmlspecialchars($bLogo) ?>" alt="Partner Lab" class="brand-logo-img">
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>