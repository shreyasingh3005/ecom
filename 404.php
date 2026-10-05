<?php
// 404.php - Custom 404 Error & Recovery Page for KAMS HEMP
http_response_code(404);
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('404 Not Found | KAMS HEMP');
$storeName = Settings::get('store_name', 'KAMS HEMP');

// Fetch top products for quick recovery
$db = Database::getInstance();
$stmt = $db->query("SELECT p.*, c.name as cat_name FROM `products` p LEFT JOIN `categories` c ON p.category_id = c.id WHERE p.status != 'Inactive' LIMIT 4");
$popular = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => '404 - Page Not Found',
        'description' => 'The page you requested could not be located. Browse our Ayurvedic Vijaya wellness catalog.'
    ]);
    ?>
    <style>
        .error-hero {
            padding: 80px 20px 60px 20px;
            text-align: center;
        }
        .error-code {
            font-size: 80px;
            font-family: var(--font-heading);
            font-weight: 900;
            line-height: 1;
            background: linear-gradient(135deg, var(--theme-primary) 0%, var(--theme-gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 14px;
        }
        .error-title {
            font-family: var(--font-heading);
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
        }
        .error-desc {
            color: var(--theme-text-secondary);
            font-size: 16px;
            max-width: 520px;
            margin: 0 auto 32px auto;
            line-height: 1.6;
        }
        .error-search-box {
            max-width: 500px;
            margin: 0 auto 36px auto;
            position: relative;
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <section class="error-hero">
            <div class="theme-container">
                <div class="error-code">404</div>
                <h1 class="error-title">Formulation Pathway Not Found</h1>
                <p class="error-desc">The Ayurvedic preparation or resource you are looking for might have been relocated, discontinued, or the address entered contains a typo.</p>

                <div class="error-search-box">
                    <form action="cbd-products.php" method="GET">
                        <input type="text" name="search" placeholder="Search our catalog of Ayurvedic extracts..." class="theme-search-input" style="height:48px; padding-right:50px;">
                        <button type="submit" class="theme-search-btn" style="width:40px; height:40px;">
                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </button>
                    </form>
                </div>

                <div style="display:flex; justify-content:center; gap:16px; flex-wrap:wrap;">
                    <a href="cbd.php" class="btn-primary">Return to Homepage</a>
                    <a href="cbd-products.php" class="btn-outline">Explore All Products</a>
                </div>
            </div>
        </section>

        <!-- Popular Recovery Formulations -->
        <?php if (!empty($popular)): ?>
            <section style="padding: 40px 0 80px 0; border-top: 1px solid var(--theme-border);">
                <div class="theme-container">
                    <h3 style="font-family:var(--font-heading); font-size:22px; color:#fff; text-align:center; margin-bottom:32px;">You Might Be Looking For These Best Sellers</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:24px;">
                        <?php foreach ($popular as $p): 
                            $images = Inventory::getProductImages($p['id']);
                            $img = Inventory::getPrimaryImage($images);
                        ?>
                            <div class="theme-product-card">
                                <a href="product_details.php?id=<?= $p['id'] ?>" class="theme-product-img-box">
                                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="theme-product-img" loading="lazy">
                                </a>
                                <div class="theme-product-body">
                                    <div class="theme-product-cat"><?= htmlspecialchars($p['cat_name'] ?? 'Ayurvedic Extract') ?></div>
                                    <a href="product_details.php?id=<?= $p['id'] ?>" class="theme-product-title"><?= htmlspecialchars($p['name']) ?></a>
                                    <div class="theme-product-pricing">
                                        <span class="theme-price-current">₹<?= number_format((float)$p['price']) ?></span>
                                    </div>
                                    <a href="product_details.php?id=<?= $p['id'] ?>" class="btn-outline" style="width:100%; font-size:12px; text-align:center;">View Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
