<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = getDB();

$artifactDir = 'C:/Users/Aman Chaursiya/.gemini/antigravity/brain/f8feb74d-42d5-4c40-8efb-331d36e6ce47/';
$productsDir = __DIR__ . '/../uploads/products/';
$bannersDir = __DIR__ . '/../uploads/banners/';

if (!is_dir($productsDir)) mkdir($productsDir, 0777, true);
if (!is_dir($bannersDir)) mkdir($bannersDir, 0777, true);

$imageMappings = [
    1 => [
        'primary' => 'premium_vijaya_extract_1791008755330.jpg',
        'alt'     => 'premium_vijaya_alternate_1791008779422.jpg',
        'target_primary' => 'p1_vijaya_primary.jpg',
        'target_alt'     => 'p1_vijaya_alt.jpg'
    ],
    2 => [
        'primary' => 'deep_sleep_drops_1791008719361.jpg',
        'alt'     => 'deep_sleep_alternate_1791008735810.jpg',
        'target_primary' => 'p2_sleep_primary.jpg',
        'target_alt'     => 'p2_sleep_alt.jpg'
    ],
    3 => [
        'primary' => 'pain_relief_balm_1791008667005.jpg',
        'alt'     => 'pain_relief_alternate_1791008701111.jpg',
        'target_primary' => 'p3_balm_primary.jpg',
        'target_alt'     => 'p3_balm_alt.jpg'
    ],
    4 => [
        'primary' => 'athletic_recovery_tincture_1791008626266.jpg',
        'alt'     => 'athletic_recovery_alternate_1791008654793.jpg',
        'target_primary' => 'p4_athletic_primary.jpg',
        'target_alt'     => 'p4_athletic_alt.jpg'
    ],
    5 => [
        'primary' => 'calming_pet_cbd_1791008459877.jpg',
        'alt'     => 'calming_pet_alternate_1791008599045.jpg',
        'target_primary' => 'p5_pet_primary.jpg',
        'target_alt'     => 'p5_pet_alt.jpg'
    ],
    6 => [
        'primary' => 'hemp_hearts_seeds_1791008434742.jpg',
        'alt'     => 'hemp_hearts_alternate_1791008445870.jpg',
        'target_primary' => 'p6_hemp_primary.jpg',
        'target_alt'     => 'p6_hemp_alt.jpg'
    ],
];

// 1. Copy Banner
$heroSource = $artifactDir . 'hero_banner_main_1791008797987.jpg';
$heroDest = $bannersDir . 'hero_banner_main.jpg';
if (file_exists($heroSource)) {
    copy($heroSource, $heroDest);
    echo "Copied Hero Banner: $heroDest\n";
} else {
    echo "Warning: Hero banner source not found at $heroSource\n";
}

// 2. Copy Product Images and update product_images table
foreach ($imageMappings as $productId => $imgs) {
    $srcPri = $artifactDir . $imgs['primary'];
    $dstPri = $productsDir . $imgs['target_primary'];
    $srcAlt = $artifactDir . $imgs['alt'];
    $dstAlt = $productsDir . $imgs['target_alt'];

    if (file_exists($srcPri)) {
        copy($srcPri, $dstPri);
        echo "Copied Product $productId Primary: {$imgs['target_primary']}\n";
    }
    if (file_exists($srcAlt)) {
        copy($srcAlt, $dstAlt);
        echo "Copied Product $productId Alt: {$imgs['target_alt']}\n";
    }

    // Delete existing images for this product
    $del = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
    $del->execute([$productId]);

    // Insert primary image
    $ins = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_primary, is_secondary, sort_order, created_at) VALUES (?, ?, 1, 0, 1, NOW())");
    $ins->execute([$productId, 'uploads/products/' . $imgs['target_primary']]);

    // Insert secondary image
    $insAlt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_primary, is_secondary, sort_order, created_at) VALUES (?, ?, 0, 1, 2, NOW())");
    $insAlt->execute([$productId, 'uploads/products/' . $imgs['target_alt']]);
}

