<?php
// faq.php - Searchable FAQ Page for KAMS HEMP
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('FAQs | KAMS HEMP');

$storeName = Settings::get('store_name', 'KAMS HEMP');

// Load FAQs from Database
$faqs = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM `faqs` WHERE `status` = 'Published' ORDER BY `sort_order` ASC, `id` ASC");
    $faqs = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log("Failed to load FAQs: " . $e->getMessage());
}

if (empty($faqs)) {
    $faqs = [
        ['category' => 'Legality & Prescription', 'question' => 'Are Vijaya (Cannabis) leaf extracts legal in India?', 'answer' => 'Yes. Under the Drugs and Cosmetics Act, 1940 and Ayurvedic Pharmacopoeia of India, Vijaya (Cannabis sativa) formulations are 100% legal Ayurvedic Proprietary Medicines licensed under the Ministry of AYUSH.'],
        ['category' => 'Legality & Prescription', 'question' => 'Do I need a doctor prescription to buy Vijaya extract?', 'answer' => 'Yes, Vijaya leaf extract is a Schedule E-1 classical Ayurvedic medicine. If you do not have a valid prescription, we provide free online clinical consultation with our certified Ayurvedic practitioners.'],
        ['category' => 'Usage & Dosages', 'question' => 'How should I take Vijaya drops for restorative sleep?', 'answer' => 'Start with 2-4 drops placed sublingually (under the tongue) 30-45 minutes before bedtime. Hold for 60 seconds before swallowing for optimal cannabinoid absorption.'],
        ['category' => 'Orders & Shipping', 'question' => 'How discreet is the packaging and delivery?', 'answer' => 'All orders are shipped in unmarked, tamper-evident, smell-proof corrugated packaging without any product labels visible on the exterior carton. Express delivery takes 2-4 business days.']
    ];
}

// Group by category
$categories = [];
foreach ($faqs as $f) {
    $cat = $f['category'] ?: 'General';
    if (!isset($categories[$cat])) {
        $categories[$cat] = [];
    }
    $categories[$cat][] = $f;
}

