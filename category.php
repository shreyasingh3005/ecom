<?php
// category.php - Dedicated Category Landing Experience for KAMS HEMP
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

// Category metadata definitions
$categoriesConfig = [
    'oils-tinctures' => [
        'slug' => 'oils-tinctures',
        'title' => 'Full Spectrum Oils & Tinctures',
        'badge' => 'AYUSH Licensed Sublingual Extracts',
        'icon' => '🌿',
        'description' => 'Potent sublingual Vijaya & CBD drops designed for rapid mucosal absorption, calming mental chatter, relieving chronic stress, and restoring natural nervous system balance.',
        'seo_title' => 'Vijaya & CBD Oils & Tinctures | Sublingual Extracts - KAMS HEMP',
        'seo_desc' => 'Shop certified Full Spectrum Ayurvedic Vijaya and CBD oil drops. NABL lab tested for purity, doctor prescribed, with fast discreet delivery across India.',
        'keywords' => ['oil', 'tincture', 'extract', 'drops'],
        'benefits' => [
            ['icon' => '⚡', 'title' => 'Rapid Sublingual Uptake', 'desc' => 'Direct absorption through sublingual capillaries bypasses liver breakdown for onset within 15–30 minutes.'],
            ['icon' => '🔬', 'title' => 'Full-Spectrum Entourage', 'desc' => 'Preserves minor phytocannabinoids, beta-caryophyllene, and native terpenes working in natural synergy.'],
            ['icon' => '💧', 'title' => 'Calibrated Precision Dropper', 'desc' => 'Graduated glass pipette allows you to adjust drops accurately from 3 to 10 drops per serving.'],
            ['icon' => '🛡️', 'title' => '100% Himalayan Organic', 'desc' => 'Wild-crafted organic botanical harvest with cold-pressed virgin seed carrier and zero synthetic fillers.']
        ],
        'faqs' => [
            ['q' => 'How do I take sublingual Vijaya oil drops?', 'a' => 'Place 3 to 5 drops directly under your tongue using the calibrated glass pipette. Hold the oil for 60 to 90 seconds before swallowing to facilitate optimal absorption through oral mucosal capillaries.'],
            ['q' => 'How soon will I notice the calming effects?', 'a' => 'Sublingual drops typically begin working within 15 to 30 minutes, with therapeutic benefits lasting 4 to 6 hours.'],
            ['q' => 'Will these oil drops make me feel intoxicated or sleepy?', 'a' => 'No. Our formulations are standardized according to Ministry of AYUSH guidelines to deliver clear-headed relaxation, stress relief, and systemic homeostasis without intoxication.'],
            ['q' => 'How should I store my tincture bottle?', 'a' => 'Store in a cool, dry place away from direct sunlight. There is no need to refrigerate, but ensure the cap is tightly closed after each use.']
        ]
    ],
    'sleep-restorative' => [
        'slug' => 'sleep-restorative',
        'title' => 'Sleep & Restorative Formulations',
        'badge' => 'Herbal REM Sleep & Circadian Support',
        'icon' => '🌙',
        'description' => 'Gentle botanical drops formulated to calm hyperactive evening thoughts, soothe night restlessness, and promote deep, uninterrupted sleep cycles naturally.',
        'seo_title' => 'Ayurvedic Sleep Drops & Restorative Oils - KAMS HEMP',
        'seo_desc' => 'Discover natural restorative sleep drops formulated with Vijaya extract and calming botanical terpenes. 100% non-habit forming with zero morning grogginess.',
        'keywords' => ['sleep', 'restorative', 'night', 'cbn'],
        'benefits' => [
            ['icon' => '💤', 'title' => 'Natural REM Restoration', 'desc' => 'Helps shorten the time it takes to drift off and promotes deep slow-wave restorative sleep cycles.'],
            ['icon' => '☀️', 'title' => 'Zero Morning Grogginess', 'desc' => 'Wake up feeling naturally refreshed and alert, with zero heavy sedative hangover or daytime fog.'],
            ['icon' => '🌿', 'title' => '100% Non-Habit Forming', 'desc' => 'Free from synthetic chemicals, antihistamines, or melatonin. Works with your natural circadian rhythm.'],
            ['icon' => '🧘', 'title' => 'Evening Cortisol Soothing', 'desc' => 'Eases muscular tightness and nervous tension accumulated from stressful workdays.']
        ],
        'faqs' => [
            ['q' => 'When is the best time to take Sleep Drops?', 'a' => 'Administer 4 to 6 drops sublingually 30 to 45 minutes before going to bed, ideally away from bright phone or laptop screens.'],
            ['q' => 'Are these sleep drops habit-forming or addictive?', 'a' => 'No. They contain pure Ayurvedic plant botanicals and terpenes that harmonize the endocannabinoid system without creating pharmacological dependency.'],
            ['q' => 'Can I use this if I wake up in the middle of the night?', 'a' => 'Yes. If you experience middle-of-the-night restlessness, 2 to 3 drops can be taken under the tongue to gently settle your mind back to sleep.'],
            ['q' => 'Does this formulation contain melatonin?', 'a' => 'No, our sleep formulations are 100% melatonin-free, relying entirely on full-spectrum phytocannabinoids, myrcene, and lavender botanicals.']
        ]
    ],
    'pain-relief-balms' => [
        'slug' => 'pain-relief-balms',
        'title' => 'Pain Relief Balms & Topicals',
        'badge' => 'Fast Transdermal Relief for Joints & Muscles',
        'icon' => '⚡',
        'description' => 'Targeted warming and soothing herbal balms engineered for fast relief from joint stiffness, backaches, neck strain, and post-workout muscular soreness.',
        'seo_title' => 'Ayurvedic Pain Relief Balms & Joint Topicals - KAMS HEMP',
        'seo_desc' => 'Fast-acting transdermal Vijaya balms for sore muscles, joint stiffness, and backache. Enriched with eucalyptus, wintergreen, and organic botanicals.',
        'keywords' => ['balm', 'pain', 'relief', 'topical', 'joint'],
        'benefits' => [
            ['icon' => '🎯', 'title' => 'Targeted Dermal Action', 'desc' => 'Engages dense cutaneous CB2 receptors directly at the source of muscular discomfort and joint stiffness.'],
            ['icon' => '🔥', 'title' => 'Warming Ayurvedic Herbs', 'desc' => 'Infused with Gandhapura (Wintergreen) and Nilgiri (Eucalyptus) to stimulate local tissue circulation.'],
            ['icon' => '🧴', 'title' => 'Non-Greasy & Quick Absorbing', 'desc' => 'Organic beeswax and shea butter base melts upon skin contact without leaving sticky residues on clothing.'],
            ['icon' => '🏃', 'title' => 'Desk & Athlete Mobility', 'desc' => 'Ideal for soothing posture tightness from long sitting hours as well as post-exercise muscle fatigue.']
        ],
        'faqs' => [
            ['q' => 'How often can I apply the pain relief balm?', 'a' => 'You can apply a small amount 2 to 3 times daily. Gently massage into the affected joint or muscle area for 2 minutes until absorbed.'],
            ['q' => 'How quickly does it start working?', 'a' => 'Most users feel a soothing warming sensation within 5 to 10 minutes, followed by progressive easing of stiffness for up to 4 hours.'],
            ['q' => 'Can I apply this balm on open cuts or broken skin?', 'a' => 'No. For external topical use only on intact skin. Avoid contact with eyes, mucus membranes, and broken or wounded areas.'],
            ['q' => 'Does it have a strong chemical smell?', 'a' => 'No, it has a pleasant, mild natural aroma of essential eucalyptus, camphor, and mint, without synthetic artificial fragrances.']
        ]
    ],
    'pet-wellness' => [
        'slug' => 'pet-wellness',
        'title' => 'Veterinary Pet Wellness Extracts',
        'badge' => 'Veterinarian Formulated 0.0% THC Drops',
        'icon' => '🐾',
        'description' => 'Gentle broad-spectrum botanical tinctures formulated specifically for dogs and cats to soothe noise anxiety, travel distress, and aging joint mobility.',
        'seo_title' => 'Pet CBD & Botanical Drops for Dogs & Cats - KAMS HEMP',
        'seo_desc' => 'Broad-spectrum zero-THC pet CBD drops for separation anxiety, loud fireworks distress, and senior dog joint stiffness. Infused with wild salmon oil.',
        'keywords' => ['pet', 'dog', 'cat', 'veterinary'],
        'benefits' => [
            ['icon' => '🐕', 'title' => 'Guaranteed 0.0% THC', 'desc' => 'Carefully purified broad spectrum extract to safeguard the unique endocannabinoid sensitivity of pets.'],
            ['icon' => '🎆', 'title' => 'Eases Fireworks & Thunder Panic', 'desc' => 'Calms rapid heartbeat and nervous trembling during loud festive crackers, thunderstorms, and travel.'],
            ['icon' => '🦴', 'title' => 'Senior Joint & Hip Mobility', 'desc' => 'Soothes morning joint stiffness in senior dogs, supporting easy standing and stairs climbing.'],
            ['icon' => '🐟', 'title' => 'Palatable Wild Salmon Oil', 'desc' => 'Delicious natural flavor that pets eagerly lap up directly from the dropper or mixed in daily food.']
        ],
        'faqs' => [
            ['q' => 'Is this safe for both dogs and cats?', 'a' => 'Yes. The formulation is completely non-toxic and calibrated for mammalian endocannabinoid systems with zero THC.'],
            ['q' => 'How much should I give my pet?', 'a' => 'For small pets under 10kg, start with 2 to 3 drops daily. For medium dogs (10–25kg), use 4 to 6 drops. For large breeds (25kg+), use 7 to 10 drops. A dosing guide is included.'],
            ['q' => 'What if my pet is picky with food?', 'a' => 'The oil is infused with real wild salmon aroma, which most pets love. You can also drop it directly onto their favorite treat or wet food.'],
            ['q' => 'How long before a stressful event should I give it?', 'a' => 'Administer the drops 45 to 60 minutes before fireworks, thunderstorms, grooming appointments, or car travel.']
        ]
    ],
    'superfoods-nutrition' => [
        'slug' => 'superfoods-nutrition',
        'title' => 'Superfoods & Plant Nutrition',
        'badge' => 'Complete Himalayan Plant Protein & Omegas',
        'icon' => '🥗',
        'description' => 'Raw shelled hemp hearts and nutrient-dense Ayurvedic seeds providing complete plant protein, optimal 3:1 Omega fats, and vital minerals for whole-body vitality.',
        'seo_title' => 'Hemp Hearts & Ayurvedic Superfood Seeds - KAMS HEMP',
        'seo_desc' => 'Buy 100% raw shelled hemp seeds online in India. 10g complete plant protein per serving, rich in Omega 3 & 6, magnesium, and dietary fiber. Non-GMO.',
        'keywords' => ['hemp', 'seed', 'superfood', 'hearts', 'protein', 'nutrition'],
        'benefits' => [
            ['icon' => '🥜', 'title' => 'Complete Plant Protein', 'desc' => 'Contains all 9 essential amino acids in highly digestible edestin and albumin forms with zero gut heaviness.'],
            ['icon' => '🥑', 'title' => 'Optimal 3:1 Omegas', 'desc' => 'Nature’s perfectly balanced ratio of Omega-6 to Omega-3 fatty acids for cardiovascular and skin health.'],
            ['icon' => '🌱', 'title' => '100% Raw & Himalayan', 'desc' => 'Gently cold-hulled from non-GMO seeds with zero heat processing or synthetic preservatives.'],
            ['icon' => '⚡', 'title' => 'Daily Mineral Energy', 'desc' => 'High natural content of magnesium, zinc, iron, and phosphorus to fuel daily metabolic vitality.']
        ],
        'faqs' => [
            ['q' => 'Do I need to cook or soak hemp hearts before eating?', 'a' => 'No. Raw shelled hemp hearts are ready to eat right out of the pouch. They have a pleasant nutty flavor and soft, creamy texture.'],
            ['q' => 'How do I add them to daily Indian meals?', 'a' => 'Sprinkle 2 tablespoons over dalia, curd/raita, poha, oats, fresh salads, or blend into fruit smoothies and gravies.'],
            ['q' => 'How should I store the hemp hearts pouch?', 'a' => 'Keep the resealable pouch tightly sealed in a cool, dry pantry or in the refrigerator after opening to keep the delicate Omega oils fresh.'],
            ['q' => 'Can children and senior citizens eat hemp hearts?', 'a' => 'Absolutely. Hemp seeds contain zero cannabinoids or THC, making them a safe, hypoallergenic, and nutrient-rich whole food for all ages.']
        ]
    ]
];

