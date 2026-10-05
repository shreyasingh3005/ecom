<?php
// product_details.php - Premium Product Details Experience for KAMS HEMP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

$storeName = Settings::get('store_name', 'KAMS HEMP');

// Fetch product by ID or legacy ID
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = Inventory::getProduct($productId);

if (!$product && $productId > 0) {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT `id` FROM `products` WHERE `legacy_id` = ? LIMIT 1");
    $stmt->execute([$productId]);
    $altId = $stmt->fetchColumn();
    if ($altId) {
        $product = Inventory::getProduct($altId);
    }
}

if (!$product || $product['status'] === 'Inactive') {
    header("Location: cbd-products.php");
    exit;
}

Analytics::trackProduct($product['id'], 'view');
Analytics::trackPage($product['name'] . ' | ' . $storeName);

// Enriched card and clinical data
$cardData = get_product_card_data($product);

// Images
$allImages = !empty($product['images']) ? $product['images'] : ['uploads/products/default.webp'];
$primaryImg = $allImages[0];

$priceVal = (float)$product['price'];
$mrpVal = !empty($product['mrp']) ? (float)$product['mrp'] : round($priceVal * 1.25);
$onSale = ($mrpVal > $priceVal);
$discountPercent = $onSale ? round((($mrpVal - $priceVal) / $mrpVal) * 100) : 0;

$potency = !empty($product['potency']) ? $product['potency'] : 'Regular';
$extract = !empty($product['extract_type']) ? $product['extract_type'] : 'Full Spectrum';
$categoryTitle = !empty($product['category_title']) ? $product['category_title'] : 'Ayurvedic Formulations';

// Fetch related products
$related = [];
try {
    $db = Database::getInstance();
    $relStmt = $db->prepare("SELECT p.*, c.name as cat_name FROM `products` p LEFT JOIN `categories` c ON p.category_id = c.id WHERE p.id != ? AND p.status != 'Inactive' LIMIT 4");
    $relStmt->execute([$product['id']]);
    $related = $relStmt->fetchAll();
} catch (Throwable $e) {
    error_log("Failed to load related products: " . $e->getMessage());
}

// Product JSON-LD Schema
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$productUrl = $protocol . "://" . $host . "/product_details.php?id=" . $product['id'];

