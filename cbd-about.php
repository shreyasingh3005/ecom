<?php
// cbd-about.php - About Us / Brand Heritage for KAMS HEMP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('About Our Heritage | KAMS HEMP');
$storeName = Settings::get('store_name', 'KAMS HEMP');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Our Vedic Heritage, Himalayan Sourcing & AYUSH Science',
        'description' => 'Discover how KAMS HEMP recreates 5,000-year-old Vedic cannabis (Vijaya) pharmacopoeia with modern laboratory extraction and standardized potency.'
    ]);
    ?>
    <style>
        .about-hero {
            padding: 70px 0 50px 0;
            text-align: center;
            background: radial-gradient(circle at 50% 0%, rgba(229, 195, 120, 0.08) 0%, transparent 70%);
        }
        .about-hero h1 {
            font-family: var(--font-heading);
            font-size: 40px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 14px;
        }
        .about-hero p {
            color: var(--theme-text-secondary);
            font-size: 16.5px;
            max-width: 680px;
            margin: 0 auto;
            line-height: 1.7;
        }
        .about-story-section {
            padding: 40px 0 60px 0;
        }
        .story-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: center;
            margin-bottom: 60px;
        }
        .story-content h2 {
            font-family: var(--font-heading);
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 16px;
        }
        .story-content p {
            color: #cbd5e1;
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 18px;
        }
        .story-img-box {
            position: relative;
            border-radius: var(--radius-xl);
            overflow: hidden;
            border: 1px solid var(--theme-border);
            box-shadow: var(--shadow-card);
            min-height: 360px;
            background: #11141e;
        }
        .story-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .values-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 36px;
        }
        .value-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 32px 24px;
            transition: transform 0.25s ease, border-color 0.25s ease;
        }
        .value-card:hover {
            transform: translateY(-5px);
            border-color: var(--theme-primary);
        }
        .value-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-md);
            background: rgba(0, 255, 204, 0.1);
            border: 1px solid var(--theme-primary);
            color: var(--theme-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
        }
        .value-card h3 {
            font-family: var(--font-heading);
            font-size: 18px;
            color: #ffffff;
            margin-bottom: 10px;
        }
        .value-card p {
            color: var(--theme-text-secondary);
            font-size: 13.5px;
            line-height: 1.6;
        }
        @media (max-width: 900px) {
            .story-grid {
                grid-template-columns: 1fr;
                gap: 32px;
            }
            .values-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <!-- Hero Section -->
        <section class="about-hero">
            <div class="theme-container">
                <span style="font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--theme-gold);">Ancient Heritage • Modern Pharmacology</span>
                <h1>Reviving Sacred Plant Science</h1>
                <p>Rooted in the ancient Atharva Veda, Vijaya has guarded holistic Indian health for millennia. KAMS HEMP unites this botanical wisdom with precision NABL lab testing and standardized extraction.</p>
            </div>
        </section>

        <!-- Story Section -->
        <section class="about-story-section">
            <div class="theme-container">
                <div class="story-grid">
                    <div class="story-content">
                        <h2>From the High Valleys of the Himalayas</h2>
                        <p>Our botanical story begins in the pristine alpine microclimates of Uttarakhand and Himachal Pradesh. Here, indigenous hemp cultivars thrive under pure glacial meltwater, intense ultraviolet sunlight, and nutrient-dense mountain soils.</p>
                        <p>Unlike mass-market synthetic isolates, we harvest only whole-plant Vijaya leaves at optimal cannabinoid maturity. This preserves the complete spectrum of botanical compounds: CBD, CBG, CBN, and therapeutic aromatic terpenes that produce the clinical <em>Entourage Effect</em>.</p>
                        <div style="display:flex; gap:16px; margin-top:24px;">
                            <a href="cbd-products.php" class="btn-primary">View Formulations</a>
                            <a href="b2b.php" class="btn-outline">Wholesale Partnerships</a>
                        </div>
                    </div>
                    <div class="story-img-box">
                        <img src="uploads/banners/hero_banner_main.jpg" alt="Himalayan Hemp Cultivation" class="story-img">
                    </div>
                </div>

                <!-- 3 Pillars of Craft -->
                <div style="text-align:center; margin-top:60px;">
                    <h2 style="font-family:var(--font-heading); font-size:28px; color:#fff;">The Pillars of Our Standard</h2>
                    <p style="color:var(--theme-text-secondary); max-width:540px; margin:8px auto 0 auto;">How we ensure every single bottle is pure, safe, and effective.</p>

                    <div class="values-grid">
                        <div class="value-card">
                            <div class="value-icon">
                                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                            </div>
                            <h3>100% AYUSH Compliance</h3>
                            <p>Every formulation is manufactured in strict conformity with Ministry of AYUSH regulatory frameworks and licensed by the State Licensing Authority under the Drugs & Cosmetics Act.</p>
                        </div>

                        <div class="value-card">
                            <div class="value-icon">
                                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                            </div>
                            <h3>NABL Batch Verification</h3>
                            <p>We believe in radical transparency. Every single production batch is independently analyzed by NABL-accredited testing facilities for cannabinoid concentration, heavy metals, and purity.</p>
                        </div>

                        <div class="value-card">
                            <div class="value-icon">
                                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                            </div>
                            <h3>Formulated by Vaidyas</h3>
                            <p>Our proprietary ratios are designed alongside licensed Ayurvedic doctors (BAMS) to provide targeted relief for neuromuscular pain, sleep deprivation, and systemic inflammation.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>