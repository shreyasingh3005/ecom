<?php
// refund-policy.php - Returns & Refund Policy for KAMS HEMP
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('Refund Policy | KAMS HEMP');
$storeName = Settings::get('store_name', 'KAMS HEMP');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Returns & Refund Policy',
        'description' => 'Guidelines regarding returns, damaged parcel replacements, and refunds for KAMS HEMP.'
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
            <h1>Returns & Refund Policy</h1>
            <div class="policy-meta">Ayurvedic Medicinal Formulations • Quality Assurance Guarantee</div>

            <div class="policy-body">
                <h2>1. Healthcare Product Nature & Non-Returnable Items</h2>
                <p>Due to health, safety, and drug contamination regulations governing AYUSH-licensed Ayurvedic formulations and medical cannabis extracts, opened, unsealed, or consumed bottles cannot be returned for restocking.</p>

                <h2>2. Damaged or Incorrect Dispatches (100% Replacement Guarantee)</h2>
                <p>If your package arrives in a damaged condition, broken glass seals, or if an incorrect item was dispatched:</p>
                <ul>
                    <li>Notify our customer team within <strong>48 hours</strong> of receipt by emailing <a href="mailto:<?= htmlspecialchars(Settings::get('support_email', 'support@kamshemp.com')) ?>" style="color:var(--theme-primary);"><?= htmlspecialchars(Settings::get('support_email', 'support@kamshemp.com')) ?></a> or messaging our WhatsApp Support desk.</li>
                    <li>Please provide clear photographs/video of the transit outer box and the damaged formulation.</li>
                    <li>We will immediately arrange a priority express replacement at zero cost or issue a complete refund.</li>
                </ul>

                <h2>3. Refund Processing Timelines</h2>
                <p>Approved refunds are processed to your original payment method (Bank Account, UPI, or Credit Card) within 3 to 5 business days. For COD orders, refunds are disbursed directly via instant UPI payout.</p>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