// Generate FAQPage JSON-LD schema
$faqEntities = [];
foreach ($faqs as $f) {
    $faqEntities[] = [
        "@type" => "Question",
        "name" => $f['question'],
        "acceptedAnswer" => [
            "@type" => "Answer",
            "text" => strip_tags($f['answer'])
        ]
    ];
}
$faqSchema = [
    "@type" => "FAQPage",
    "mainEntity" => $faqEntities
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Frequently Asked Questions & Ayurvedic Help',
        'description' => 'Find answers about Ayurvedic Vijaya leaf extract legality, dosage guidelines, order dispatch timelines, UPI payments, and doctor consultations.',
        'schema' => $faqSchema
    ]);
    ?>
    <style>
        .faq-hero {
            padding: 60px 0 40px 0;
            text-align: center;
            background: radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.08) 0%, transparent 70%);
        }
        .faq-hero h1 {
            font-family: var(--font-heading);
            font-size: 38px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
        }
        .faq-hero p {
            color: var(--theme-text-secondary);
            font-size: 16px;
            max-width: 600px;
            margin: 0 auto 28px auto;
        }
        .faq-search-wrapper {
            max-width: 580px;
            margin: 0 auto;
            position: relative;
        }
        .faq-search-input {
            width: 100%;
            height: 52px;
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-full);
            padding: 0 54px 0 24px;
            color: #ffffff;
            font-size: 15px;
            outline: none;
            box-shadow: var(--shadow-card);
            transition: all 0.25s ease;
        }
        .faq-search-input:focus {
            border-color: var(--theme-primary);
            box-shadow: 0 0 20px var(--theme-primary-glow);
        }
        .faq-category-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin: 36px 0 44px 0;
        }
        .faq-cat-pill {
            padding: 8px 18px;
            border-radius: var(--radius-full);
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            color: var(--theme-text-secondary);
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .faq-cat-pill:hover, .faq-cat-pill.active {
            background: var(--theme-primary);
            color: #0b0d14;
            border-color: var(--theme-primary);
        }
        .faq-group-title {
            font-family: var(--font-heading);
            font-size: 20px;
            font-weight: 700;
            color: var(--theme-gold);
            margin: 32px 0 16px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .faq-item {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            overflow: hidden;
            transition: border-color 0.2s ease;
        }
        .faq-item:hover {
            border-color: rgba(255, 255, 255, 0.16);
        }
        .faq-trigger {
            width: 100%;
            padding: 18px 24px;
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 15.5px;
            font-weight: 700;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            cursor: pointer;
        }
        .faq-chevron {
            transition: transform 0.25s ease;
            color: var(--theme-primary);
            flex-shrink: 0;
        }
        .faq-item.open .faq-chevron {
            transform: rotate(180deg);
        }
        .faq-content {
            display: none;
            padding: 0 24px 20px 24px;
            color: var(--theme-text-secondary);
            font-size: 14.5px;
            line-height: 1.7;
        }
        .faq-item.open .faq-content {
            display: block;
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <!-- Hero Section -->
        <section class="faq-hero">
            <div class="theme-container">
                <h1>Frequently Asked Questions</h1>
                <p>Learn about our ancient Ayurvedic formulations, doctor consultation process, Pan-India delivery, and payment options.</p>

                <div class="faq-search-wrapper">
                    <input type="text" id="faqSearchInput" class="faq-search-input" placeholder="Type a keyword (e.g. Legal, Dosage, Shipping, UPI)...">
                </div>
            </div>
        </section>

        <!-- Category Pills & Questions -->
        <section style="padding-bottom: 80px;">
            <div class="theme-container" style="max-width: 900px;">
                <div class="faq-category-nav">
                    <button class="faq-cat-pill active" onclick="filterFaqCategory('All', this)">All Questions</button>
                    <?php foreach (array_keys($categories) as $catName): ?>
                        <button class="faq-cat-pill" onclick="filterFaqCategory('<?= htmlspecialchars($catName) ?>', this)"><?= htmlspecialchars($catName) ?></button>
                    <?php endforeach; ?>
                </div>

                <div id="faqAccordionContainer">
                    <?php foreach ($categories as $catName => $items): ?>
                        <div class="faq-group-block" data-category="<?= htmlspecialchars($catName) ?>">
                            <h3 class="faq-group-title">
                                <span>•</span> <?= htmlspecialchars($catName) ?>
                            </h3>
                            <?php foreach ($items as $f): ?>
                                <div class="faq-item" data-category="<?= htmlspecialchars($catName) ?>">
                                    <button class="faq-trigger" onclick="toggleFaq(this)">
                                        <span><?= htmlspecialchars($f['question']) ?></span>
                                        <svg class="faq-chevron" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">
                                            <polyline points="6 9 12 15 18 9"></polyline>
                                        </svg>
                                    </button>
                                    <div class="faq-content">
                                        <?= nl2br(htmlspecialchars($f['answer'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Still have questions CTA card -->
                <div style="background:var(--theme-surface-card); border:1px solid var(--theme-border); border-radius:var(--radius-lg); padding:32px; text-align:center; margin-top:48px;">
                    <h3 style="font-family:var(--font-heading); font-size:20px; color:#fff; margin-bottom:8px;">Still have questions?</h3>
                    <p style="color:var(--theme-text-secondary); margin-bottom:20px;">Our Ayurvedic wellness consultants are ready to assist you with dosage and product recommendations.</p>
                    <div style="display:flex; justify-content:center; gap:14px; flex-wrap:wrap;">
                        <a href="cbd-contact.php" class="btn-primary">Write to Us</a>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', Settings::get('whatsapp_number', '+919876543210')) ?>" target="_blank" class="btn-outline" style="border-color:#25d366; color:#25d366;">WhatsApp Support Desk</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
        function toggleFaq(btn) {
            const item = btn.closest('.faq-item');
            const isOpen = item.classList.contains('open');
            // Close other items in the same block if desired
            item.classList.toggle('open');
        }

        function filterFaqCategory(category, btn) {
            document.querySelectorAll('.faq-cat-pill').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');

            const blocks = document.querySelectorAll('.faq-group-block');
            blocks.forEach(block => {
                if (category === 'All' || block.getAttribute('data-category') === category) {
                    block.style.display = 'block';
                } else {
                    block.style.display = 'none';
                }
            });
        }

        // Live FAQ search
        document.getElementById('faqSearchInput').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase().trim();
            const items = document.querySelectorAll('.faq-item');
            const blocks = document.querySelectorAll('.faq-group-block');

            if (!term) {
                blocks.forEach(b => b.style.display = 'block');
                items.forEach(i => i.style.display = 'block');
                return;
            }

            blocks.forEach(b => b.style.display = 'block');
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(term)) {
                    item.style.display = 'block';
                    item.classList.add('open');
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
