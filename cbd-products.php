<?php
// cbd-products.php - Premium Product Catalog for KAMS HEMP
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

Analytics::trackPage('Shop All Products | KAMS HEMP');

$storeName = Settings::get('store_name', 'KAMS HEMP');
$db = Database::getInstance();

// Load dynamic products from MySQL Database
$products = [];
try {
    $prodStmt = $db->query("SELECT p.*, c.name as cat_name 
                            FROM `products` p 
                            LEFT JOIN `categories` c ON p.category_id = c.id 
                            WHERE p.status != 'Inactive' 
                            ORDER BY p.id ASC");
    $dbItems = $prodStmt->fetchAll();

    foreach ($dbItems as $item) {
        $images = Inventory::getProductImages($item['id']);
        $primaryImg = Inventory::getPrimaryImage($images);
        $secondaryImg = Inventory::getSecondaryImage($images);

        $priceVal = (float)$item['price'];
        $mrpVal = !empty($item['mrp']) ? (float)$item['mrp'] : round($priceVal * 1.25);
        $onSale = ($mrpVal > $priceVal);
        $discountPercent = $onSale ? round((($mrpVal - $priceVal) / $mrpVal) * 100) : 0;

        $catName = !empty($item['category_name']) ? $item['category_name'] : (!empty($item['cat_name']) ? $item['cat_name'] : 'Ayurvedic Extracts');
        $card = get_product_card_data($item);

        $products[] = array_merge($card, [
            "id" => (int)$item['id'],
            "title" => $item['name'],
            "price_val" => $priceVal,
            "price" => '₹' . number_format($priceVal),
            "mrp_val" => $mrpVal,
            "mrp" => '₹' . number_format($mrpVal),
            "discount_percent" => $discountPercent,
            "on_sale" => $onSale,
            "sale_badge" => $item['sale_badge'] ?: ($discountPercent > 0 ? "{$discountPercent}% OFF" : ''),
            "category" => $catName,
            "potency" => $item['potency'] ?? 'Regular',
            "extract" => $item['extract_type'] ?? 'Full Spectrum',
            "image" => $primaryImg,
            "image2" => $secondaryImg,
            "stock" => (int)$item['stock'],
            "status" => $item['status']
        ]);
    }
} catch (Exception $e) {
    error_log("Error loading catalog: " . $e->getMessage());
}

// JSON API endpoint for live search autocomplete
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    $searchTerm = strtolower(trim($_GET['search'] ?? ''));
    $filtered = [];
    foreach ($products as $p) {
        if (empty($searchTerm) || strpos(strtolower($p['title']), $searchTerm) !== false || strpos(strtolower($p['category']), $searchTerm) !== false) {
            $filtered[] = $p;
        }
    }
    echo json_encode($filtered);
    exit;
}

// Filter parameters
$selectedCategory = $_GET['category'] ?? 'All';
$searchQuery = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'default';

