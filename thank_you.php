<?php
// thank_you.php - Production Order Confirmation & WhatsApp Sharing
session_start();

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/analytics.php';

$orderNumber = trim($_GET['order'] ?? '');

if (empty($orderNumber)) {
    header("Location: cbd.php");
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found. <a href='cbd.php'>Return to store</a>");
}

// Fetch order items
$itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$order['id']]);
$orderItems = $itemStmt->fetchAll();

$storeName = Settings::get('store_name', 'KAMS HEMP');
$adminWhatsApp = preg_replace('/[^0-9]/', '', Settings::get('admin_whatsapp_number', '919876543210'));
$currencySymbol = Settings::getCurrencySymbol();

$customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
if (empty($customerName)) {
    $customerName = $order['email'] ?? 'Customer';
}

$paymentMethod = $order['payment_method'] ?? 'COD';
$paymentStatus = $order['payment_status'] ?? 'Pending';
$orderStatus = $order['order_status'] ?? 'Pending';

// Format WhatsApp message text
$waItemsList = [];
foreach ($orderItems as $it) {
    $waItemsList[] = "• " . $it['product_name'] . " (x" . $it['quantity'] . ") - ₹" . number_format($it['subtotal'], 2);
}
$waItemsStr = implode("\n", $waItemsList);

$waMessage = "🌿 *Order Confirmation - {$storeName}*\n"
    . "━━━━━━━━━━━━━━━━━━━━\n"
    . "📦 *Order ID:* {$order['order_number']}\n"
    . "👤 *Customer:* {$customerName}\n"
    . "💳 *Payment:* {$paymentMethod} ({$paymentStatus})\n"
    . "🚚 *Status:* {$orderStatus}\n\n"
    . "🛍️ *Items Ordered:*\n{$waItemsStr}\n\n"
    . "💵 *Total Amount:* ₹" . number_format($order['total_amount'], 2) . "\n"
    . "📍 *Delivery To:* {$order['shipping_address']}\n\n"
    . "Track your order: " . getBaseUrl() . "/order-tracking.php?order=" . urlencode($order['order_number']);

$shareWhatsAppUrl = "https://api.whatsapp.com/send?text=" . rawurlencode($waMessage);
$adminWhatsAppUrl = "https://api.whatsapp.com/send?phone={$adminWhatsApp}&text=" . rawurlencode("Hello {$storeName}! I just placed order #{$order['order_number']} for ₹" . number_format($order['total_amount'], 2) . ". Please confirm!");