// Determine requested slug
$requestedSlug = trim($_GET['cat'] ?? ($_GET['slug'] ?? ($_GET['category'] ?? '')));
$currentCategory = null;

// Slug alias matching
$aliasMap = [
    'oils' => 'oils-tinctures',
    'oils-extracts' => 'oils-tinctures',
    'oils & extracts' => 'oils-tinctures',
    'oils & tinctures' => 'oils-tinctures',
    'sleep' => 'sleep-restorative',
    'sleep-recovery' => 'sleep-restorative',
    'sleep & recovery' => 'sleep-restorative',
    'sleep & restorative' => 'sleep-restorative',
    'balms' => 'pain-relief-balms',
    'balms-topicals' => 'pain-relief-balms',
    'topicals' => 'pain-relief-balms',
    'balms & topicals' => 'pain-relief-balms',
    'pets' => 'pet-wellness',
    'pet-care' => 'pet-wellness',
    'pet wellness' => 'pet-wellness',
    'superfoods' => 'superfoods-nutrition',
    'nutrition' => 'superfoods-nutrition',
    'superfoods & nutrition' => 'superfoods-nutrition',
    'edibles-wellness' => 'superfoods-nutrition'
];

$normalizedSlug = strtolower(trim($requestedSlug));
if (isset($aliasMap[$normalizedSlug])) {
    $normalizedSlug = $aliasMap[$normalizedSlug];
}

