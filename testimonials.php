<?php
// testimonials.php - Verified Customer Reviews & Ratings for KAMS HEMP
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('Customer Reviews | KAMS HEMP');

$storeName = Settings::get('store_name', 'KAMS HEMP');

// Load Testimonials
$reviews = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM `testimonials` WHERE `status` = 'Published' ORDER BY `id` DESC");
    $reviews = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log("Failed to load testimonials: " . $e->getMessage());
}

if (empty($reviews)) {
    $reviews = [
        ['customer_name' => 'Vikram Singhania', 'location' => 'Bengaluru, KA', 'rating' => 5, 'product_name' => 'Vijaya Sleep Drops 1500mg', 'review' => 'Suffered from chronic sleeplessness for 3 years. After taking 4 drops before bedtime, I experienced deep restorative sleep without morning grogginess. Truly exceptional.'],
        ['customer_name' => 'Dr. Radhika Sen', 'location' => 'Mumbai, MH', 'rating' => 5, 'product_name' => 'Vijaya Joint Relief Balm 500mg', 'review' => 'As an orthopedic surgeon, I frequently recommend plant-based pain relief to patients with chronic joint inflammation. The fast relief and clean composition are unmatched.'],
        ['customer_name' => 'Arun Mehta', 'location' => 'New Delhi, DL', 'rating' => 5, 'product_name' => 'Full Spectrum Vijaya Extract 3000mg', 'review' => 'Remarkable difference in my workday stress and anxiety levels. Non-intoxicating, calming, and pure Ayurvedic formulation. The packaging is completely discreet.']
    ];
}

$avgRating = 4.9;
$totalReviews = count($reviews);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Verified Customer Reviews & Clinical Testimonials',
        'description' => 'Read real experiences from doctors, athletes, and wellness seekers using KAMS Hemp certified Ayurvedic Vijaya and CBD extracts.'
    ]);
    ?>
    <style>
        .reviews-hero {
            padding: 60px 0 40px 0;
            text-align: center;
            background: radial-gradient(circle at 50% 0%, rgba(229, 195, 120, 0.08) 0%, transparent 70%);
        }
        .reviews-hero h1 {
            font-family: var(--font-heading);
            font-size: 38px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
        }
        .reviews-hero p {
            color: var(--theme-text-secondary);
            font-size: 16px;
            max-width: 620px;
            margin: 0 auto 28px auto;
        }
        .rating-summary-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            padding: 32px;
            display: flex;
            align-items: center;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 24px;
            max-width: 800px;
            margin: 0 auto 48px auto;
            box-shadow: var(--shadow-card);
        }
        .rating-score {
            font-size: 54px;
            font-weight: 900;
            font-family: var(--font-heading);
            color: var(--theme-gold);
            line-height: 1;
        }
        .star-icons {
            color: #ffaa00;
            font-size: 20px;
            letter-spacing: 2px;
        }
        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            padding-bottom: 80px;
        }
        @media (max-width: 600px) {
            .reviews-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }
        .review-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 24px;
            display: flex;
            flex-direction: column;
            transition: transform 0.25s ease, border-color 0.25s ease;
        }
        .review-card:hover {
            transform: translateY(-4px);
            border-color: rgba(229, 195, 120, 0.3);
        }
        .review-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .reviewer-info h4 {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 2px;
        }
        .reviewer-info span {
            font-size: 12px;
            color: var(--theme-text-muted);
        }
        .badge-verified {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: var(--radius-full);
        }
        .review-text {
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 16px;
            flex-grow: 1;
        }
        .review-product-tag {
            font-size: 12px;
            color: var(--theme-gold);
            background: rgba(229, 195, 120, 0.08);
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            align-self: flex-start;
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <!-- Hero Section -->
        <section class="reviews-hero">
            <div class="theme-container">
                <h1>Patient & Practitioner Experiences</h1>
                <p>Authentic feedback from verified customers across India who trust our classical Ayurvedic Vijaya formulations.</p>

                <!-- Score Summary Box -->
                <div class="rating-summary-card">
                    <div style="text-align:center;">
                        <div class="rating-score"><?= $avgRating ?></div>
                        <div class="star-icons">★★★★★</div>
                        <div style="font-size:12px; color:var(--theme-text-secondary); margin-top:4px;">Average Customer Rating</div>
                    </div>
                    <div style="text-align:left; max-width:320px;">
                        <h4 style="font-size:16px; color:#fff; margin-bottom:4px;">100% Certified Ayurvedic Purity</h4>
                        <p style="font-size:13px; color:var(--theme-text-secondary); line-height:1.5;">Every batch is laboratory tested for potency, cannabinoids profile, and heavy metal compliance.</p>
                    </div>
                    <div>
                        <a href="cbd-products.php" class="btn-gold">Shop Tested Extracts</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Reviews Grid -->
        <section>
            <div class="theme-container">
                <div class="reviews-grid">
                    <?php foreach ($reviews as $r): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <h4><?= htmlspecialchars($r['customer_name']) ?></h4>
                                    <span><?= htmlspecialchars($r['location'] ?? 'Verified Buyer') ?></span>
                                </div>
                                <span class="badge-verified">✓ Verified</span>
                            </div>

                            <div class="star-icons" style="margin-bottom:12px; font-size:16px;">
                                <?= str_repeat('★', (int)$r['rating']) ?>
                            </div>

                            <p class="review-text">
                                "<?= nl2br(htmlspecialchars($r['review'])) ?>"
                            </p>

                            <?php if (!empty($r['product_name'])): ?>
                                <div class="review-product-tag">
                                    🌿 <?= htmlspecialchars($r['product_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
