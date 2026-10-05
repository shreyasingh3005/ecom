<?php
// shipping-policy.php - Shipping & Delivery Policy for KAMS HEMP
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('Shipping Policy | KAMS HEMP');
$storeName = Settings::get('store_name', 'KAMS HEMP');
$freeThreshold = Settings::get('free_shipping_threshold', '3999');
$shippingFee = Settings::get('standard_delivery_fee', '250');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Shipping & Pan-India Delivery Policy',
        'description' => 'Fast, discreet, cold-chain compliant shipping details for KAMS HEMP extracts across India.'
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
            <h1>Shipping & Delivery Policy</h1>
            <div class="policy-meta">Pan-India Express Logistics • 100% Discreet Packaging</div>

            <div class="policy-body">
                <h2>1. Pan-India Delivery Coverage</h2>
                <p><?= htmlspecialchars($storeName) ?> ships to over 24,000+ pin codes across India using trusted express air courier partners (BlueDart, Delhivery, DTDC, XpressBees). Every package is packed in unmarked, discreet protective outer cartons to protect your privacy.</p>

                <h2>2. Shipping Charges & Free Threshold</h2>
                <ul>
                    <li><strong>Free Express Shipping:</strong> Orders valued at ₹<?= number_format((float)$freeThreshold) ?> or above receive complimentary express air delivery across India.</li>
                    <li><strong>Standard Shipping Fee:</strong> Orders below ₹<?= number_format((float)$freeThreshold) ?> carry a flat nominal fee of ₹<?= number_format((float)$shippingFee) ?>.</li>
                </ul>

                <h2>3. Estimated Dispatch & Delivery Timelines</h2>
                <ul>
                    <li><strong>Order Dispatch:</strong> Orders placed before 1:00 PM IST on business days are dispatched within 24 hours after verification.</li>
                    <li><strong>Metro Cities:</strong> 24 to 48 hours delivery time.</li>
                    <li><strong>Rest of India (Tier 2/3):</strong> 3 to 5 business days delivery time.</li>
                </ul>

                <h2>4. Real-Time Tracking</h2>
                <p>Upon dispatch, an automated notification containing your airway bill (AWB) number and live tracking link will be sent via SMS and email. You can also monitor real-time movement anytime on our <a href="order-tracking.php" style="color:var(--theme-primary);">Track Order</a> page.</p>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