// 3. Update products table
$productUpdates = [
    1 => [
        'name' => 'Premium Vijaya Extract 1500mg',
        'price' => 2999.00,
        'mrp' => 3999.00,
        'sale_badge' => '25% OFF ⚡',
        'stock' => 150,
        'status' => 'Active',
        'featured' => 1,
        'category_name' => 'Oils & Extracts',
        'potency' => '1500mg',
        'extract_type' => 'Full Spectrum Vijaya',
        'description' => 'Unrefined, full-spectrum Vijaya leaf extract containing high concentrations of CBD, natural minor cannabinoids, and soothing botanical terpenes. Formulated according to Ayurvedic texts for deep bodily equilibrium, stress mitigation, and cellular renewal.'
    ],
    2 => [
        'name' => 'Deep Sleep Restorative Drops',
        'price' => 1499.00,
        'mrp' => 2000.00,
        'sale_badge' => '25% OFF ⚡',
        'stock' => 120,
        'status' => 'Active',
        'featured' => 1,
        'category_name' => 'Sleep & Recovery',
        'potency' => '2500mg',
        'extract_type' => 'Nighttime Terpene Blend',
        'description' => 'A synergistic botanical formulation uniting organic melatonin, chamomile, and broad-spectrum CBD to support circadian rhythm balance and deep, undisturbed REM sleep cycles without next-day grogginess.'
    ],
    3 => [
        'name' => 'Full Spectrum Pain Relief Balm',
        'price' => 999.00,
        'mrp' => 1299.00,
        'sale_badge' => 'Bestseller',
        'stock' => 140,
        'status' => 'Active',
        'featured' => 1,
        'category_name' => 'Balms & Topicals',
        'potency' => '500mg',
        'extract_type' => 'Arnica & Hemp Topical',
        'description' => 'Rapid-absorption soothing topical balm infused with organic Vijaya extract, arnica montana, camphor, and eucalyptus oil. Provides targeted cooling and warming relief to stiff joints, chronic soreness, and fatigued muscle groups.'
    ],
    4 => [
        'name' => 'Athletic Recovery Tincture',
        'price' => 3499.00,
        'mrp' => 3999.00,
        'sale_badge' => 'Pro Athlete',
        'stock' => 95,
        'status' => 'Active',
        'featured' => 1,
        'category_name' => 'Oils & Extracts',
        'potency' => '3000mg',
        'extract_type' => 'Broad Spectrum Recovery',
        'description' => 'Engineered for athletes, fitness enthusiasts, and demanding active lifestyles. High-concentration organic cannabinoid profile tailored to attenuate post-workout inflammation, combat muscle soreness, and accelerate bodily restoration.'
    ],
    5 => [
        'name' => 'Calming Pet CBD Oil',
        'price' => 1299.00,
        'mrp' => 1599.00,
        'sale_badge' => 'Veterinary Safe',
        'stock' => 250,
        'status' => 'Active',
        'featured' => 1,
        'category_name' => 'Pet Wellness',
        'potency' => '250mg',
        'extract_type' => 'Organic Pet Friendly Formula',
        'description' => 'Veterinarian-guided, gentle hemp extract in cold-pressed virgin hemp seed oil. Designed to calm separation anxiety, hyper-reactivity to loud noises, joint stiffness, and age-related discomfort in both dogs and cats.'
    ],
    6 => [
        'name' => 'Hemp Hearts Seeds 250g',
        'price' => 499.00,
        'mrp' => 699.00,
        'sale_badge' => 'Superfood',
        'stock' => 220,
        'status' => 'Active',
        'featured' => 1,
        'category_name' => 'Superfoods & Nutrition',
        'potency' => '100% Organic Raw',
        'extract_type' => 'Cold-hulled Raw Hearts',
        'description' => 'Raw, de-hulled certified organic hemp seeds packed with complete plant-based protein, all 9 essential amino acids, and the optimal 3:1 ratio of Omega-6 to Omega-3 fatty acids. Perfect addition to smoothies, bowls, and salads.'
    ]
];

$updStmt = $pdo->prepare("
    UPDATE products SET 
        name = :name, 
        price = :price, 
        mrp = :mrp, 
        sale_badge = :sale_badge, 
        stock = :stock, 
        status = :status, 
        featured = :featured, 
        category_name = :category_name, 
        potency = :potency, 
        extract_type = :extract_type, 
        description = :description, 
        updated_at = NOW() 
    WHERE id = :id
");

foreach ($productUpdates as $id => $data) {
    $data['id'] = $id;
    $updStmt->execute($data);
    echo "Updated Product $id: {$data['name']} (Price: ₹{$data['price']}, Stock: {$data['stock']})\n";
}

// 4. Update Settings Table for storefront banners & referrals
$settingsToAdd = [
    'hero_title' => 'Ancient Vedic Healing, Powered by Modern Science',
    'hero_subtitle' => "Explore India's most certified Full-Spectrum Vijaya & CBD extracts. Lab tested, doctor prescribed, and AYUSH compliant.",
    'hero_badge' => '🌿 100% Certified Organic Vijaya Extract',
    'hero_banner_image' => 'uploads/banners/hero_banner_main.jpg',
    'hero_cta_text' => 'Shop Ayurvedic Extracts',
    'hero_cta_link' => '#products-grid',
    'announcement_bar_text' => '⚡ Special Launch Offer: Refer a friend to get 10% OFF, and earn 10% Cashback on EVERY order forever! Free shipping above ₹3,999.',
    'referral_banner_title' => 'Give 10% Discount, Earn 10% Recurring Cashback',
    'referral_banner_subtitle' => 'Share your unique referral link with friends. They get an instant 10% discount on checkout, and you receive 10% cash reward into your wallet on every purchase they ever make!'
];

require_once __DIR__ . '/../includes/settings.php';
Settings::setBatch($settingsToAdd);
foreach ($settingsToAdd as $k => $v) {
    echo "Configured Setting: $k\n";
}

echo "=== DEPLOYMENT COMPLETED SUCCESSFULLY ===\n";
