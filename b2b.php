<?php
// b2b.php - Premium B2B Wholesale & Distribution Portal
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/b2b_service.php';

Analytics::trackPage('b2b', 'B2B Wholesale Portal');

$allSettings = Settings::getAll();
$storeName = Settings::get('store_name', 'KAMS HEMP');
$supportEmail = Settings::get('b2b_contact_email', Settings::get('support_email', 'b2b@kamshemp.com'));
$supportPhone = Settings::get('b2b_contact_phone', Settings::get('support_phone', '+91 98765 43210'));
$b2bWhatsApp = preg_replace('/[^0-9]/', '', Settings::get('b2b_whatsapp_number', Settings::get('admin_whatsapp_number', '919876543210')));
$currencySymbol = Settings::getCurrencySymbol();
$isLoggedIn = Auth::isCustomerLoggedIn();
$currentUser = Auth::getUser();

// Catalog Information
$catalog = B2BService::getCatalogInfo();

// Form Submission Handling
$formSuccess = false;
$successMessage = '';
$formError = '';
$submittedInquiryId = 0;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'b2b_inquiry') {
    if (!verify_csrf()) {
        $formError = "Security token expired or invalid. Please refresh the page and try again.";
    } else {
        $data = [
            'company_name' => trim($_POST['company_name'] ?? ''),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'gst_number' => trim($_POST['gst_number'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'business_type' => trim($_POST['business_type'] ?? ''),
            'estimated_monthly_volume' => trim($_POST['estimated_monthly_volume'] ?? ''),
            'message' => trim($_POST['message'] ?? '')
        ];

        $file = $_FILES['attachment'] ?? null;
        $result = B2BService::createInquiry($data, $file);

        if ($result['success']) {
            $formSuccess = true;
            $submittedInquiryId = $result['inquiry_id'];
            $successMessage = $result['message'];
        } else {
            $formError = $result['error'] ?? 'Submission failed. Please check your details.';
        }
    }
}

// SEO
$metaTitle = "B2B Wholesale & Bulk Distribution | " . htmlspecialchars($storeName);
$metaDesc = "Partner directly with India's premier AYUSH-certified Vijaya & CBD manufacturer. Bulk wholesale pricing, high retail margins (40-60%), lab-certified batches, and Pan-India logistics.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($metaTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/responsive.css">

    <style>
        :root {
            --bg-base: #06080c;
            --card-bg: rgba(16, 20, 29, 0.85);
            --card-border: rgba(255, 255, 255, 0.08);
            --accent-cyan: #00ffcc;
            --accent-gold: #e5c378;
            --accent-green: #25d366;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(37, 211, 102, 0.04) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(0, 255, 204, 0.04) 0%, transparent 40%);
            color: var(--text-primary);
            font-family: var(--font-main);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* 1. Clean Background */
        .fullscreen-bg, .bg-overlay {
            display: none !important;
        }

        /* Hero Section */
        .b2b-hero {
            padding: 50px 20px 50px;
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 999px;
            background: rgba(0, 255, 204, 0.08);
            border: 1px solid rgba(0, 255, 204, 0.3);
            color: var(--accent-cyan);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 24px;
        }

        .b2b-hero h1 {
            font-family: var(--font-display);
            font-size: clamp(32px, 5vw, 56px);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #ffffff 30%, #e5c378 70%, #00ffcc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .b2b-hero p {
            color: var(--text-secondary);
            font-size: clamp(15px, 2vw, 18px);
            max-width: 780px;
            margin: 0 auto 36px;
            line-height: 1.6;
        }

        .hero-action-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 50px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00ffcc, #00b386);
            color: #06080c;
            font-weight: 700;
            padding: 14px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 15px;
            letter-spacing: 0.5px;
            box-shadow: 0 0 25px rgba(0, 255, 204, 0.4);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 35px rgba(0, 255, 204, 0.6);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-weight: 600;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 15px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Stat Counter Grid */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            max-width: 1100px;
            margin: 0 auto 60px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            padding: 24px;
            text-align: center;
            backdrop-filter: blur(16px);
            transition: border-color 0.3s;
        }

        .stat-card:hover {
            border-color: rgba(0, 255, 204, 0.4);
        }

        .stat-number {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 800;
            color: var(--accent-cyan);
            margin-bottom: 6px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        /* Section Wrappers */
        .section-wrapper {
            max-width: 1180px;
            margin: 0 auto 70px;
            padding: 0 20px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-subtitle {
            color: var(--accent-gold);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
            display: block;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: clamp(24px, 3.5vw, 36px);
            font-weight: 700;
            color: #ffffff;
        }

        /* Catalog Download Card */
        .catalog-card {
            background: linear-gradient(135deg, rgba(16, 24, 38, 0.95), rgba(12, 16, 26, 0.95));
            border: 1px solid rgba(0, 255, 204, 0.25);
            border-radius: 24px;
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
            margin-bottom: 60px;
        }

        .catalog-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #00ffcc, #e5c378, #25d366);
        }

        .catalog-info {
            flex: 1;
        }

        .catalog-tag {
            background: rgba(229, 195, 120, 0.15);
            color: var(--accent-gold);
            border: 1px solid rgba(229, 195, 120, 0.3);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-block;
            margin-bottom: 12px;
        }

        .catalog-title {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
        }

        .catalog-meta {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 16px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .catalog-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Tiers Grid */
        .tiers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 60px;
        }

        .tier-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: relative;
        }

        .tier-card.featured {
            border-color: rgba(0, 255, 204, 0.4);
            box-shadow: 0 15px 35px rgba(0, 255, 204, 0.1);
        }

        .tier-card:hover {
            transform: translateY(-5px);
            border-color: rgba(229, 195, 120, 0.5);
        }

        .tier-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--accent-cyan);
            color: #000;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 3px 14px;
            border-radius: 999px;
        }

        .tier-name {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 6px;
        }

        .tier-moq {
            font-size: 14px;
            color: var(--accent-cyan);
            font-weight: 600;
            margin-bottom: 16px;
        }

        .tier-margin {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            margin-bottom: 20px;
        }

        .tier-margin-val {
            font-size: 24px;
            font-weight: 800;
            color: #4ade80;
            font-family: var(--font-display);
        }

        .tier-margin-lbl {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tier-features {
            list-style: none;
            margin-bottom: 24px;
            flex-grow: 1;
        }

        .tier-features li {
            font-size: 13px;
            color: #cbd5e1;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tier-features li svg {
            color: var(--accent-cyan);
            flex-shrink: 0;
        }

        /* Requirements Cards */
        .req-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 50px;
        }

        .req-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
        }

        .req-card:hover {
            border-color: rgba(229, 195, 120, 0.3);
            background: rgba(255, 255, 255, 0.04);
        }

        .req-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(229, 195, 120, 0.12);
            color: var(--accent-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .req-title {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
        }

        .req-desc {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        /* Application Form Container */
        .form-container {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 40px;
            max-width: 900px;
            margin: 0 auto;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
            position: relative;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label span.req {
            color: #ff4d4d;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            padding: 12px 16px;
            color: #fff;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--accent-cyan);
            box-shadow: 0 0 15px rgba(0, 255, 204, 0.25);
            background: rgba(255, 255, 255, 0.07);
        }

        select.form-control option {
            background: #11141c;
            color: #fff;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .file-upload-box {
            border: 2px dashed rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background: rgba(255, 255, 255, 0.02);
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload-box:hover {
            border-color: var(--accent-cyan);
            background: rgba(0, 255, 204, 0.03);
        }

        /* Success / Alert Boxes */
        .alert-box {
            padding: 16px 20px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .alert-box.success {
            background: rgba(37, 211, 102, 0.12);
            border: 1px solid rgba(37, 211, 102, 0.4);
            color: #4ade80;
        }

        .alert-box.error {
            background: rgba(255, 77, 77, 0.12);
            border: 1px solid rgba(255, 77, 77, 0.4);
            color: #ff4d4d;
        }

        /* FAQ Accordion */
        .faq-accordion {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .faq-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            overflow: hidden;
            transition: border-color 0.3s;
        }

        .faq-item.active {
            border-color: rgba(0, 255, 204, 0.3);
        }

        .faq-question {
            padding: 18px 24px;
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }

        .faq-question svg {
            transition: transform 0.3s;
            color: var(--accent-cyan);
        }

        .faq-item.active .faq-question svg {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 24px 18px;
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
            display: none;
        }

        .faq-item.active .faq-answer {
            display: block;
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .header-banner {
                padding: 0 20px;
            }
            .header-nav {
                display: none;
            }
            .catalog-card {
                flex-direction: column;
                padding: 28px;
                text-align: center;
            }
            .catalog-meta {
                justify-content: center;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-container {
                padding: 24px;
            }
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- Main Page Content -->
    <main class="site-content">
        <!-- Hero Section -->
        <section class="b2b-hero">
            <div class="badge-pill">
                <span>🌱</span> AYUSH Certified Manufacturer & Bulk Supplier
            </div>
            <h1>Partner With India's Leading Vijaya & Cannabinoid Manufacturer</h1>
            <p>
                Empowering Ayurvedic pharmacies, wellness clinics, practitioners, and regional retail distributors with high-potency, full-spectrum Vijaya formulations. Benefit from industry-leading wholesale margins, rigorous batch COAs, and rapid Pan-India logistics.
            </p>
            <div class="hero-action-row">
                <a href="#applyForm" class="btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
                    Apply for Wholesale Account
                </a>
                <a href="#catalogSection" class="btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Download Product Catalog (PDF)
                </a>
                <a href="https://api.whatsapp.com/send?phone=<?= htmlspecialchars($b2bWhatsApp) ?>&text=<?= urlencode('Hello ' . $storeName . '! I am interested in B2B Wholesale / Distribution partnership. Please share wholesale details.') ?>" target="_blank" class="btn-secondary" style="border-color: rgba(37, 211, 102, 0.4); color: #4ade80;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-5.705 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    WhatsApp B2B Desk
                </a>
            </div>

            <!-- Key Metric Stats -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-number">40% - 60%</div>
                    <div class="stat-label">Retail Gross Margins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">₹25,000</div>
                    <div class="stat-label">Low Starter MOV / MOQ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">100%</div>
                    <div class="stat-label">AYUSH & COA Verified</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">24 - 48 Hrs</div>
                    <div class="stat-label">Pan-India Dispatch</div>
                </div>
            </div>
        </section>

        <!-- Catalog Download Section -->
        <section class="section-wrapper" id="catalogSection">
            <div class="catalog-card">
                <div class="catalog-info">
                    <span class="catalog-tag">Official Wholesale Asset</span>
                    <h2 class="catalog-title"><?= htmlspecialchars($catalog['title']) ?></h2>
                    <p style="color: #cbd5e1; font-size: 14px; margin-bottom: 14px; line-height: 1.5;">
                        Complete wholesale master catalog including SKU lists, full potency spectrums, packaging dimensions, HSN codes, GST taxation tiers, and tiered volume discounts.
                    </p>
                    <div class="catalog-meta">
                        <span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> PDF Document</span>
                        <span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Size: <?= htmlspecialchars($catalog['size']) ?></span>
                        <span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> Updated: <?= htmlspecialchars($catalog['updated']) ?></span>
                    </div>
                </div>
                <div>
                    <?php if (!empty($catalog['file_path']) && $catalog['exists']): ?>
                        <a href="<?= htmlspecialchars($catalog['file_path']) ?>" download class="btn-primary" style="white-space: nowrap;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            Download Catalog (PDF)
                        </a>
                    <?php else: ?>
                        <a href="#applyForm" class="btn-primary" style="white-space: nowrap;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            Request Digital Catalog
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Partnership Tiers -->
        <section class="section-wrapper">
            <div class="section-header">
                <span class="section-subtitle">Flexible Wholesale Structures</span>
                <h2 class="section-title">Choose Your Partnership Tier</h2>
            </div>

            <div class="tiers-grid">
                <!-- Tier 1 -->
                <div class="tier-card">
                    <h3 class="tier-name">Retail Stockist</h3>
                    <div class="tier-moq">Min. Order: ₹25,000 / 30 Units</div>
                    <div class="tier-margin">
                        <div class="tier-margin-val">40%</div>
                        <div class="tier-margin-lbl">Retail Margin</div>
                    </div>
                    <ul class="tier-features">
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Complete Retail SKU access</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Countertop Brand Standee & Displays</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Patient & customer brochures</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Pan-India Courier Delivery</li>
                    </ul>
                    <a href="#applyForm" class="btn-secondary" style="justify-content: center;">Apply as Retailer</a>
                </div>

                <!-- Tier 2 (Featured) -->
                <div class="tier-card featured">
                    <div class="tier-badge">Most Popular</div>
                    <h3 class="tier-name">Regional Distributor</h3>
                    <div class="tier-moq">Min. Order: ₹1,00,000 / Tiered</div>
                    <div class="tier-margin">
                        <div class="tier-margin-val">50%</div>
                        <div class="tier-margin-lbl">Distributor Margin</div>
                    </div>
                    <ul class="tier-features">
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Regional Territory Priority</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Tester & Doctor Sampling Kits</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Dedicated B2B Account Manager</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Free Freight Delivery across India</li>
                    </ul>
                    <a href="#applyForm" class="btn-primary" style="justify-content: center;">Apply as Distributor</a>
                </div>

                <!-- Tier 3 -->
                <div class="tier-card">
                    <h3 class="tier-name">Institutional / Doctor</h3>
                    <div class="tier-moq">Clinics, Hospitals & Doctors</div>
                    <div class="tier-margin">
                        <div class="tier-margin-val">55%</div>
                        <div class="tier-margin-lbl">Clinical Margin</div>
                    </div>
                    <ul class="tier-features">
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Medical Vijaya Extracts & Drops</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Doctor Prescription Integration</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Clinical Dossiers & Research Papers</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Priority Clinical Helpline</li>
                    </ul>
                    <a href="#applyForm" class="btn-secondary" style="justify-content: center;">Apply as Clinic</a>
                </div>

                <!-- Tier 4 -->
                <div class="tier-card">
                    <h3 class="tier-name">White-Label / OEM</h3>
                    <div class="tier-moq">Custom Brand Manufacturing</div>
                    <div class="tier-margin">
                        <div class="tier-margin-val">Custom</div>
                        <div class="tier-margin-lbl">Contract Pricing</div>
                    </div>
                    <ul class="tier-features">
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Custom Potency & Terpene Blends</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Bottle, Dropper & Box Customization</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Complete AYUSH License Support</li>
                        <li><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> NABL Accredited Lab Testing</li>
                    </ul>
                    <a href="#applyForm" class="btn-secondary" style="justify-content: center;">Inquire White-Label</a>
                </div>
            </div>
        </section>

        <!-- Document Requirements -->
        <section class="section-wrapper">
            <div class="section-header">
                <span class="section-subtitle">Verification Standards</span>
                <h2 class="section-title">Documents Required to Become a B2B Partner</h2>
            </div>

            <div class="req-grid">
                <div class="req-card">
                    <div class="req-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    </div>
                    <h3 class="req-title">1. GST Registration</h3>
                    <p class="req-desc">Active 15-digit GSTIN Certificate for legitimate tax invoicing and claiming Input Tax Credit (ITC).</p>
                </div>

                <div class="req-card">
                    <div class="req-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                    <h3 class="req-title">2. Business PAN Card</h3>
                    <p class="req-desc">PAN card copy of the Proprietor, Partnership, LLP, or Private Limited entity for tax compliance.</p>
                </div>

                <div class="req-card">
                    <div class="req-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    </div>
                    <h3 class="req-title">3. Drug / AYUSH / Retail License</h3>
                    <p class="req-desc">For pharmacies and clinics handling prescription Vijaya extracts. Food & seed retailers may submit FSSAI/Trade License.</p>
                </div>

                <div class="req-card">
                    <div class="req-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    </div>
                    <h3 class="req-title">4. Address Proof / Shop Act</h3>
                    <p class="req-desc">Electricity bill, Shop & Establishment certificate, or rent agreement verifying physical commercial premise.</p>
                </div>
            </div>
        </section>

        <!-- B2B Wholesale Application Form -->
        <section class="section-wrapper" id="applyForm">
            <div class="section-header">
                <span class="section-subtitle">Instant Onboarding</span>
                <h2 class="section-title">Submit Wholesale Inquiry / Application</h2>
            </div>

            <div class="form-container">
                <?php if ($formSuccess): ?>
                    <div class="alert-box success">
                        <h3 style="margin-bottom: 6px; font-size: 18px;">🎉 Application Submitted Successfully!</h3>
                        <p><?= htmlspecialchars($successMessage) ?></p>
                        <p style="margin-top: 8px;"><strong>Application Reference ID:</strong> #B2B-<?= $submittedInquiryId ?></p>
                        <div style="margin-top: 16px;">
                            <a href="https://api.whatsapp.com/send?phone=<?= htmlspecialchars($b2bWhatsApp) ?>&text=<?= urlencode("Hello {$storeName}! I just submitted B2B Wholesale Application #B2B-{$submittedInquiryId}. Please expedite my verification.") ?>" target="_blank" class="btn-primary" style="background:#25d366; color:#fff;">
                                Expedite on WhatsApp (Connect Now) →
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($formError)): ?>
                    <div class="alert-box error">
                        <strong>⚠️ Submission Error:</strong> <?= htmlspecialchars($formError) ?>
                    </div>
                <?php endif; ?>

                <form action="b2b.php#applyForm" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="b2b_inquiry">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="company_name">Company / Business Name <span class="req">*</span></label>
                            <input type="text" id="company_name" name="company_name" class="form-control" placeholder="e.g. Apex Health & Wellness LLP" required value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="contact_person">Contact Person Name <span class="req">*</span></label>
                            <input type="text" id="contact_person" name="contact_person" class="form-control" placeholder="e.g. Vikram Singhal" required value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Business Email Address <span class="req">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="e.g. wholesale@apexhealth.in" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone / WhatsApp Number <span class="req">*</span></label>
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="e.g. 9876543210" required pattern="^[6-9]\d{9}$" title="Enter a valid 10-digit Indian mobile number" maxlength="10" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="gst_number">GST Number (GSTIN)</label>
                            <input type="text" id="gst_number" name="gst_number" class="form-control" placeholder="e.g. 07AAAAA0000A1Z5" maxlength="15" value="<?= htmlspecialchars($_POST['gst_number'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="business_type">Business Classification <span class="req">*</span></label>
                            <select id="business_type" name="business_type" class="form-control" required>
                                <option value="" disabled selected>-- Select Type --</option>
                                <option value="Retail Pharmacy">Retail Pharmacy / Chemist</option>
                                <option value="Ayurvedic Clinic / Doctor">Ayurvedic Clinic / Medical Practitioner</option>
                                <option value="Distributor / Stockist">Wholesale Stockist / Regional Distributor</option>
                                <option value="E-Commerce Marketplace">Online Store / E-commerce Brand</option>
                                <option value="Gym & Fitness Center">Gym, Fitness & Athletic Center</option>
                                <option value="White-Label / OEM">Private Label / White-Label Brand</option>
                                <option value="Other">Other Business Entity</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="city">City <span class="req">*</span></label>
                            <input type="text" id="city" name="city" class="form-control" placeholder="e.g. Mumbai" required value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="state">State / Union Territory <span class="req">*</span></label>
                            <select id="state" name="state" class="form-control" required>
                                <option value="" disabled selected>Select State</option>
                                <option value="Andhra Pradesh">Andhra Pradesh</option>
                                <option value="Assam">Assam</option>
                                <option value="Bihar">Bihar</option>
                                <option value="Chandigarh">Chandigarh</option>
                                <option value="Delhi">Delhi</option>
                                <option value="Goa">Goa</option>
                                <option value="Gujarat">Gujarat</option>
                                <option value="Haryana">Haryana</option>
                                <option value="Himachal Pradesh">Himachal Pradesh</option>
                                <option value="Karnataka">Karnataka</option>
                                <option value="Kerala">Kerala</option>
                                <option value="Madhya Pradesh">Madhya Pradesh</option>
                                <option value="Maharashtra">Maharashtra</option>
                                <option value="Punjab">Punjab</option>
                                <option value="Rajasthan">Rajasthan</option>
                                <option value="Tamil Nadu">Tamil Nadu</option>
                                <option value="Telangana">Telangana</option>
                                <option value="Uttar Pradesh">Uttar Pradesh</option>
                                <option value="Uttarakhand">Uttarakhand</option>
                                <option value="West Bengal">West Bengal</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label for="estimated_monthly_volume">Estimated Monthly Purchasing Budget</label>
                            <select id="estimated_monthly_volume" name="estimated_monthly_volume" class="form-control">
                                <option value="₹25,000 - ₹50,000">₹25,000 - ₹50,000 (Starter Retail)</option>
                                <option value="₹50,000 - ₹1,00,000">₹50,000 - ₹1,00,000 (Growth Partner)</option>
                                <option value="₹1,00,000 - ₹5,00,000">₹1,00,000 - ₹5,00,000 (Regional Distributor)</option>
                                <option value="₹5,00,000+">₹5,00,000+ (Super Stockist / OEM)</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label for="attachment">Upload Visiting Card / GST Certificate / License (Optional, PDF / Image)</label>
                            <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <small style="color: var(--text-secondary); font-size: 11px;">Max file size: 10MB. Accepted formats: PDF, JPG, PNG, WEBP.</small>
                        </div>

                        <div class="form-group full">
                            <label for="message">Specific Products of Interest or Questions</label>
                            <textarea id="message" name="message" class="form-control" placeholder="Specify which extracts, tinctures, seeds, or bulk packaging you are interested in ordering..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 10px;">
                        <button type="submit" class="btn-primary" style="width: 100%; max-width: 400px; justify-content: center; font-size: 16px;">
                            Submit Wholesale Application →
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="section-wrapper">
            <div class="section-header">
                <span class="section-subtitle">Clarifications</span>
                <h2 class="section-title">Frequently Asked B2B Questions</h2>
            </div>

            <div class="faq-accordion">
                <div class="faq-item active">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>What is the minimum order quantity (MOQ) and value (MOV)?</span>
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="faq-answer">
                        Our starter wholesale threshold is just ₹25,000. You can mix and match various SKUs (e.g., tinctures, oils, gummies, hemp seeds) within this minimum order value to test consumer response in your store or clinic.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Are KAMS HEMP Vijaya products legally compliant in India?</span>
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="faq-answer">
                        Yes, 100%. All our medical Vijaya and hemp wellness formulations are manufactured under valid AYUSH manufacturing licenses in compliance with the Drugs and Cosmetics Act. Every batch comes with a Certificate of Analysis (COA) confirming safety and legal cannabinoid ratios.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Do you provide samples or doctor tester kits?</span>
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="faq-answer">
                        Yes! Upon initial document and business verification, registered distributors, clinics, and pharmacies can receive curated sample packs and tester kits along with doctor educational pamphlets and counter displays.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>How are B2B shipments handled and insured?</span>
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="faq-answer">
                        All wholesale orders are packaged in heavy-duty tamper-proof transit boxing and dispatched via surface/air cargo logistics (e.g., BlueDart, Delhivery Freight, TCI Express) with end-to-end transit insurance and real-time tracking.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Can you do Private Label / White-Label manufacturing for my brand?</span>
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="faq-answer">
                        Yes. We provide complete contract manufacturing (OEM) and white-labeling solutions including custom formulation blending, bottle packaging, label printing, and regulatory AYUSH batch filing for established brands and wellness clinic chains.
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script src="assets/js/responsive.js"></script>
    <script>
        function toggleFaq(el) {
            const item = el.closest('.faq-item');
            item.classList.toggle('active');
        }
    </script>
</body>
</html>
