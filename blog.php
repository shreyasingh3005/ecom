<?php
// blog.php - Ayurvedic Research & Clinical Blog Articles
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('Research & Science Blog | KAMS HEMP');

$storeName = Settings::get('store_name', 'KAMS HEMP');
$posts = [];
$categories = [];

try {
    $db = Database::getInstance();
    $categoryFilter = $_GET['category'] ?? '';
    $sql = "SELECT * FROM `blogs` WHERE `status` = 'Published'";
    $params = [];
    if (!empty($categoryFilter)) {
        $sql .= " AND `category` = ?";
        $params[] = $categoryFilter;
    }
    $sql .= " ORDER BY `id` DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    // Get unique categories
    $catStmt = $db->query("SELECT DISTINCT `category` FROM `blogs` WHERE `status` = 'Published'");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    error_log("Failed to load blog posts: " . $e->getMessage());
}

if (empty($posts)) {
    $posts = [
        [
            'id' => 1,
            'title' => 'Ayurvedic Vijaya Extract and Sleep Architecture: A Clinical Deep Dive',
            'slug' => 'ayurvedic-vijaya-sleep-architecture',
            'category' => 'Sleep Science',
            'excerpt' => 'Exploring how full-spectrum phyto-cannabinoids interact with CB1 receptors in the hypothalamus to restore natural sleep cycles.',
            'featured_image' => 'uploads/banners/hero_banner_main.jpg',
            'author' => 'Dr. KAMS Ayurvedic Board',
            'created_at' => date('Y-m-d')
        ],
        [
            'id' => 2,
            'title' => 'The Endocannabinoid System & Chronic Inflammation in Classical Ayurveda',
            'slug' => 'endocannabinoid-system-ayurveda',
            'category' => 'Ayurvedic Pharmacopoeia',
            'excerpt' => 'How classical texts like Charaka Samhita described the therapeutic potential of Vijaya for systemic Vata imbalances.',
            'featured_image' => 'uploads/banners/hero_banner_main.jpg',
            'author' => 'Dr. KAMS Ayurvedic Board',
            'created_at' => date('Y-m-d')
        ]
    ];
    $categories = ['Sleep Science', 'Ayurvedic Pharmacopoeia'];
}