// Extract unique categories
$categoriesList = array_unique(array_column($products, 'category'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Shop All Ayurvedic Vijaya & CBD Formulations',
        'description' => 'Explore certified full-spectrum Vijaya leaf oils, restorative sleep drops, organic hemp hearts, and pain relief balms. Fast discreet Pan-India delivery.'
    ]);
    ?>
    <style>
        .shop-hero {
            padding: 50px 0 32px 0;
            background: radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.08) 0%, transparent 70%);
            text-align: center;
        }
        .shop-hero h1 {
            font-family: var(--font-heading);
            font-size: 38px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 10px;
        }
        .shop-hero p {
            color: var(--theme-text-secondary);
            font-size: 16px;
            max-width: 620px;
            margin: 0 auto;
        }
        .shop-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 36px;
            padding: 36px 0 80px 0;
            align-items: flex-start;
        }
        .shop-sidebar {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 24px;
            position: sticky;
            top: calc(var(--header-height) + 20px);
        }
        .filter-section {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--theme-border);
        }
        .filter-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .filter-title {
            font-family: var(--font-heading);
            font-size: 14.5px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 13.5px;
            color: var(--theme-text-secondary);
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .filter-option:hover {
            color: #ffffff;
        }
        .filter-option input[type="radio"],
        .filter-option input[type="checkbox"] {
            accent-color: var(--theme-primary);
            width: 16px;
            height: 16px;
        }
        .shop-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--theme-border);
            flex-wrap: wrap;
            gap: 16px;
        }
        .shop-count {
            font-size: 14px;
            color: var(--theme-text-secondary);
        }
        .sort-select {
            height: 40px;
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            padding: 0 16px;
            color: #ffffff;
            font-size: 13.5px;
            outline: none;
            cursor: pointer;
        }
        .sort-select:focus {
            border-color: var(--theme-primary);
        }
        .shop-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
        }
        @media (max-width: 600px) {
            .shop-products-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }
        @media (max-width: 960px) {
            .shop-layout {
                grid-template-columns: 1fr;
            }
            .shop-sidebar {
                position: static;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <!-- Hero Section -->
        <section class="shop-hero">
            <div class="theme-container">
                <h1>Ayurvedic Botanical Formulations</h1>
                <p>Pure, standardized Vijaya cannabis leaf extracts formulated for neurological calm, restorative sleep, and joint pain relief.</p>
            </div>
        </section>

        <!-- Catalog Container -->
        <section>
            <div class="theme-container">
                <div class="shop-layout">
                    <!-- Sidebar Filters -->
                    <aside class="shop-sidebar">
                        <div class="filter-section">
                            <div class="filter-title">
                                <span>Category</span>
                                <a href="cbd-products.php" style="font-size:11px; color:var(--theme-primary);">Reset</a>
                            </div>
                            <label class="filter-option">
                                <input type="radio" name="cat_filter" value="All" <?= $selectedCategory === 'All' ? 'checked' : '' ?> onchange="applyCategory('All')">
                                <span>All Formulations</span>
                            </label>
                            <?php foreach ($categoriesList as $cat): ?>
                                <label class="filter-option">
                                    <input type="radio" name="cat_filter" value="<?= htmlspecialchars($cat) ?>" <?= $selectedCategory === $cat ? 'checked' : '' ?> onchange="applyCategory('<?= htmlspecialchars($cat) ?>')">
                                    <span><?= htmlspecialchars($cat) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="filter-section">
                            <div class="filter-title">Extract Potency</div>
                            <label class="filter-option">
                                <input type="checkbox" class="potency-filter" value="Full Spectrum" checked onchange="filterProductsClient()">
                                <span>Full Spectrum Vijaya</span>
                            </label>
                            <label class="filter-option">
                                <input type="checkbox" class="potency-filter" value="Broad Spectrum" checked onchange="filterProductsClient()">
                                <span>Broad Spectrum</span>
                            </label>
                            <label class="filter-option">
                                <input type="checkbox" class="potency-filter" value="Isolate" checked onchange="filterProductsClient()">
                                <span>Pure Isolate</span>
                            </label>
                        </div>

                        <div class="filter-section">
                            <div class="filter-title">Availability</div>
                            <label class="filter-option">
                                <input type="checkbox" id="inStockOnly" onchange="filterProductsClient()">
                                <span>In Stock Only</span>
                            </label>
                        </div>

                        <!-- B2B Wholesale Banner in Sidebar -->
                        <div style="background:rgba(0, 255, 204, 0.06); border:1px solid rgba(0, 255, 204, 0.2); border-radius:var(--radius-md); padding:16px; text-align:center;">
                            <div style="font-size:11px; font-weight:800; color:var(--theme-primary); text-transform:uppercase; margin-bottom:4px;">Wholesale Desk</div>
                            <p style="font-size:12px; color:var(--theme-text-secondary); margin-bottom:12px;">Doctor, Clinic, or Pharmacy? Order bulk with 40-60% margins.</p>
                            <a href="b2b.php" class="btn-primary" style="padding:6px 14px; font-size:11.5px; width:100%;">B2B Wholesale Portal</a>
                        </div>
                    </aside>

                    <!-- Product Grid Area -->
                    <div>
                        <!-- Toolbar -->
                        <div class="shop-toolbar">
                            <div class="shop-count" id="productCountDisplay">
                                Showing <strong><?= count($products) ?></strong> formulations
                            </div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <label for="shopSort" style="font-size:13px; color:var(--theme-text-muted);">Sort By:</label>
                                <select id="shopSort" class="sort-select" onchange="sortProductsClient(this.value)">
                                    <option value="default">Featured / Default</option>
                                    <option value="price-asc">Price: Low to High</option>
                                    <option value="price-desc">Price: High to Low</option>
                                    <option value="name-asc">Name: A to Z</option>
                                </select>
                            </div>
                        </div>

                        <!-- Cards Grid -->
                        <div class="shop-products-grid" id="productsGrid">
                            <?php foreach ($products as $p): ?>
                                <div class="theme-product-card product-item" 
                                     data-id="<?= $p['id'] ?>"
                                     data-title="<?= htmlspecialchars(strtolower($p['title'])) ?>"
                                     data-category="<?= htmlspecialchars($p['category']) ?>"
                                     data-extract="<?= htmlspecialchars($p['extract']) ?>"
                                     data-price="<?= $p['price_val'] ?>"
                                     data-stock="<?= $p['stock'] ?>">
                                    
                                    <a href="product_details.php?id=<?= $p['id'] ?>" class="theme-product-img-box">
                                        <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" class="theme-product-img" loading="lazy">
                                        <?php if ($p['on_sale'] && $p['discount_percent'] > 0): ?>
                                            <span class="theme-badge-sale"><?= $p['discount_percent'] ?>% OFF</span>
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
                                            <?php if ($p['on_sale']): ?>
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
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
        function applyCategory(cat) {
            if (cat === 'All') {
                window.location.href = 'cbd-products.php';
            } else {
                window.location.href = 'cbd-products.php?category=' + encodeURIComponent(cat);
            }
        }

        function filterProductsClient() {
            const inStock = document.getElementById('inStockOnly').checked;
            const potencyBoxes = Array.from(document.querySelectorAll('.potency-filter:checked')).map(cb => cb.value.toLowerCase());
            const items = document.querySelectorAll('.product-item');
            let visibleCount = 0;

            items.forEach(item => {
                const stock = parseInt(item.getAttribute('data-stock') || '0');
                const extract = (item.getAttribute('data-extract') || '').toLowerCase();

                let show = true;
                if (inStock && stock <= 0) show = false;
                
                if (potencyBoxes.length > 0) {
                    const matchesPotency = potencyBoxes.some(p => extract.includes(p));
                    if (!matchesPotency) show = false;
                }

                item.style.display = show ? 'flex' : 'none';
                if (show) visibleCount++;
            });

            document.getElementById('productCountDisplay').innerHTML = `Showing <strong>${visibleCount}</strong> formulations`;
        }

        function sortProductsClient(sortBy) {
            const grid = document.getElementById('productsGrid');
            const items = Array.from(grid.querySelectorAll('.product-item'));

            items.sort((a, b) => {
                const priceA = parseFloat(a.getAttribute('data-price') || 0);
                const priceB = parseFloat(b.getAttribute('data-price') || 0);
                const titleA = a.getAttribute('data-title') || '';
                const titleB = b.getAttribute('data-title') || '';

                if (sortBy === 'price-asc') return priceA - priceB;
                if (sortBy === 'price-desc') return priceB - priceA;
                if (sortBy === 'name-asc') return titleA.localeCompare(titleB);
                return parseInt(a.getAttribute('data-id')) - parseInt(b.getAttribute('data-id'));
            });

            items.forEach(item => grid.appendChild(item));
        }
    </script>
</body>
</html>