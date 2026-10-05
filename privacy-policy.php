<?php
// privacy-policy.php - Privacy Policy for KAMS HEMP
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('Privacy Policy | KAMS HEMP');
$storeName = Settings::get('store_name', 'KAMS HEMP');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Privacy Policy & Patient Data Security',
        'description' => 'Learn how KAMS HEMP safeguards your personal data, medical records, and digital payment transactions.'
    ]);
    ?>
    <style>
        .policy-container {
            max-width: 860px;
            margin: 0 auto;
            padding: 60px 20px 80px 20px;
        }
        .policy-container h1 {
            font-family: var(--font-heading);
            font-size: 34px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
        }
        .policy-meta {
            color: var(--theme-gold);
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 36px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--theme-border);
        }
        .policy-body h2 {
            font-family: var(--font-heading);
            font-size: 22px;
            color: #ffffff;
            margin: 32px 0 14px 0;
        }
        .policy-body p {
            color: #cbd5e1;
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 18px;
        }
        .policy-body ul {
            margin: 0 0 20px 24px;
            color: #cbd5e1;
            line-height: 1.8;
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <div class="policy-container">
            <h1>Privacy & Confidentiality Policy</h1>
            <div class="policy-meta">Last Updated: October 2026 • Compliant with Indian Information Technology Act</div>

            <div class="policy-body">
                <h2>1. Commitment to Customer Privacy</h2>
                <p>At <?= htmlspecialchars($storeName) ?>, we are committed to upholding the confidentiality of all patient, practitioner, and customer information. As an authorized provider of Ayurvedic Vijaya and herbal extracts, we recognize that privacy regarding your wellness decisions is paramount.</p>

                <h2>2. Information We Collect</h2>
                <p>When you browse our storefront, consult with our Vaidyas, or place an order, we may collect:</p>
                <ul>
                    <li>Personal Identification Information: Name, delivery address, contact telephone, and email address.</li>
                    <li>Medical Consultation Details: Medical history or Ayurvedic consultation notes provided voluntarily for formulation guidance.</li>
                    <li>Transaction Telemetry: Payment method identifiers (UPI reference or bank transaction ID). We never store raw debit/credit card numbers or banking passwords.</li>
                    <li>Device & Browsing Metadata: IP address, browser type, and operating system collected for fraud protection and security auditing.</li>
                </ul>

                <h2>3. Use of Your Data</h2>
                <p>We process your data strictly to fulfill order deliveries, verify AYUSH prescriptions where legally required, provide tracking status updates via SMS/Email, and administer our referral cashback system.</p>

                <h2>4. Data Protection & Security</h2>
                <p>All data transmitted between your browser and our servers is secured via 256-bit TLS/SSL encryption. We do not sell, rent, or trade your personal information to third-party advertising brokers.</p>

                <h2>5. Contact Data Privacy Officer</h2>
                <p>For questions or requests to delete your profile data, email our compliance desk at <a href="mailto:<?= htmlspecialchars(Settings::get('support_email', 'support@kamshemp.com')) ?>" style="color:var(--theme-primary);"><?= htmlspecialchars(Settings::get('support_email', 'support@kamshemp.com')) ?></a>.</p>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
