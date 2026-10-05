<?php
// blog_post.php - Single Article Detail Page for KAMS HEMP
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

$storeName = Settings::get('store_name', 'KAMS HEMP');
$slug = trim($_GET['slug'] ?? '');
$id = (int)($_GET['id'] ?? 0);

$post = null;
$related = [];

try {
    $db = Database::getInstance();

    if (!empty($slug)) {
        $stmt = $db->prepare("SELECT * FROM `blogs` WHERE `slug` = ? AND `status` = 'Published' LIMIT 1");
        $stmt->execute([$slug]);
        $post = $stmt->fetch();
    } elseif ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM `blogs` WHERE `id` = ? AND `status` = 'Published' LIMIT 1");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
    }

    if ($post) {
        // Increment view count (ignore failures)
        try {
            $db->prepare("UPDATE `blogs` SET `view_count` = `view_count` + 1 WHERE `id` = ?")->execute([$post['id']]);
        } catch (Throwable $e) {}

        // Load related posts
        $relStmt = $db->prepare("SELECT * FROM `blogs` WHERE `status` = 'Published' AND `id` != ? ORDER BY `id` DESC LIMIT 3");
        $relStmt->execute([$post['id']]);
        $related = $relStmt->fetchAll();
    }
} catch (Throwable $e) {
    error_log("Failed to load blog post: " . $e->getMessage());
}

if (!$post) {
    header("Location: blog.php");
    exit;
}

Analytics::trackPage($post['title'] . ' | KAMS HEMP');

// Article Schema
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentUrl = $protocol . "://" . $host . $_SERVER['REQUEST_URI'];
$imgUrl = !empty($post['featured_image']) ? ($protocol . "://" . $host . "/" . $post['featured_image']) : ($protocol . "://" . $host . "/logo.png");

$articleSchema = [
    "@type" => "Article",
    "headline" => $post['title'],
    "image" => [$imgUrl],
    "datePublished" => date('c', strtotime($post['created_at'])),
    "dateModified" => date('c', strtotime($post['updated_at'] ?? $post['created_at'])),
    "author" => [
        "@type" => "Person",
        "name" => $post['author'] ?: 'KAMS Ayurvedic Board'
    ],
    "publisher" => [
        "@type" => "Organization",
        "name" => $storeName,
        "logo" => [
            "@type" => "ImageObject",
            "url" => $protocol . "://" . $host . "/logo.png"
        ]
    ],
    "description" => $post['excerpt']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => $post['seo_title'] ?: $post['title'],
        'description' => $post['seo_description'] ?: $post['excerpt'],
        'image' => $imgUrl,
        'type' => 'article',
        'canonical' => $currentUrl,
        'schema' => $articleSchema
    ]);
    ?>
    <style>
        .article-hero {
            padding: 48px 0 32px 0;
            background: radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.06) 0%, transparent 70%);
        }
        .article-breadcrumbs {
            font-size: 13px;
            color: var(--theme-text-muted);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .article-breadcrumbs a {
            color: var(--theme-text-secondary);
        }
        .article-breadcrumbs a:hover {
            color: var(--theme-primary);
        }
        .article-title {
            font-family: var(--font-heading);
            font-size: 36px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.3;
            margin-bottom: 18px;
            max-width: 900px;
        }
        .article-meta-bar {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 13.5px;
            color: var(--theme-text-secondary);
            border-bottom: 1px solid var(--theme-border);
            padding-bottom: 24px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .article-featured-img {
            width: 100%;
            max-height: 480px;
            object-fit: cover;
            border-radius: var(--radius-xl);
            margin-bottom: 40px;
            border: 1px solid var(--theme-border);
            box-shadow: var(--shadow-card);
        }
        .article-content {
            font-size: 16px;
            line-height: 1.85;
            color: #cbd5e1;
            max-width: 800px;
            margin: 0 auto;
        }
        .article-content h2 {
            font-family: var(--font-heading);
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            margin: 40px 0 16px 0;
        }
        .article-content h3 {
            font-family: var(--font-heading);
            font-size: 20px;
            font-weight: 700;
            color: var(--theme-gold);
            margin: 32px 0 12px 0;
        }
        .article-content p {
            margin-bottom: 22px;
        }
        .article-author-card {
            max-width: 800px;
            margin: 48px auto;
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .author-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(0, 255, 204, 0.1);
            border: 1px solid var(--theme-primary);
            color: var(--theme-primary);
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <article>
            <section class="article-hero">
                <div class="theme-container" style="max-width: 960px;">
                    <div class="article-breadcrumbs">
                        <a href="cbd.php">Home</a> <span>›</span>
                        <a href="blog.php">Research</a> <span>›</span>
                        <span><?= htmlspecialchars($post['category']) ?></span>
                    </div>

                    <h1 class="article-title"><?= htmlspecialchars($post['title']) ?></h1>

                    <div class="article-meta-bar">
                        <span>✍️ <strong><?= htmlspecialchars($post['author'] ?: 'Dr. KAMS Ayurvedic Board') ?></strong></span>
                        <span>📅 Published on <?= date('F d, Y', strtotime($post['created_at'])) ?></span>
                        <span>🏷️ <?= htmlspecialchars($post['category']) ?></span>
                        <span style="margin-left:auto; color:var(--theme-gold);">👁️ <?= number_format((int)$post['view_count']) ?> views</span>
                    </div>

                    <?php if (!empty($post['featured_image'])): ?>
                        <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="article-featured-img">
                    <?php endif; ?>

                    <div class="article-content">
                        <?= $post['content'] ?>
                    </div>

                    <!-- Author Box -->
                    <div class="article-author-card">
                        <div class="author-avatar">🌿</div>
                        <div>
                            <h4 style="color:#fff; font-size:16px; margin-bottom:4px;"><?= htmlspecialchars($post['author'] ?: 'KAMS Ayurvedic Advisory') ?></h4>
                            <p style="color:var(--theme-text-secondary); font-size:13px; line-height:1.5;">Our medical board specializes in classical Ayurvedic pharmacopoeia, non-synthetic plant adaptogens, and clinical Vijaya formulations licensed under AYUSH.</p>
                        </div>
                    </div>
                </div>
            </section>
        </article>

        <!-- Related Articles -->
        <?php if (!empty($related)): ?>
            <section style="padding: 40px 0 80px 0; border-top: 1px solid var(--theme-border);">
                <div class="theme-container" style="max-width: 960px;">
                    <h3 style="font-family:var(--font-heading); font-size:22px; color:#fff; margin-bottom:24px;">Recommended Reading</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:20px;">
                        <?php foreach ($related as $r): ?>
                            <a href="blog_post.php?slug=<?= urlencode($r['slug']) ?>" style="background:var(--theme-surface-card); border:1px solid var(--theme-border); border-radius:var(--radius-md); padding:18px; display:block; transition:transform 0.2s ease;">
                                <div style="font-size:11px; color:var(--theme-primary); font-weight:700; text-transform:uppercase; margin-bottom:6px;"><?= htmlspecialchars($r['category']) ?></div>
                                <h4 style="color:#fff; font-size:15px; font-weight:700; line-height:1.4; margin-bottom:8px;"><?= htmlspecialchars($r['title']) ?></h4>
                                <div style="font-size:12px; color:var(--theme-text-muted);">Read article →</div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