$featuredPost = !empty($posts) ? $posts[0] : null;
$gridPosts = !empty($posts) ? array_slice($posts, 1) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Ayurvedic Science & Cannabis Research Blog',
        'description' => 'Evidence-based insights into Vedic Vijaya pharmacology, cannabinoid medicine, sleep science, and natural pain relief.'
    ]);
    ?>
    <style>
        .blog-hero {
            padding: 60px 0 40px 0;
            text-align: center;
            background: radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.08) 0%, transparent 70%);
        }
        .blog-hero h1 {
            font-family: var(--font-heading);
            font-size: 38px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
        }
        .blog-hero p {
            color: var(--theme-text-secondary);
            font-size: 16px;
            max-width: 620px;
            margin: 0 auto 30px auto;
        }
        .featured-article-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            margin-bottom: 48px;
            box-shadow: var(--shadow-card);
            transition: border-color 0.25s ease;
        }
        .featured-article-card:hover {
            border-color: var(--theme-primary);
        }
        .featured-img-box {
            position: relative;
            min-height: 340px;
            background: #0f131d;
        }
        .featured-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .featured-body {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .blog-tag {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--theme-primary);
            margin-bottom: 12px;
        }
        .featured-title {
            font-family: var(--font-heading);
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.3;
            margin-bottom: 14px;
        }
        .featured-excerpt {
            color: var(--theme-text-secondary);
            font-size: 14.5px;
            line-height: 1.7;
            margin-bottom: 24px;
        }
        .blog-meta-row {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 12.5px;
            color: var(--theme-text-muted);
        }
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 28px;
            padding-bottom: 80px;
        }
        @media (max-width: 600px) {
            .blog-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }
        }
        .blog-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.25s ease, border-color 0.25s ease;
        }
        .blog-card:hover {
            transform: translateY(-5px);
            border-color: var(--theme-border-hover);
        }
        .blog-card-img-box {
            position: relative;
            padding-top: 56.25%; /* 16:9 */
            background: #0e121a;
        }
        .blog-card-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .blog-card-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .blog-card-title {
            font-family: var(--font-heading);
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.4;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .blog-card-excerpt {
            color: var(--theme-text-secondary);
            font-size: 13.5px;
            line-height: 1.6;
            margin-bottom: 18px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        @media (max-width: 900px) {
            .featured-article-card {
                grid-template-columns: 1fr;
            }
            .featured-img-box {
                min-height: 240px;
            }
            .featured-body {
                padding: 24px;
            }
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <section class="blog-hero">
            <div class="theme-container">
                <h1>Ayurvedic Science & Cannabis Research</h1>
                <p>Bridging ancient Vedic healing philosophies with modern phytocannabinoid clinical trials and lifestyle protocols.</p>
                
                <!-- Category Tabs -->
                <div style="display:flex; justify-content:center; gap:10px; flex-wrap:wrap; margin-top:20px;">
                    <a href="blog.php" class="btn-outline" style="padding:6px 16px; font-size:12px; <?= empty($categoryFilter) ? 'border-color:var(--theme-primary); color:var(--theme-primary);' : '' ?>">All Topics</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="blog.php?category=<?= urlencode($cat) ?>" class="btn-outline" style="padding:6px 16px; font-size:12px; <?= $categoryFilter === $cat ? 'border-color:var(--theme-primary); color:var(--theme-primary);' : '' ?>">
                            <?= htmlspecialchars($cat) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section>
            <div class="theme-container">
                <!-- Featured Article -->
                <?php if ($featuredPost): ?>
                    <article class="featured-article-card">
                        <div class="featured-img-box">
                            <img src="<?= htmlspecialchars($featuredPost['featured_image'] ?: 'uploads/banners/hero_banner_main.jpg') ?>" alt="<?= htmlspecialchars($featuredPost['title']) ?>" class="featured-img">
                        </div>
                        <div class="featured-body">
                            <span class="blog-tag">⭐ Featured Publication • <?= htmlspecialchars($featuredPost['category']) ?></span>
                            <h2 class="featured-title">
                                <a href="blog_post.php?slug=<?= urlencode($featuredPost['slug']) ?>">
                                    <?= htmlspecialchars($featuredPost['title']) ?>
                                </a>
                            </h2>
                            <p class="featured-excerpt">
                                <?= htmlspecialchars($featuredPost['excerpt']) ?>
                            </p>
                            <div class="blog-meta-row" style="margin-bottom:20px;">
                                <span>✍️ <?= htmlspecialchars($featuredPost['author'] ?? 'Editorial Board') ?></span>
                                <span>📅 <?= date('M d, Y', strtotime($featuredPost['created_at'])) ?></span>
                            </div>
                            <div>
                                <a href="blog_post.php?slug=<?= urlencode($featuredPost['slug']) ?>" class="btn-primary" style="padding:10px 20px; font-size:13px;">
                                    Read Full Research Paper →
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endif; ?>

                <!-- Grid Articles -->
                <?php if (!empty($gridPosts)): ?>
                    <div class="blog-grid">
                        <?php foreach ($gridPosts as $post): ?>
                            <article class="blog-card">
                                <a href="blog_post.php?slug=<?= urlencode($post['slug']) ?>" class="blog-card-img-box">
                                    <img src="<?= htmlspecialchars($post['featured_image'] ?: 'uploads/banners/hero_banner_main.jpg') ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="blog-card-img" loading="lazy">
                                </a>
                                <div class="blog-card-body">
                                    <div class="blog-tag"><?= htmlspecialchars($post['category']) ?></div>
                                    <h3 class="blog-card-title">
                                        <a href="blog_post.php?slug=<?= urlencode($post['slug']) ?>">
                                            <?= htmlspecialchars($post['title']) ?>
                                        </a>
                                    </h3>
                                    <p class="blog-card-excerpt">
                                        <?= htmlspecialchars($post['excerpt']) ?>
                                    </p>
                                    <div class="blog-meta-row" style="margin-top:auto;">
                                        <span>📅 <?= date('M d, Y', strtotime($post['created_at'])) ?></span>
                                        <a href="blog_post.php?slug=<?= urlencode($post['slug']) ?>" style="margin-left:auto; color:var(--theme-gold); font-weight:700;">Read →</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