if (!empty($normalizedSlug) && isset($categoriesConfig[$normalizedSlug])) {
    $currentCategory = $categoriesConfig[$normalizedSlug];
} else {
    // Default to first category if requested is unknown or 'all'
    $currentCategory = $categoriesConfig['oils-tinctures'];
}

Analytics::trackPage($currentCategory['title'] . ' | ' . $storeName);

// Fetch products from database
$db = Database::getInstance();
$allProducts = [];
try {
    $prodStmt = $db->query("SELECT p.*, c.name as cat_name 
                            FROM `products` p 
                            LEFT JOIN `categories` c ON p.category_id = c.id 
                            WHERE p.status != 'Inactive' 
                            ORDER BY p.id ASC");
    $dbRows = $prodStmt->fetchAll();

    foreach ($dbRows as $item) {
        $images = Inventory::getProductImages($item['id']);
        $primaryImg = Inventory::getPrimaryImage($images);
        $secondaryImg = Inventory::getSecondaryImage($images);

        $rawPrice = (float)$item['price'];
        $mrpVal = !empty($item['mrp']) ? (float)$item['mrp'] : round($rawPrice * 1.25);
        $discountPercent = ($mrpVal > $rawPrice) ? round((($mrpVal - $rawPrice) / $mrpVal) * 100) : 0;
        $badge = !empty($item['sale_badge']) ? $item['sale_badge'] : ($discountPercent > 0 ? "{$discountPercent}% OFF" : '');
        $card = get_product_card_data($item);

        $allProducts[] = array_merge($card, [
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
    error_log("Error loading products for category: " . $e->getMessage());
}

// Filter products matching current category keywords
$categoryProducts = [];
$otherProducts = [];

foreach ($allProducts as $p) {
    $matches = false;
    $searchTarget = strtolower($p['title'] . ' ' . $p['category'] . ' ' . $p['short_benefit']);
    
    foreach ($currentCategory['keywords'] as $kw) {
        if (strpos($searchTarget, strtolower($kw)) !== false) {
            $matches = true;
            break;
        }
    }
    
    if ($matches) {
        $categoryProducts[] = $p;
    } else {
        $otherProducts[] = $p;
    }
}

// If no direct matches, show all products
if (empty($categoryProducts)) {
    $categoryProducts = $allProducts;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => $currentCategory['seo_title'],
        'description' => $currentCategory['seo_desc']
    ]);
    ?>
    <style>
        .category-hero {
            padding: 56px 0 44px 0;
            background: radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.09) 0%, transparent 70%);
            border-bottom: 1px solid var(--theme-border);
            text-align: center;
        }
        .category-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(229, 195, 120, 0.12);
            border: 1px solid var(--theme-gold);
            color: var(--theme-gold);
            font-size: 12px;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: var(--radius-full);
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .category-title {
            font-family: var(--font-heading);
            font-size: 38px;
            font-weight: 900;
            color: #ffffff;
            margin-bottom: 14px;
            letter-spacing: -0.5px;
        }
        .category-desc {
            color: var(--theme-text-secondary);
            font-size: 16px;
            line-height: 1.7;
            max-width: 680px;
            margin: 0 auto 28px auto;
        }

        /* Category Nav Tabs */
        .category-tabs-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 24px;
        }
        .category-tab-btn {
            padding: 8px 18px;
            border-radius: var(--radius-full);
            border: 1px solid var(--theme-border);
            background: rgba(255, 255, 255, 0.03);
            color: var(--theme-text-secondary);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .category-tab-btn:hover {
            border-color: var(--theme-primary);
            color: #ffffff;
            background: rgba(0, 255, 204, 0.08);
        }
        .category-tab-btn.active {
            background: var(--theme-primary);
            border-color: var(--theme-primary);
            color: #0b0d14;
            font-weight: 800;
            box-shadow: 0 0 15px var(--theme-primary-glow);
        }

        /* 4 Key Benefits Grid */
        .cat-benefits-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 44px 0 60px 0;
        }
        .cat-benefit-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 22px 18px;
            transition: all 0.2s ease;
        }
        .cat-benefit-card:hover {
            border-color: var(--theme-primary);
            transform: translateY(-3px);
        }
        .cat-benefit-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .cat-benefit-card h4 {
            font-size: 14.5px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .cat-benefit-card p {
            font-size: 12.5px;
            color: var(--theme-text-secondary);
            line-height: 1.55;
        }

        /* FAQs Accordion */
        .category-faq-box {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            padding: 36px;
            margin: 60px 0;
        }
        .faq-item {
            border-bottom: 1px solid var(--theme-border);
            padding: 16px 0;
        }
        .faq-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .faq-trigger {
            width: 100%;
            background: none;
            border: none;
            text-align: left;
            color: #ffffff;
            font-size: 15.5px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0;
        }
        .faq-answer {
            display: none;
            padding-top: 12px;
            color: var(--theme-text-secondary);
            font-size: 14px;
            line-height: 1.7;
        }
        .faq-item.open .faq-answer {
            display: block;
        }
        .faq-item.open svg {
            transform: rotate(180deg);
        }

        @media (max-width: 1024px) {
            .cat-benefits-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 640px) {
            .category-title {
                font-size: 28px;
            }
            .cat-benefits-grid {
                grid-template-columns: 1fr;
            }
            .category-faq-box {
                padding: 24px;
            }
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <!-- Category Hero -->
        <section class="category-hero">
            <div class="theme-container">
                <div class="category-pill">
                    <?= htmlspecialchars($currentCategory['badge']) ?>
                </div>
                <h1 class="category-title">
                    <?= $currentCategory['icon'] ?> <?= htmlspecialchars($currentCategory['title']) ?>
                </h1>
                <p class="category-desc">
                    <?= htmlspecialchars($currentCategory['description']) ?>
                </p>

                <!-- Category Switcher Tabs -->
                <div class="category-tabs-bar">
                    <?php foreach ($categoriesConfig as $key => $cfg): ?>
                        <a href="category.php?cat=<?= $cfg['slug'] ?>" 
                           class="category-tab-btn <?= $currentCategory['slug'] === $cfg['slug'] ? 'active' : '' ?>">
                            <?= $cfg['icon'] ?> <?= htmlspecialchars($cfg['title']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <div class="theme-container">
            <!-- 4 Category Benefits -->
            <div class="cat-benefits-grid">
                <?php foreach ($currentCategory['benefits'] as $b): ?>
                    <div class="cat-benefit-card">
                        <div class="cat-benefit-icon"><?= $b['icon'] ?></div>
                        <h4><?= htmlspecialchars($b['title']) ?></h4>
                        <p><?= htmlspecialchars($b['desc']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Products Grid for Category -->
            <div style="margin-bottom: 60px;">
                <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h2 style="font-family:var(--font-heading); font-size:26px; color:#ffffff; font-weight:800; margin-bottom:4px;">
                            Available Formulations (<?= count($categoryProducts) ?>)
                        </h2>
                        <p style="color:var(--theme-text-muted); font-size:13.5px;">
                            Practitioner-grade products tested for safety, potency, and purity.
                        </p>
                    </div>
                    <a href="cbd-products.php" class="btn-outline" style="font-size:12.5px; padding:6px 16px;">
                        View All Categories →
                    </a>
                </div>

                <div class="theme-products-grid">
                    <?php foreach ($categoryProducts as $p): ?>
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
                                        <a href="cart.php?action=add&id=<?= $p['id'] ?>" class="theme-btn-add">
                                            <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" stroke-width="2" fill="none"><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                            Add to Cart
                                        </a>
                                        <a href="cart.php?action=add&id=<?= $p['id'] ?>&redirect=checkout" class="theme-btn-buy">
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
            </div>

            <!-- Other Recommended Formulations -->
            <?php if (!empty($otherProducts)): ?>
                <div style="margin-bottom: 60px; padding-top: 30px; border-top: 1px solid var(--theme-border);">
                    <h3 style="font-family:var(--font-heading); font-size:20px; color:#ffffff; font-weight:700; margin-bottom:20px;">
                        Explore Other Categories
                    </h3>
                    <div class="theme-products-grid">
                        <?php foreach (array_slice($otherProducts, 0, 3) as $op): ?>
                            <div class="theme-product-card">
                                <a href="product_details.php?id=<?= $op['id'] ?>" class="theme-product-img-box">
                                    <img src="<?= htmlspecialchars($op['image']) ?>" alt="<?= htmlspecialchars($op['title']) ?>" class="theme-product-img" loading="lazy">
                                    <span class="theme-badge-stock"><?= htmlspecialchars($op['status']) ?></span>
                                </a>
                                <div class="theme-product-body">
                                    <div class="theme-product-cat"><?= htmlspecialchars($op['category']) ?></div>
                                    <a href="product_details.php?id=<?= $op['id'] ?>" class="theme-product-title"><?= htmlspecialchars($op['title']) ?></a>
                                    <div class="theme-product-benefit"><?= htmlspecialchars($op['short_benefit']) ?></div>
                                    <div class="theme-product-pricing">
                                        <span class="theme-price-current"><?= $op['price'] ?></span>
                                    </div>
                                    <div class="theme-product-actions">
                                        <a href="cart.php?action=add&id=<?= $op['id'] ?>" class="theme-btn-add">Add to Cart</a>
                                        <a href="cart.php?action=add&id=<?= $op['id'] ?>&redirect=checkout" class="theme-btn-buy">Buy Now</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Category Specific FAQs Accordion -->
            <div class="category-faq-box">
                <div style="text-align:center; margin-bottom:28px;">
                    <div style="font-size:11.5px; font-weight:800; color:var(--theme-primary); text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">Frequently Asked Questions</div>
                    <h3 style="font-family:var(--font-heading); font-size:24px; color:#ffffff; font-weight:800;">
                        Questions About <?= htmlspecialchars($currentCategory['title']) ?>
                    </h3>
                </div>

                <div>
                    <?php foreach ($currentCategory['faqs'] as $idx => $f): ?>
                        <div class="faq-item <?= $idx === 0 ? 'open' : '' ?>">
                            <button type="button" class="faq-trigger" onclick="this.parentElement.classList.toggle('open')">
                                <span><?= htmlspecialchars($f['q']) ?></span>
                                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                            <div class="faq-answer">
                                <?= htmlspecialchars($f['a']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- B2B Wholesale Strip (Secondary Prominence) -->
            <div style="background:linear-gradient(135deg, rgba(19, 26, 41, 0.95) 0%, rgba(15, 21, 34, 0.95) 100%); border:1px solid rgba(0, 255, 204, 0.25); border-radius:var(--radius-xl); padding:32px 36px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px; margin-bottom:70px;">
                <div>
                    <div style="font-size:11.5px; font-weight:800; color:var(--theme-primary); text-transform:uppercase; margin-bottom:4px;">Wholesale Supply</div>
                    <h3 style="color:#ffffff; font-family:var(--font-heading); font-size:22px; font-weight:800; margin-bottom:6px;">
                        Need Bulk Quantities of <?= htmlspecialchars($currentCategory['title']) ?>?
                    </h3>
                    <p style="color:var(--theme-text-secondary); font-size:13.5px; max-width:600px;">
                        Order directly for your clinic, retail pharmacy, or distribution center with wholesale trade margins, NABL COAs, and GST invoices.
                    </p>
                </div>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <a href="b2b.php" class="btn-primary" style="padding:12px 24px; font-size:13.5px;">Enquire for Bulk Order / Get B2B Price →</a>
                    <a href="b2b.php#catalog" class="btn-outline" style="padding:12px 20px; font-size:13px;">View Catalog</a>
                </div>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
