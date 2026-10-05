<?php
// cart.php - Shopping Cart for KAMS HEMP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/referral.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/seo.php';

Analytics::trackPage('Shopping Cart | KAMS HEMP');

$storeName = Settings::get('store_name', 'KAMS HEMP');
$supportEmail = Settings::get('support_email', 'support@kamshemp.com');
$taxRatePercentage = (float)Settings::get('tax_rate', 18);
$taxRateMultiplier = $taxRatePercentage / 100;
$freeShippingThreshold = (float)Settings::get('free_shipping_threshold', 3999);
$standardDeliveryFee = (float)Settings::get('standard_delivery_fee', 250);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$currentUser = Auth::isCustomerLoggedIn() ? Auth::getUser(true) : null;

// Handle GET Actions (Add, Remove, Quantity adjustments)
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // Add item to cart
    if ($action === 'add') {
        $db = Database::getInstance();
        $targetProduct = null;
        $qtyToAdd = isset($_GET['qty']) ? max(1, (int)$_GET['qty']) : 1;

        if (!empty($_GET['id'])) {
            $targetProduct = Inventory::getProduct((int)$_GET['id']);
        } elseif (!empty($_GET['item'])) {
            $name = trim(urldecode($_GET['item']));
            $stmt = $db->prepare("SELECT id FROM `products` WHERE `name` = ? LIMIT 1");
            $stmt->execute([$name]);
            $row = $stmt->fetch();
            if ($row) {
                $targetProduct = Inventory::getProduct($row['id']);
            }
        }

        if ($targetProduct && $targetProduct['status'] !== 'Inactive') {
            $pId = (int)$targetProduct['id'];
            $stockCheck = Inventory::checkStock($pId, $qtyToAdd);

            if ($stockCheck['available']) {
                $found = false;
                foreach ($_SESSION['cart'] as &$cartItem) {
                    $cId = (int)($cartItem['id'] ?? $cartItem['product_id'] ?? 0);
                    if ($cId === $pId || $cartItem['name'] === $targetProduct['name']) {
                        if ($targetProduct['stock'] >= ($cartItem['qty'] + $qtyToAdd)) {
                            $cartItem['qty'] += $qtyToAdd;
                        } else {
                            $cartItem['qty'] = $targetProduct['stock'];
                            $_SESSION['cart_error'] = "Adjusted quantity to max available stock ({$targetProduct['stock']}).";
                        }
                        $found = true;
                        break;
                    }
                }
                unset($cartItem);

                if (!$found) {
                    $_SESSION['cart'][] = [
                        'id' => $pId,
                        'product_id' => $pId,
                        'name' => $targetProduct['name'],
                        'price' => (float)$targetProduct['price'],
                        'img' => $targetProduct['primary_image'],
                        'image' => $targetProduct['primary_image'],
                        'qty' => $qtyToAdd
                    ];
                }

                Analytics::trackProduct($pId, 'add_to_cart');
                $_SESSION['cart_success'] = "Added {$targetProduct['name']} to cart.";

                if (isset($_GET['redirect']) && $_GET['redirect'] === 'checkout') {
                    header("Location: checkout.php");
                    exit;
                }
            } else {
                $_SESSION['cart_error'] = "Sorry, {$targetProduct['name']} is out of stock.";
            }
        } else {
            $_SESSION['cart_error'] = "Product could not be found.";
        }
        header("Location: cart.php");
        exit;
    }

    // Remove item from cart
    if ($action === 'remove' && isset($_GET['index'])) {
        $index = (int)$_GET['index'];
        if (isset($_SESSION['cart'][$index])) {
            array_splice($_SESSION['cart'], $index, 1);
        }
        header("Location: cart.php");
        exit;
    }

    // Step quantity
    if ($action === 'step_qty' && isset($_GET['index']) && isset($_GET['delta'])) {
        $index = (int)$_GET['index'];
        $delta = (int)$_GET['delta'];
        if (isset($_SESSION['cart'][$index])) {
            $newQty = $_SESSION['cart'][$index]['qty'] + $delta;
            if ($newQty <= 0) {
                array_splice($_SESSION['cart'], $index, 1);
            } else {
                $pId = (int)($_SESSION['cart'][$index]['product_id'] ?? $_SESSION['cart'][$index]['id'] ?? 0);
                $p = Inventory::getProduct($pId);
                if ($p && $p['stock'] < $newQty) {
                    $_SESSION['cart_error'] = "Only {$p['stock']} available in stock.";
                } else {
                    $_SESSION['cart'][$index]['qty'] = $newQty;
                }
            }
        }
        header("Location: cart.php");
        exit;
    }

    // Remove Referral Code
    if ($action === 'remove_referral') {
        unset($_SESSION['referral_code']);
        $_SESSION['referral_msg'] = "Referral code removed.";
        header("Location: cart.php");
        exit;
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'apply_referral') {
        $code = strtoupper(trim($_POST['referral_code'] ?? ''));
        $uId = $currentUser ? $currentUser['id'] : null;
        $uEmail = $currentUser ? $currentUser['email'] : null;

        $validation = ReferralSystem::validateCode($code, $uId, $uEmail);
        if ($validation['valid']) {
            $_SESSION['referral_code'] = $code;
            $_SESSION['referral_msg'] = $validation['message'];
        } else {
            unset($_SESSION['referral_code']);
            $_SESSION['referral_error'] = $validation['message'];
        }
        header("Location: cart.php");
        exit;
    }
}

