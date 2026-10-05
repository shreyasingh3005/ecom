<?php
// terms.php - Terms & Conditions for KAMS HEMP
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('Terms & Conditions | KAMS HEMP');
$storeName = Settings::get('store_name', 'KAMS HEMP');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Terms of Service & AYUSH Legal Guidelines',
        'description' => 'Terms and conditions governing the purchase, delivery, and medical usage of Ayurvedic Vijaya extracts.'
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
            <h1>Terms of Service & Compliance</h1>
            <div class="policy-meta">Effective Date: October 2026 • Governing Laws of India</div>

            <div class="policy-body">
                <h2>1. Acceptance of Terms</h2>
                <p>By accessing <?= htmlspecialchars($storeName) ?> or purchasing our Ayurvedic Vijaya preparations, you signify agreement to these Terms of Service. If you do not accept these terms, you must discontinue using our services immediately.</p>

                <h2>2. Age Requirement (Strict 18+ Rule)</h2>
                <p>All customers must be at least 18 years of age to purchase Ayurvedic cannabis (Vijaya) extracts. We reserve the statutory right to request age and identity verification prior to dispatching any order.</p>

                <h2>3. AYUSH Legal Framework & Medical Nature</h2>
                <p>Our formulations are manufactured in compliance with the Ministry of AYUSH, Government of India, and under the Drugs and Cosmetics Act. These formulations are intended for therapeutic balancing of Vata, Pitta, and Kapha and symptomatic wellness. Formulations marked with an Rx tag require consultation with a registered Ayurvedic practitioner (available on our platform).</p>

                <h2>4. Pricing, Taxes & Payment Verification</h2>
                <p>All prices are listed in Indian Rupees (₹) and include applicable GST. Payments via UPI, debit/credit cards, and Cash on Delivery are verified through automated security gateways. Fraudulent orders or fictitious addresses will be cancelled without liability.</p>

                <h2>5. Limitation of Liability</h2>
                <p><?= htmlspecialchars($storeName) ?> shall not be held liable for misuse, improper storage (exposure to direct heat/sunlight), or non-compliance with the dosage directions indicated on the formulation packaging.</p>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