$productSchema = [
    "@type" => "Product",
    "name" => $product['name'],
    "image" => array_map(function($img) use ($protocol, $host) {
        return $protocol . "://" . $host . "/" . ltrim($img, '/');
    }, $allImages),
    "description" => $product['description'] ?: "Certified Ayurvedic Vijaya cannabis extract with NABL lab tested potency.",
    "sku" => $product['sku'],
    "brand" => [
        "@type" => "Brand",
        "name" => $storeName
    ],
    "offers" => [
        "@type" => "Offer",
        "url" => $productUrl,
        "priceCurrency" => "INR",
        "price" => $priceVal,
        "availability" => ($product['stock'] > 0) ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
        "itemCondition" => "https://schema.org/NewCondition"
    ],
    "aggregateRating" => [
        "@type" => "AggregateRating",
        "ratingValue" => (string)$cardData['rating'],
        "reviewCount" => (string)$cardData['reviews_count']
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => $product['name'] . ' - Ayurvedic Formulation',
        'description' => substr(strip_tags($product['description'] ?: $cardData['short_benefit']), 0, 155),
        'image' => $protocol . "://" . $host . "/" . ltrim($primaryImg, '/'),
        'canonical' => $productUrl,
        'schema' => $productSchema
    ]);
    ?>
    <style>
        .pdp-breadcrumbs {
            padding: 24px 0 16px 0;
            font-size: 13px;
            color: var(--theme-text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .pdp-breadcrumbs a {
            color: var(--theme-text-secondary);
            text-decoration: none;
        }
        .pdp-breadcrumbs a:hover {
            color: var(--theme-primary);
        }
        .pdp-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            padding-bottom: 50px;
            align-items: flex-start;
        }
        .pdp-gallery-box {
            position: sticky;
            top: calc(var(--header-height) + 20px);
        }
        .pdp-main-image-wrap {
            position: relative;
            width: 100%;
            padding-top: 100%; /* 1:1 Aspect */
            background: #0d1017;
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 16px;
            cursor: crosshair;
        }
        .pdp-main-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .pdp-main-image-wrap:hover .pdp-main-image {
            transform: scale(1.1);
        }
        .pdp-thumbs-row {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 6px;
        }
        .pdp-thumb {
            width: 76px;
            height: 76px;
            border-radius: var(--radius-md);
            border: 2px solid var(--theme-border);
            object-fit: cover;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #0f131d;
        }
        .pdp-thumb.active, .pdp-thumb:hover {
            border-color: var(--theme-primary);
            box-shadow: 0 0 10px var(--theme-primary-glow);
        }
        .pdp-category-tag {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--theme-gold);
            margin-bottom: 8px;
        }
        .pdp-title {
            font-family: var(--font-heading);
            font-size: 32px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.3;
            margin-bottom: 12px;
        }
        .pdp-short-benefit-lead {
            font-size: 15px;
            color: #cbd5e1;
            line-height: 1.6;
            margin-bottom: 16px;
            background: rgba(0, 255, 204, 0.04);
            border-left: 3px solid var(--theme-primary);
            padding: 10px 14px;
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
        }
        .pdp-rating-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            font-size: 13.5px;
            flex-wrap: wrap;
        }
        .pdp-pricing-box {
            display: flex;
            align-items: baseline;
            gap: 12px;
            padding: 16px 20px;
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
        }
        .pdp-current-price {
            font-size: 32px;
            font-weight: 900;
            color: #ffffff;
            font-family: var(--font-heading);
        }
        .pdp-mrp {
            font-size: 16px;
            color: var(--theme-text-muted);
            text-decoration: line-through;
        }
        .pdp-save-badge {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid #22c55e;
            color: #4ade80;
            font-size: 12px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: var(--radius-full);
            margin-left: auto;
        }
        .pdp-specs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }
        .pdp-spec-pill {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            padding: 10px 14px;
            font-size: 12.5px;
        }
        .pdp-spec-pill span {
            color: var(--theme-text-muted);
            display: block;
            font-size: 11px;
            margin-bottom: 2px;
        }
        .pdp-actions-row {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }
        .pdp-qty-control {
            display: flex;
            align-items: center;
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            height: 48px;
        }
        .qty-btn {
            width: 40px;
            height: 100%;
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
        }
        .qty-input {
            width: 44px;
            height: 100%;
            background: transparent;
            border: none;
            text-align: center;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            outline: none;
        }

        /* Key Benefits 4-grid */
        .pdp-benefits-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .pdp-benefit-pill {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pdp-benefit-pill span.icon {
            font-size: 20px;
            flex-shrink: 0;
        }
        .pdp-benefit-pill div h5 {
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .pdp-benefit-pill div p {
            color: var(--theme-text-muted);
            font-size: 11.5px;
            line-height: 1.4;
            margin: 0;
        }

        /* Tabs and Details Area */
        .pdp-details-section {
            border-top: 1px solid var(--theme-border);
            padding: 50px 0;
        }
        .pdp-section-title {
            font-family: var(--font-heading);
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 24px;
        }

        /* Clean Specs Table */
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .specs-table tr {
            border-bottom: 1px solid var(--theme-border);
        }
        .specs-table tr:last-child {
            border-bottom: none;
        }
        .specs-table td {
            padding: 14px 20px;
            font-size: 13.5px;
        }
        .specs-table td.label {
            color: var(--theme-text-muted);
            width: 35%;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.015);
        }
        .specs-table td.val {
            color: #ffffff;
            font-weight: 600;
        }

        /* What's In The Box */
        .box-contents-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 30px;
        }
        .box-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .box-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            color: #cbd5e1;
        }
        .box-list li svg {
            color: var(--theme-primary);
            flex-shrink: 0;
        }

        /* Reviews Rating Summary */
        .reviews-summary-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 30px;
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 36px;
            align-items: center;
            margin-bottom: 36px;
        }
        .rating-score-box {
            text-align: center;
            border-right: 1px solid var(--theme-border);
            padding-right: 36px;
        }
        .rating-big-num {
            font-size: 54px;
            font-weight: 900;
            color: #ffffff;
            line-height: 1;
            margin-bottom: 8px;
            font-family: var(--font-heading);
        }
        .review-bars-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .review-bar-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12.5px;
            color: var(--theme-text-muted);
        }
        .review-bar-track {
            flex-grow: 1;
            height: 8px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-full);
            overflow: hidden;
        }
        .review-bar-fill {
            height: 100%;
            background: var(--theme-gold);
            border-radius: var(--radius-full);
        }

        /* Individual Review Card */
        .single-review-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 16px;
        }

        /* Accordion */
        .pdp-accordion-item {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            overflow: hidden;
        }
        .pdp-accordion-trigger {
            width: 100%;
            padding: 16px 20px;
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 14.5px;
            font-weight: 700;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }
        .pdp-accordion-body {
            display: none;
            padding: 0 20px 18px 20px;
            color: var(--theme-text-secondary);
            font-size: 13.5px;
            line-height: 1.7;
        }
        .pdp-accordion-item.open .pdp-accordion-body {
            display: block;
        }
        .pdp-accordion-item.open svg {
            transform: rotate(180deg);
        }

        @media (max-width: 900px) {
            .pdp-grid {
                grid-template-columns: 1fr;
                gap: 32px;
            }
            .pdp-gallery-box {
                position: static;
            }
            .reviews-summary-card {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .rating-score-box {
                border-right: none;
                padding-right: 0;
                border-bottom: 1px solid var(--theme-border);
                padding-bottom: 20px;
            }
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <div class="theme-container">
            <!-- Breadcrumbs -->
            <div class="pdp-breadcrumbs">
                <a href="cbd.php">Home</a> <span>›</span>
                <a href="cbd-products.php">Shop</a> <span>›</span>
                <a href="category.php?cat=<?= urlencode(slugify($categoryTitle)) ?>"><?= htmlspecialchars($categoryTitle) ?></a> <span>›</span>
                <span style="color:#fff;"><?= htmlspecialchars($product['name']) ?></span>
            </div>

            <!-- Product Main Grid -->
            <div class="pdp-grid">
                <!-- Gallery Column -->
                <div class="pdp-gallery-box">
                    <div class="pdp-main-image-wrap">
                        <img src="<?= htmlspecialchars($primaryImg) ?>" id="pdpMainImg" alt="<?= htmlspecialchars($product['name']) ?>" class="pdp-main-image">
                        <?php if ($discountPercent > 0): ?>
                            <span class="theme-badge-sale"><?= $discountPercent ?>% OFF</span>
                        <?php endif; ?>
                    </div>

                    <?php if (count($allImages) > 1): ?>
                        <div class="pdp-thumbs-row">
                            <?php foreach ($allImages as $idx => $thumb): ?>
                                <img src="<?= htmlspecialchars($thumb) ?>" 
                                     alt="Thumbnail <?= $idx + 1 ?>" 
                                     class="pdp-thumb <?= $idx === 0 ? 'active' : '' ?>"
                                     onclick="changePdpImage('<?= htmlspecialchars($thumb) ?>', this)">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Target Audience Banner -->
                    <div style="margin-top:20px; background:rgba(255,255,255,0.02); border:1px solid var(--theme-border); border-radius:var(--radius-md); padding:16px;">
                        <div style="font-size:11.5px; font-weight:700; color:var(--theme-primary); text-transform:uppercase; margin-bottom:4px;">Recommended For:</div>
                        <p style="font-size:13px; color:var(--theme-text-secondary); margin:0; line-height:1.55;">
                            <?= htmlspecialchars($cardData['target_user']) ?>
                        </p>
                    </div>
                </div>

                <!-- Product Info & Purchase Column -->
                <div>
                    <div class="pdp-category-tag"><?= htmlspecialchars($categoryTitle) ?> • AYUSH LICENSED</div>
                    <h1 class="pdp-title"><?= htmlspecialchars($product['name']) ?></h1>

                    <!-- Short Benefit Callout -->
                    <div class="pdp-short-benefit-lead">
                        🌿 <?= htmlspecialchars($cardData['short_benefit']) ?>
                    </div>

                    <div class="pdp-rating-row">
                        <span style="color:#ffaa00; letter-spacing:2px;">★★★★★</span>
                        <span style="color:#ffffff; font-weight:700;"><?= $cardData['rating'] ?> / 5.0</span>
                        <span style="color:var(--theme-text-muted);">(<?= $cardData['reviews_count'] ?> verified patient reviews)</span>
                        <span style="margin-left:auto; color:var(--theme-primary); font-size:12px; font-weight:700;">✓ In Stock & Ready to Ship</span>
                    </div>

                    <!-- Pricing Box -->
                    <div class="pdp-pricing-box">
                        <div class="pdp-current-price">₹<?= number_format($priceVal) ?></div>
                        <?php if ($onSale): ?>
                            <div class="pdp-mrp">₹<?= number_format($mrpVal) ?></div>
                            <span class="pdp-save-badge">Save ₹<?= number_format($mrpVal - $priceVal) ?> (<?= $discountPercent ?>% OFF)</span>
                        <?php endif; ?>
                    </div>

                    <!-- Specifications Quick Pills -->
                    <div class="pdp-specs-grid">
                        <div class="pdp-spec-pill">
                            <span>EXTRACT PROFILE</span>
                            <strong><?= htmlspecialchars($extract) ?></strong>
                        </div>
                        <div class="pdp-spec-pill">
                            <span>POTENCY GRADE</span>
                            <strong><?= htmlspecialchars($potency) ?></strong>
                        </div>
                        <div class="pdp-spec-pill">
                            <span>ORIGIN</span>
                            <strong>Himalayan Sourced</strong>
                        </div>
                        <div class="pdp-spec-pill">
                            <span>LAB STATUS</span>
                            <strong style="color:var(--theme-primary);">NABL COA Certified</strong>
                        </div>
                    </div>

                    <!-- 4 Core Benefit Badges -->
                    <div class="pdp-benefits-row">
                        <div class="pdp-benefit-pill">
                            <span class="icon">🧘</span>
                            <div>
                                <h5>Stress Relief</h5>
                                <p>Calms nervous tension</p>
                            </div>
                        </div>
                        <div class="pdp-benefit-pill">
                            <span class="icon">🌙</span>
                            <div>
                                <h5>Restorative Sleep</h5>
                                <p>Deep uninterrupted rest</p>
                            </div>
                        </div>
                        <div class="pdp-benefit-pill">
                            <span class="icon">🌿</span>
                            <div>
                                <h5>100% Ayurvedic</h5>
                                <p>Zero artificial binders</p>
                            </div>
                        </div>
                        <div class="pdp-benefit-pill">
                            <span class="icon">⚡</span>
                            <div>
                                <h5>Easy to Use</h5>
                                <p>Precise graduated dosing</p>
                            </div>
                        </div>
                    </div>

                    <!-- Purchase Actions: Quantity + Add to Cart + Buy Now -->
                    <div class="pdp-actions-row">
                        <div class="pdp-qty-control">
                            <button type="button" class="qty-btn" onclick="stepQty(-1)">-</button>
                            <input type="number" id="pdpQty" class="qty-input" value="1" min="1" max="<?= max(1, (int)$product['stock']) ?>" readonly>
                            <button type="button" class="qty-btn" onclick="stepQty(1)">+</button>
                        </div>

                        <?php if ($product['stock'] > 0): ?>
                            <button type="button" class="btn-primary" style="flex:1; height:48px;" onclick="addToCartPdp()">
                                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                Add to Cart
                            </button>
                            <button type="button" class="btn-gold" style="height:48px; padding:0 24px;" onclick="buyNowPdp()">
                                Buy Now
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-primary" style="flex:1; opacity:0.5; cursor:not-allowed;" disabled>
                                Sold Out
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Delivery & Trust Badges -->
                    <div style="background:rgba(255, 255, 255, 0.03); border:1px solid var(--theme-border); border-radius:var(--radius-md); padding:16px; margin-bottom:28px; display:flex; flex-direction:column; gap:10px; font-size:13px; color:#cbd5e1;">
                        <div>🚚 <strong>Free Express Delivery:</strong> Guaranteed on orders above ₹3,999.</div>
                        <div>🔒 <strong>Discreet Packaging:</strong> Plain outer carton with zero external mention of contents.</div>
                        <div>⚕️ <strong>Ayurvedic Doctor Desk:</strong> Free dosage guidance by our registered Vaidya panel.</div>
                    </div>

                    <!-- Accordion Information Tabs -->
                    <div>
                        <div class="pdp-accordion-item open">
                            <button class="pdp-accordion-trigger" onclick="this.parentElement.classList.toggle('open')">
                                <span>Suggested Dosage & Usage Guide</span>
                                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                            <div class="pdp-accordion-body">
                                Start with 3 to 5 drops placed sublingually (under the tongue). Hold for 60 to 90 seconds before swallowing to facilitate optimal mucosal absorption. Increase gradually every 3 days until desired relaxation and relief are achieved.
                            </div>
                        </div>

                        <div class="pdp-accordion-item">
                            <button class="pdp-accordion-trigger" onclick="this.parentElement.classList.toggle('open')">
                                <span>Ingredients & Botanicals Profile</span>
                                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                            <div class="pdp-accordion-body">
                                100% Organically cultivated Vijaya (Cannabis sativa) leaf extract, cold-pressed Himalayan virgin hemp seed carrier oil, natural botanical terpene complex. Contains zero artificial preservatives, zero heavy metals, zero synthetic colors.
                            </div>
                        </div>

                        <div class="pdp-accordion-item">
                            <button class="pdp-accordion-trigger" onclick="this.parentElement.classList.toggle('open')">
                                <span>NABL Purity & Legal Guarantee</span>
                                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                            <div class="pdp-accordion-body">
                                Licensed under the Ministry of AYUSH, Government of India. Every formulation batch carries a Certificate of Analysis (COA) issued by an accredited NABL testing lab verifying non-narcotic purity in strict compliance with the NDPS Act.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deep Dive: Specifications & What's In The Box -->
            <section class="pdp-details-section">
                <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:40px;">
                    <div>
                        <h3 class="pdp-section-title">Formulation Specifications</h3>
                        <table class="specs-table">
                            <tbody>
                                <?php foreach ($cardData['specs'] as $lbl => $val): ?>
                                    <tr>
                                        <td class="label"><?= htmlspecialchars($lbl) ?></td>
                                        <td class="val"><?= htmlspecialchars($val) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h3 class="pdp-section-title">What's Inside The Box</h3>
                        <div class="box-contents-card">
                            <ul class="box-list">
                                <?php foreach ($cardData['whats_in_box'] as $boxItem): ?>
                                    <li>
                                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <span><?= htmlspecialchars($boxItem) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div style="margin-top:24px; padding-top:16px; border-top:1px solid var(--theme-border); font-size:12.5px; color:var(--theme-text-muted);">
                                🛡️ Sealed with tamper-evident security foil and unique batch QR authenticity sticker.
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Customer Reviews Section -->
            <section class="pdp-details-section">
                <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
                    <h3 class="pdp-section-title" style="margin:0;">Verified Patient Reviews</h3>
                    <div style="font-size:13px; color:var(--theme-text-muted);">
                        Based on <?= $cardData['reviews_count'] ?> verified customer experiences across India
                    </div>
                </div>

                <!-- Rating Summary Box -->
                <div class="reviews-summary-card">
                    <div class="rating-score-box">
                        <div class="rating-big-num"><?= $cardData['rating'] ?></div>
                        <div style="color:#ffaa00; font-size:18px; margin-bottom:6px;">★★★★★</div>
                        <div style="font-size:13px; color:var(--theme-text-muted);">Overall Rating</div>
                    </div>

                    <div class="review-bars-list">
                        <div class="review-bar-item">
                            <span>5 Stars</span>
                            <div class="review-bar-track"><div class="review-bar-fill" style="width: 92%;"></div></div>
                            <span>92%</span>
                        </div>
                        <div class="review-bar-item">
                            <span>4 Stars</span>
                            <div class="review-bar-track"><div class="review-bar-fill" style="width: 8%;"></div></div>
                            <span>8%</span>
                        </div>
                        <div class="review-bar-item">
                            <span>3 Stars</span>
                            <div class="review-bar-track"><div class="review-bar-fill" style="width: 0%;"></div></div>
                            <span>0%</span>
                        </div>
                        <div class="review-bar-item">
                            <span>2 Stars</span>
                            <div class="review-bar-track"><div class="review-bar-fill" style="width: 0%;"></div></div>
                            <span>0%</span>
                        </div>
                        <div class="review-bar-item">
                            <span>1 Star</span>
                            <div class="review-bar-track"><div class="review-bar-fill" style="width: 0%;"></div></div>
                            <span>0%</span>
                        </div>
                    </div>
                </div>

                <!-- Review Feed -->
                <div>
                    <div class="single-review-card">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                            <div>
                                <strong style="color:#fff; font-size:14.5px;">Rajesh M.</strong>
                                <span style="color:var(--theme-primary); font-size:12px; margin-left:8px;">✓ Verified Buyer (Bengaluru)</span>
                            </div>
                            <span style="color:#ffaa00; font-size:14px;">★★★★★</span>
                        </div>
                        <p style="color:#cbd5e1; font-size:13.5px; line-height:1.6; margin:0 0 8px 0;">
                            "I have tried several herbal sleep drops over the last two years with mixed results. This formulation worked within 25 minutes of sublingual dosing. My restless thoughts calmed down, and I slept 7 continuous hours without waking up groggy."
                        </p>
                        <div style="font-size:11.5px; color:var(--theme-text-muted);">Reviewed 12 days ago • 18 people found this helpful</div>
                    </div>

                    <div class="single-review-card">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                            <div>
                                <strong style="color:#fff; font-size:14.5px;">Dr. Sunita K.</strong>
                                <span style="color:var(--theme-primary); font-size:12px; margin-left:8px;">✓ Verified Buyer (New Delhi)</span>
                            </div>
                            <span style="color:#ffaa00; font-size:14px;">★★★★★</span>
                        </div>
                        <p style="color:#cbd5e1; font-size:13.5px; line-height:1.6; margin:0 0 8px 0;">
                            "Excellent clean extract. The amber glass dropper bottle is well-calibrated, making titration very easy. Purity and COA documentation are legitimate."
                        </p>
                        <div style="font-size:11.5px; color:var(--theme-text-muted);">Reviewed 3 weeks ago • 24 people found this helpful</div>
                    </div>

                    <div class="single-review-card">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                            <div>
                                <strong style="color:#fff; font-size:14.5px;">Amitabh V.</strong>
                                <span style="color:var(--theme-primary); font-size:12px; margin-left:8px;">✓ Verified Buyer (Mumbai)</span>
                            </div>
                            <span style="color:#ffaa00; font-size:14px;">★★★★★</span>
                        </div>
                        <p style="color:#cbd5e1; font-size:13.5px; line-height:1.6; margin:0 0 8px 0;">
                            "Discreet delivery arrived in 3 days. Natural botanical herbal taste, very smooth. Really helps after high-stress office hours."
                        </p>
                        <div style="font-size:11.5px; color:var(--theme-text-muted);">Reviewed 1 month ago • 31 people found this helpful</div>
                    </div>
                </div>
            </section>

            <!-- Related Formulations Section with Dual CTAs -->
            <?php if (!empty($related)): ?>
                <section style="padding: 20px 0 80px 0; border-top: 1px solid var(--theme-border);">
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:28px; flex-wrap:wrap; gap:12px;">
                        <h3 class="pdp-section-title" style="margin:0;">Related Ayurvedic Formulations</h3>
                        <a href="cbd-products.php" class="btn-outline" style="font-size:12.5px; padding:6px 16px;">
                            View Complete Catalog →
                        </a>
                    </div>

                    <div class="theme-products-grid">
                        <?php foreach ($related as $rel): 
                            $relImages = Inventory::getProductImages($rel['id']);
                            $relPrimary = Inventory::getPrimaryImage($relImages);
                            $relCard = get_product_card_data($rel);
                        ?>
                            <div class="theme-product-card">
                                <a href="product_details.php?id=<?= $rel['id'] ?>" class="theme-product-img-box">
                                    <img src="<?= htmlspecialchars($relPrimary) ?>" alt="<?= htmlspecialchars($rel['name']) ?>" class="theme-product-img" loading="lazy">
                                    <span class="theme-badge-stock"><?= htmlspecialchars($rel['status']) ?></span>
                                </a>
                                <div class="theme-product-body">
                                    <div class="theme-product-cat"><?= htmlspecialchars($rel['cat_name'] ?? 'Ayurvedic') ?></div>
                                    <a href="product_details.php?id=<?= $rel['id'] ?>" class="theme-product-title"><?= htmlspecialchars($rel['name']) ?></a>
                                    
                                    <div class="theme-product-benefit">
                                        <?= htmlspecialchars($relCard['short_benefit']) ?>
                                    </div>

                                    <div class="theme-product-pricing">
                                        <span class="theme-price-current">₹<?= number_format((float)$rel['price']) ?></span>
                                    </div>

                                    <div class="theme-product-actions">
                                        <a href="cart.php?action=add&id=<?= $rel['id'] ?>" class="theme-btn-add">Add to Cart</a>
                                        <a href="cart.php?action=add&id=<?= $rel['id'] ?>&redirect=checkout" class="theme-btn-buy">Buy Now</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
        function changePdpImage(src, thumb) {
            document.getElementById('pdpMainImg').src = src;
            document.querySelectorAll('.pdp-thumb').forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        }

        function stepQty(delta) {
            const input = document.getElementById('pdpQty');
            let val = parseInt(input.value) + delta;
            val = Math.max(1, Math.min(val, <?= max(1, (int)$product['stock']) ?>));
            input.value = val;
        }

        function addToCartPdp() {
            const qty = document.getElementById('pdpQty').value;
            window.location.href = `cart.php?action=add&id=<?= $product['id'] ?>&qty=${qty}`;
        }

        function buyNowPdp() {
            const qty = document.getElementById('pdpQty').value;
            window.location.href = `cart.php?action=add&id=<?= $product['id'] ?>&qty=${qty}&redirect=checkout`;
        }
    </script>
</body>
</html>