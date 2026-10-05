<?php
// search.php - Dedicated Product Search Results Page for KAMS HEMP
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

$query = trim($_GET['q'] ?? $_GET['search'] ?? '');
Analytics::trackPage('Search: ' . $query . ' | KAMS HEMP');

$storeName = Settings::get('store_name', 'KAMS HEMP');
$db = Database::getInstance();

$products = [];
try {
    if (!empty($query)) {
        $stmt = $db->prepare("SELECT p.*, c.name as cat_name 
                              FROM `products` p 
                              LEFT JOIN `categories` c ON p.category_id = c.id 
                              WHERE p.status != 'Inactive' 
                                AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.sku LIKE ?)
                              ORDER BY p.id ASC");
        $term = '%' . $query . '%';
        $stmt->execute([$term, $term, $term, $term]);
        $rows = $stmt->fetchAll();
        
        foreach ($rows as $item) {
            $images = Inventory::getProductImages($item['id']);
            $item['primary_image'] = Inventory::getPrimaryImage($images);
            $products[] = $item;
        }
    } else {
        // If empty query, show popular products
        $stmt = $db->query("SELECT p.*, c.name as cat_name FROM `products` p LEFT JOIN `categories` c ON p.category_id = c.id WHERE p.status != 'Inactive' ORDER BY p.id ASC LIMIT 8");
        $rows = $stmt->fetchAll();
        foreach ($rows as $item) {
            $images = Inventory::getProductImages($item['id']);
            $item['primary_image'] = Inventory::getPrimaryImage($images);
            $products[] = $item;
        }
    }
} catch (Throwable $e) {
    error_log("Search error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => (!empty($query) ? 'Search Results for "' . htmlspecialchars($query) . '"' : 'Search Products'),
        'description' => 'Search certified Ayurvedic Vijaya extracts, pain balms, sleep drops, and wellness supplements.'
    ]);
    ?>
    <style>
        .search-page-header {
            padding: 50px 0 30px 0;
            background: radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.06) 0%, transparent 70%);
        }
        .search-page-title {
            font-family: var(--font-heading);
            font-size: 32px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
        }
        .search-form-large {
            max-width: 650px;
            margin: 24px 0 0 0;
            position: relative;
        }
        .search-input-lg {
            width: 100%;
            height: 52px;
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-full);
            padding: 0 110px 0 24px;
            color: #ffffff;
            font-size: 15px;
            outline: none;
            transition: all 0.25s ease;
        }
        .search-input-lg:focus {
            border-color: var(--theme-primary);
            box-shadow: 0 0 20px var(--theme-primary-glow);
        }
        .search-submit-lg {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            height: 40px;
            padding: 0 20px;
            border-radius: var(--radius-full);
            background: var(--theme-primary);
            color: #0b0d14;
            font-weight: 700;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }
        .results-meta {
            margin: 28px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--theme-text-secondary);
            font-size: 14px;
            border-bottom: 1px solid var(--theme-border);
            padding-bottom: 16px;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
            padding-bottom: 80px;
        }
        @media (max-width: 600px) {
            .products-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }
        .empty-search-box {
            text-align: center;
            padding: 60px 20px;
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            margin: 40px 0;
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <section class="search-page-header">
            <div class="theme-container">
                <h1 class="search-page-title">
                    <?= !empty($query) ? 'Results for "' . htmlspecialchars($query) . '"' : 'Search Ayurvedic Formulations' ?>
                </h1>
                <p style="color:var(--theme-text-secondary);">Browse standardized Vijaya cannabis tinctures, gummies, and clinical balms.</p>

                <form action="search.php" method="GET" class="search-form-large">
                    <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" class="search-input-lg" placeholder="Search by name, symptom, or extract type...">
                    <button type="submit" class="search-submit-lg">Search</button>
                </form>
            </div>
        </section>

        <section>
            <div class="theme-container">
                <div class="results-meta">
                    <div>
                        Showing <strong><?= count($products) ?></strong> formulations
                        <?= !empty($query) ? 'matching "<em>' . htmlspecialchars($query) . '</em>"' : '' ?>
                    </div>
                    <div>
                        <a href="cbd-products.php" style="color:var(--theme-gold); font-weight:600;">View Full Catalog →</a>
                    </div>
                </div>

                <?php if (!empty($products)): ?>
                    <div class="products-grid">
                        <?php foreach ($products as $p): 
                            $price = (float)$p['price'];
                            $mrp = (float)($p['mrp'] ?? 0);
                            $discount = ($mrp > $price) ? round((($mrp - $price) / $mrp) * 100) : 0;
                            $card = get_product_card_data($p);
                        ?>
                            <div class="theme-product-card">
                                <a href="product_details.php?id=<?= $p['id'] ?>" class="theme-product-img-box">
                                    <img src="<?= htmlspecialchars($p['primary_image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="theme-product-img" loading="lazy">
                                    <?php if ($discount > 0): ?>
                                        <span class="theme-badge-sale"><?= $discount ?>% OFF</span>
                                    <?php endif; ?>
                                    <span class="theme-badge-stock"><?= htmlspecialchars($p['status']) ?></span>
                                </a>

                                <div class="theme-product-body">
                                    <div class="theme-product-cat"><?= htmlspecialchars($p['cat_name'] ?? 'Ayurvedic Extract') ?></div>
                                    <a href="product_details.php?id=<?= $p['id'] ?>" class="theme-product-title">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </a>

                                    <div class="theme-product-benefit">
                                        <?= htmlspecialchars($card['short_benefit']) ?>
                                    </div>

                                    <div class="theme-product-rating">
                                        <span class="theme-stars">★★★★★</span>
                                        <span class="theme-rating-val"><?= $card['rating'] ?></span>
                                        <span class="theme-review-count">(<?= $card['reviews_count'] ?> reviews)</span>
                                    </div>

                                    <div class="theme-product-pricing">
                                        <span class="theme-price-current">₹<?= number_format($price) ?></span>
                                        <?php if ($mrp > $price): ?>
                                            <span class="theme-price-mrp">₹<?= number_format($mrp) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="theme-product-actions">
                                        <?php if ((int)$p['stock'] > 0): ?>
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
                <?php else: ?>
                    <div class="empty-search-box">
                        <div style="font-size:42px; margin-bottom:12px;">🔍</div>
                        <h3 style="font-family:var(--font-heading); font-size:22px; color:#fff; margin-bottom:8px;">No formulations matched your query</h3>
                        <p style="color:var(--theme-text-secondary); max-width:480px; margin:0 auto 24px auto;">Try searching for generic terms like "Vijaya", "Sleep Drops", "Balm", or browse our complete collection.</p>
                        <a href="cbd-products.php" class="btn-primary">Browse All Formulations</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