// Calculate Totals
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += ((float)$item['price'] * (int)$item['qty']);
}

// Re-verify referral discount
$discountAmount = 0;
if (!empty($_SESSION['referral_code']) && $subtotal > 0) {
    $uId = $currentUser ? $currentUser['id'] : null;
    $uEmail = $currentUser ? $currentUser['email'] : null;
    $check = ReferralSystem::validateCode($_SESSION['referral_code'], $uId, $uEmail);
    if ($check['valid']) {
        $discountAmount = ReferralSystem::calculateDiscount($subtotal, $check['discount_percent']);
    } else {
        unset($_SESSION['referral_code']);
        $_SESSION['referral_error'] = $check['message'];
    }
}

$postDiscountSubtotal = max(0, $subtotal - $discountAmount);
$shipping = ($postDiscountSubtotal >= $freeShippingThreshold || $postDiscountSubtotal == 0) ? 0 : $standardDeliveryFee;
$tax = round($postDiscountSubtotal * $taxRateMultiplier, 2);
$grandTotal = $postDiscountSubtotal + $shipping + $tax;
$remainingForFreeShipping = max(0, $freeShippingThreshold - $postDiscountSubtotal);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    SEO::renderMeta([
        'title' => 'Shopping Cart & Order Summary',
        'description' => 'Review your selected Ayurvedic Vijaya formulations, apply discount coupons or wallet rewards, and proceed to secure checkout.'
    ]);
    ?>
    <style>
        .cart-hero {
            padding: 44px 0 24px 0;
            background: radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.06) 0%, transparent 70%);
        }
        .cart-title {
            font-family: var(--font-heading);
            font-size: 32px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .cart-layout {
            display: grid;
            grid-template-columns: 1.8fr 1fr;
            gap: 36px;
            padding: 30px 0 80px 0;
            align-items: flex-start;
        }
        .cart-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            padding: 28px;
            box-shadow: var(--shadow-card);
        }
        .free-shipping-meter {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 24px;
            font-size: 13.5px;
        }
        .progress-bar-bg {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            margin-top: 8px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: var(--theme-primary);
            border-radius: 4px;
            box-shadow: 0 0 8px var(--theme-primary);
        }
        .cart-item-row {
            display: grid;
            grid-template-columns: 80px 1fr auto auto;
            gap: 20px;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--theme-border);
        }
        .cart-item-row:last-child {
            border-bottom: none;
        }
        .cart-item-img {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-md);
            object-fit: cover;
            background: #0f131d;
            border: 1px solid var(--theme-border);
        }
        .cart-item-name {
            font-family: var(--font-heading);
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }
        .cart-item-price {
            font-size: 14px;
            color: var(--theme-primary);
            font-weight: 600;
        }
        .cart-summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: var(--theme-text-secondary);
            margin-bottom: 14px;
        }
        .cart-total-row {
            display: flex;
            justify-content: space-between;
            font-size: 19px;
            font-weight: 800;
            color: #ffffff;
            border-top: 1px solid var(--theme-border);
            padding-top: 16px;
            margin-top: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 900px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
            .cart-item-row {
                grid-template-columns: 70px 1fr;
                gap: 14px;
            }
            .cart-item-actions-mobile {
                grid-column: 1 / -1;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding-top: 8px;
            }
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <section class="cart-hero">
            <div class="theme-container">
                <h1 class="cart-title">Your Wellness Cart</h1>
                <p style="color:var(--theme-text-secondary); font-size:14.5px;">Standardized Vedic Vijaya & Phytocannabinoid Formulations</p>
            </div>
        </section>

        <section>
            <div class="theme-container">
                <!-- Notifications -->
                <?php if (!empty($_SESSION['cart_error'])): ?>
                    <div style="background:rgba(239,68,68,0.15); border:1px solid #ef4444; color:#f87171; padding:12px 18px; border-radius:10px; margin-bottom:20px; font-size:13.5px; font-weight:600;">
                        <?= htmlspecialchars($_SESSION['cart_error']); unset($_SESSION['cart_error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['cart_success'])): ?>
                    <div style="background:rgba(34,197,94,0.15); border:1px solid #22c55e; color:#4ade80; padding:12px 18px; border-radius:10px; margin-bottom:20px; font-size:13.5px; font-weight:600;">
                        <?= htmlspecialchars($_SESSION['cart_success']); unset($_SESSION['cart_success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['cart'])): ?>
                    <!-- Free Shipping Meter -->
                    <div class="free-shipping-meter">
                        <?php if ($remainingForFreeShipping > 0): ?>
                            <span>🚚 Add <strong>₹<?= number_format($remainingForFreeShipping) ?></strong> more for <strong>FREE Pan-India Express Shipping</strong>!</span>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?= min(100, round(($postDiscountSubtotal / $freeShippingThreshold) * 100)) ?>%;"></div>
                            </div>
                        <?php else: ?>
                            <span style="color:var(--theme-primary); font-weight:700;">🎉 Congratulations! You have unlocked FREE Express Air Shipping.</span>
                        <?php endif; ?>
                    </div>

                    <div class="cart-layout">
                        <!-- Items Table Card -->
                        <div class="cart-card">
                            <h2 style="font-family:var(--font-heading); font-size:20px; color:#fff; margin-bottom:16px;">
                                Items in Cart (<?= array_sum(array_column($_SESSION['cart'], 'qty')) ?>)
                            </h2>

                            <?php foreach ($_SESSION['cart'] as $index => $item): 
                                $lineTotal = (float)$item['price'] * (int)$item['qty'];
                            ?>
                                <div class="cart-item-row">
                                    <img src="<?= htmlspecialchars($item['img'] ?? $item['image'] ?? 'uploads/products/default.webp') ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-img">
                                    
                                    <div>
                                        <div class="cart-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="cart-item-price">₹<?= number_format((float)$item['price']) ?> each</div>
                                    </div>

                                    <!-- Qty Control -->
                                    <div class="pdp-qty-control" style="height:38px;">
                                        <a href="cart.php?action=step_qty&index=<?= $index ?>&delta=-1" class="qty-btn" style="text-decoration:none;">-</a>
                                        <span class="qty-input" style="line-height:38px; display:inline-block;"><?= (int)$item['qty'] ?></span>
                                        <a href="cart.php?action=step_qty&index=<?= $index ?>&delta=1" class="qty-btn" style="text-decoration:none;">+</a>
                                    </div>

                                    <!-- Line Subtotal & Delete -->
                                    <div style="text-align:right;">
                                        <div style="font-weight:800; font-size:16px; color:#fff; margin-bottom:4px;">₹<?= number_format($lineTotal) ?></div>
                                        <a href="cart.php?action=remove&index=<?= $index ?>" style="font-size:12px; color:var(--theme-danger); text-decoration:underline;">Remove</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:24px; padding-top:20px; border-top:1px solid var(--theme-border);">
                                <a href="cbd-products.php" class="btn-outline" style="font-size:13px; padding:10px 18px;">← Continue Shopping</a>
                            </div>
                        </div>

                        <!-- Order Summary Card -->
                        <div class="cart-card">
                            <h2 style="font-family:var(--font-heading); font-size:20px; color:#fff; margin-bottom:20px;">Order Summary</h2>

                            <!-- Referral Discount Form -->
                            <form action="cart.php" method="POST" style="margin-bottom:20px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="apply_referral">
                                <label style="display:block; font-size:12px; font-weight:700; color:var(--theme-gold); margin-bottom:6px; text-transform:uppercase;">Have a Referral / Promo Code?</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="text" name="referral_code" value="<?= htmlspecialchars($_SESSION['referral_code'] ?? '') ?>" placeholder="Enter referral code" class="contact-input-field" style="height:40px; font-size:13px; text-transform:uppercase;" <?= !empty($_SESSION['referral_code']) ? 'readonly' : '' ?>>
                                    <?php if (!empty($_SESSION['referral_code'])): ?>
                                        <a href="cart.php?action=remove_referral" class="btn-outline" style="padding:0 12px; font-size:12px; color:var(--theme-danger); border-color:var(--theme-danger);">Remove</a>
                                    <?php else: ?>
                                        <button type="submit" class="btn-primary" style="padding:0 16px; font-size:12px; height:40px;">Apply</button>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <?php if (!empty($_SESSION['referral_msg'])): ?>
                                <div style="font-size:12px; color:var(--theme-primary); margin-bottom:12px;">✓ <?= htmlspecialchars($_SESSION['referral_msg']); unset($_SESSION['referral_msg']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($_SESSION['referral_error'])): ?>
                                <div style="font-size:12px; color:var(--theme-danger); margin-bottom:12px;">✕ <?= htmlspecialchars($_SESSION['referral_error']); unset($_SESSION['referral_error']); ?></div>
                            <?php endif; ?>

                            <!-- Totals Breakdown -->
                            <div class="cart-summary-row">
                                <span>Subtotal</span>
                                <span>₹<?= number_format($subtotal, 2) ?></span>
                            </div>

                            <?php if ($discountAmount > 0): ?>
                                <div class="cart-summary-row" style="color:var(--theme-primary);">
                                    <span>Referral Discount</span>
                                    <span>-₹<?= number_format($discountAmount, 2) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="cart-summary-row">
                                <span>Estimated Shipping</span>
                                <span><?= $shipping == 0 ? '<strong style="color:var(--theme-primary);">FREE</strong>' : '₹' . number_format($shipping, 2) ?></span>
                            </div>

                            <div class="cart-summary-row">
                                <span>Estimated GST (<?= $taxRatePercentage ?>%)</span>
                                <span>₹<?= number_format($tax, 2) ?></span>
                            </div>

                            <div class="cart-total-row">
                                <span>Grand Total</span>
                                <span style="color:var(--theme-primary);">₹<?= number_format($grandTotal, 2) ?></span>
                            </div>

                            <a href="checkout.php" class="btn-primary" style="width:100%; height:50px; font-size:15px; text-transform:uppercase; letter-spacing:0.5px;">
                                Proceed to Checkout →
                            </a>

                            <div style="font-size:11.5px; color:var(--theme-text-muted); text-align:center; margin-top:14px;">
                                🔒 Bank-grade 256-Bit SSL Checkout • Doctor Guided
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Empty Cart State -->
                    <div style="text-align:center; padding:80px 20px; background:var(--theme-surface-card); border:1px solid var(--theme-border); border-radius:var(--radius-xl); margin:30px 0;">
                        <div style="font-size:48px; margin-bottom:14px;">🌿</div>
                        <h2 style="font-family:var(--font-heading); font-size:24px; color:#fff; margin-bottom:8px;">Your Shopping Cart is Empty</h2>
                        <p style="color:var(--theme-text-secondary); max-width:440px; margin:0 auto 28px auto; font-size:14.5px;">Explore our collection of authentic Himalayan Vijaya cannabis extracts, restorative drops, and pain relief balms.</p>
                        <a href="cbd-products.php" class="btn-primary">Browse Formulations</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>