Analytics::trackPage('thank_you', 'Order Thank You: ' . $order['order_number']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Your Order! | <?= htmlspecialchars($storeName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-base: #06080c;
            --card-bg: rgba(16, 20, 29, 0.85);
            --card-border: rgba(255, 255, 255, 0.08);
            --accent-cyan: #00ffcc;
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
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content" style="padding: 40px 16px 80px 16px; display: flex; justify-content: center;">
        <div class="confirmation-card">
            width: 100%;
            max-width: 680px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            padding: 36px 32px;
            position: relative;
        }

        .confirmation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #25d366, #00ffcc, #38bdf8);
        }

        .success-hero {
            text-align: center;
            margin-bottom: 32px;
        }

        .success-badge-icon {
            width: 72px;
            height: 72px;
            background: rgba(37, 211, 102, 0.12);
            border: 2px solid var(--accent-green);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-green);
            margin-bottom: 16px;
        }

        .success-badge-icon svg {
            width: 36px;
            height: 36px;
            stroke-width: 2.5;
        }

        .title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .order-summary-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .summary-item label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .summary-item .val {
            font-size: 14px;
            font-weight: 600;
            color: #f1f5f9;
        }

        .badge-pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-pill.paid {
            background: rgba(37, 211, 102, 0.15);
            color: #4ade80;
            border: 1px solid rgba(37, 211, 102, 0.3);
        }

        .badge-pill.pending {
            background: rgba(229, 195, 120, 0.15);
            color: #e5c378;
            border: 1px solid rgba(229, 195, 120, 0.3);
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-secondary);
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .items-table td {
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 13px;
        }

        .totals-breakdown {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-top: 8px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .total-row.final {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-cyan);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 12px;
            margin-top: 4px;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-whatsapp {
            background: #25d366;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 14px 20px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s ease;
        }

        .btn-whatsapp:hover {
            background: #20ba59;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(37, 211, 102, 0.3);
        }

        .btn-whatsapp.admin-btn {
            background: rgba(37, 211, 102, 0.12);
            color: #4ade80;
            border: 1px solid rgba(37, 211, 102, 0.3);
        }

        .btn-whatsapp.admin-btn:hover {
            background: rgba(37, 211, 102, 0.22);
        }

        .btn-action-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 8px;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        @media (max-width: 540px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
            .btn-action-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>

    <div class="confirmation-card">
        <div class="success-hero">
            <div class="success-badge-icon">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1 class="title">Thank You for Your Order!</h1>
            <p class="subtitle">Your order has been received and is being prepared with highest quality standards.</p>
        </div>

        <?php if (!empty($_SESSION['new_account_created'])): 
            $newAcc = $_SESSION['new_account_created'];
        ?>
        <div style="background: rgba(37, 99, 235, 0.12); border: 1px solid rgba(59, 130, 246, 0.4); border-radius: 16px; padding: 20px; margin-bottom: 24px; text-align: left;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                <span style="font-size: 22px;">🎉</span>
                <h3 style="font-size: 16px; font-weight: 700; color: #60a5fa; margin: 0;">Account Created & Logged In!</h3>
            </div>
            <p style="font-size: 13px; color: #cbd5e1; margin-bottom: 12px; line-height: 1.5;">
                We've automatically registered your customer account with email <strong><?= htmlspecialchars($newAcc['email']) ?></strong>. You are currently logged in and can track this order or earn referral rewards anytime.
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 13px; background: rgba(0,0,0,0.3); padding: 12px 16px; border-radius: 10px; border: 1px dashed rgba(255,255,255,0.15);">
                <div><span style="color: #94a3b8;">Login Email:</span> <strong><?= htmlspecialchars($newAcc['email']) ?></strong></div>
                <div><span style="color: #94a3b8;">Temporary Password:</span> <code style="color: #00ffcc; background: rgba(0,255,204,0.1); padding: 2px 6px; border-radius: 4px; font-weight: bold;"><?= htmlspecialchars($newAcc['temp_password']) ?></code></div>
                <?php if (!empty($newAcc['referral_code'])): ?>
                    <div><span style="color: #94a3b8;">Your Referral Code:</span> <code style="color: #e5c378; background: rgba(229,195,120,0.1); padding: 2px 6px; border-radius: 4px; font-weight: bold;"><?= htmlspecialchars($newAcc['referral_code']) ?></code></div>
                <?php endif; ?>
            </div>
            <div style="margin-top: 14px;">
                <a href="profile.php" style="display: inline-block; background: #2563eb; color: #fff; font-weight: 600; font-size: 12px; padding: 8px 16px; border-radius: 8px; text-decoration: none;">View My Profile & Orders →</a>
            </div>
        </div>
        <?php 
            unset($_SESSION['new_account_created']);
        endif; ?>

        <div class="order-summary-box">
            <div class="summary-grid">
                <div class="summary-item">
                    <label>Order Number</label>
                    <div class="val" style="font-family: monospace; color: var(--accent-cyan);">
                        <?= htmlspecialchars($order['order_number']) ?>
                    </div>
                </div>
                <div class="summary-item">
                    <label>Customer Name</label>
                    <div class="val"><?= htmlspecialchars($customerName) ?></div>
                </div>
                <div class="summary-item">
                    <label>Payment Method</label>
                    <div class="val">
                        <?= htmlspecialchars($paymentMethod) ?> 
                        <span class="badge-pill <?= strtolower($paymentStatus) === 'paid' ? 'paid' : 'pending' ?>">
                            <?= htmlspecialchars($paymentStatus) ?>
                        </span>
                    </div>
                </div>
                <div class="summary-item">
                    <label>Order Status</label>
                    <div class="val">
                        <span class="badge-pill <?= in_array(strtolower($orderStatus), ['confirmed', 'delivered']) ? 'paid' : 'pending' ?>">
                            <?= htmlspecialchars($orderStatus) ?>
                        </span>
                    </div>
                </div>
                <div class="summary-item" style="grid-column: 1 / -1;">
                    <label>Shipping Address</label>
                    <div class="val" style="font-weight: normal; color: #cbd5e1;">
                        <?= htmlspecialchars($order['shipping_address']) ?>
                    </div>
                </div>
            </div>

            <!-- Ordered Items List -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td>
                                <strong style="color: #f8fafc;"><?= htmlspecialchars($item['product_name']) ?></strong>
                            </td>
                            <td style="text-align: center; color: var(--text-secondary);">
                                <?= (int)$item['quantity'] ?>
                            </td>
                            <td style="text-align: right; font-weight: 600; color: #f8fafc;">
                                <?= $currencySymbol ?><?= number_format($item['subtotal'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Financial Breakdown -->
            <div class="totals-breakdown">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span><?= $currencySymbol ?><?= number_format($order['subtotal'], 2) ?></span>
                </div>
                <?php if ((float)$order['discount_amount'] > 0): ?>
                    <div class="total-row" style="color: #4ade80;">
                        <span>Referral Discount (<?= htmlspecialchars($order['referral_code'] ?? '') ?>)</span>
                        <span>-<?= $currencySymbol ?><?= number_format($order['discount_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((float)$order['wallet_deduction'] > 0): ?>
                    <div class="total-row" style="color: #c084fc;">
                        <span>Wallet Credits Redeemed</span>
                        <span>-<?= $currencySymbol ?><?= number_format($order['wallet_deduction'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-row">
                    <span>Shipping</span>
                    <span><?= (float)$order['shipping_fee'] > 0 ? $currencySymbol . number_format($order['shipping_fee'], 2) : 'FREE' ?></span>
                </div>
                <div class="total-row">
                    <span>Applicable Tax (GST)</span>
                    <span><?= $currencySymbol ?><?= number_format($order['tax_amount'], 2) ?></span>
                </div>
                <div class="total-row final">
                    <span>Total Amount</span>
                    <span><?= $currencySymbol ?><?= number_format($order['total_amount'], 2) ?></span>
                </div>
            </div>
        </div>

        <!-- WhatsApp Sharing & Actions -->
        <div class="btn-group">
            <a href="<?= htmlspecialchars($shareWhatsAppUrl) ?>" target="_blank" class="btn-whatsapp">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-5.705 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                </svg>
                Share Order on WhatsApp
            </a>

            <a href="<?= htmlspecialchars($adminWhatsAppUrl) ?>" target="_blank" class="btn-whatsapp admin-btn">
                <span>💬</span> Connect with Store on WhatsApp (<?= htmlspecialchars(Settings::get('admin_whatsapp_number', '+91 98765 43210')) ?>)
            </a>

            <div class="btn-action-row">
                <a href="order-tracking.php?order=<?= urlencode($order['order_number']) ?>" class="btn-secondary">Track Delivery</a>
                <a href="cbd.php" class="btn-secondary">Continue Shopping</a>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
