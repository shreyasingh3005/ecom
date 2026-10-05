<?php
// checkout.php - Dynamic MySQL Powered Secure Checkout
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/referral.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/payment/PaymentService.php';

Analytics::trackPage('checkout', 'Secure Checkout');

// Catch referral code if passed directly in the URL
if (isset($_GET['ref'])) {
    $_SESSION['referral_code'] = strtoupper(trim($_GET['ref']));
    $_SESSION['referral_msg'] = "Referral code applied successfully!";
}

// Handle Wallet Apply/Remove Actions via GET
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'apply_wallet') {
        $_SESSION['apply_wallet'] = true;
        header("Location: checkout.php");
        exit;
    } elseif ($_GET['action'] === 'remove_wallet') {
        unset($_SESSION['apply_wallet']);
        header("Location: checkout.php");
        exit;
    }
}

// Load Global Settings from database
$allSettings = Settings::getAll();
$storeName = Settings::get('store_name', 'KAMS HEMP');
$supportEmail = Settings::get('support_email', 'support@kamshemp.com');
$supportPhone = Settings::get('support_phone', '+91 98765 43210');
$taxRatePercentage = (float)Settings::get('tax_rate', 18); 
$taxRateMultiplier = $taxRatePercentage / 100;
$referralDiscountPercent = (float)Settings::get('referral_discount_percent', 15);
$freeShippingThreshold = (float)Settings::get('free_shipping_threshold', 2000);
$standardShippingFee = (float)Settings::get('standard_delivery_fee', 150);
$enableCashfree = (Settings::get('enable_cashfree', '0') === '1' || Settings::get('payment_gateway_provider') === 'cashfree');
$currencySymbol = Settings::getCurrencySymbol();
$settings = $allSettings; // For template compatibility

// Load Current User Data for Pre-filling and Wallet
$isLoggedIn = Auth::isCustomerLoggedIn();
$currentUser = null;
$savedAddresses = [];
$walletBalance = 0;

if ($isLoggedIn) {
    $currentUserId = Auth::getUserId();
    $currentUser = Auth::getUser();
    $db = Database::getInstance();
    
    // Fetch latest user details directly from DB
    $stmtU = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmtU->execute([$currentUserId]);
    $dbUser = $stmtU->fetch();
    if ($dbUser) {
        $currentUser = array_merge($currentUser ?? [], $dbUser);
        $_SESSION['user'] = $currentUser;
        $walletBalance = (float)($currentUser['wallet_balance'] ?? 0);
    }
    
    // Load saved addresses from DB
    $stmtAddr = $db->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $stmtAddr->execute([$currentUserId]);
    $savedAddresses = $stmtAddr->fetchAll();
}

$defFname = $currentUser['first_name'] ?? '';
$defLname = $currentUser['last_name'] ?? '';
$defEmail = $currentUser['email'] ?? '';
$defPhone = $currentUser['phone'] ?? '';

// Calculate Cart Total and reconcile with latest DB prices
$cartTotal = 0;
$cartItems = $_SESSION['cart'] ?? [];
if (!empty($cartItems)) {
    foreach ($cartItems as &$item) {
        $prodId = (int)($item['id'] ?? $item['product_id'] ?? 0);
        if ($prodId <= 0 && !empty($item['name'])) {
            try {
                $stmtTmp = Database::getInstance()->prepare("SELECT id FROM products WHERE name = ? OR name LIKE ? LIMIT 1");
                $stmtTmp->execute([$item['name'], '%' . $item['name'] . '%']);
                $fProd = $stmtTmp->fetch();
                if ($fProd) {
                    $prodId = (int)$fProd['id'];
                }
            } catch (Exception $e) {}
        }
        $dbProd = $prodId > 0 ? Inventory::getProduct($prodId) : null;
        if ($dbProd) {
            $item['id'] = (int)$dbProd['id'];
            $item['product_id'] = (int)$dbProd['id'];
            $item['price'] = (float)$dbProd['price'];
            $item['name'] = $dbProd['name'];
            $img = $dbProd['primary_image'] ?? ($item['image'] ?? ($item['img'] ?? ''));
            $item['image'] = $img;
            $item['img'] = $img;
        } else {
            $item['id'] = $prodId;
            $item['product_id'] = $prodId;
            $img = $item['image'] ?? ($item['img'] ?? '');
            $item['image'] = $img;
            $item['img'] = $img;
        }
        $price = floatval($item['price'] ?? 0);
        $qty = intval($item['qty'] ?? 1);
        $cartTotal += ($price * $qty);
    }
    unset($item);
    $_SESSION['cart'] = $cartItems;
}

// --- REFERRAL VALIDATION & DISCOUNT LOGIC ---
$discountAmount = 0;
$referralError = '';

if (isset($_SESSION['referral_code']) && $cartTotal > 0) {
    $appliedRefCode = $_SESSION['referral_code'];
    $val = ReferralSystem::validateCode($appliedRefCode, $isLoggedIn ? ($currentUser['id'] ?? null) : null);
    
    if ($val['valid']) {
        $discountAmount = ReferralSystem::calculateDiscount($appliedRefCode, $cartTotal);
    } else {
        $referralError = $val['message'];
        unset($_SESSION['referral_code']);
        unset($_SESSION['referral_msg']);
        $_SESSION['referral_error'] = $referralError;
    }
}

$postDiscountSubtotal = max(0, $cartTotal - $discountAmount);

// --- WALLET CASHBACK LOGIC ---
$walletApplied = isset($_SESSION['apply_wallet']) && $_SESSION['apply_wallet'] === true && $isLoggedIn;
$walletDeduction = 0;

if ($walletApplied && $walletBalance > 0) {
    $walletDeduction = ReferralSystem::calculateAllowedWalletUsage($walletBalance, $postDiscountSubtotal);
}

$postWalletSubtotal = max(0, $postDiscountSubtotal - $walletDeduction);

// Dynamic shipping & tax
$shippingFee = ($postWalletSubtotal >= $freeShippingThreshold || $cartTotal == 0) ? 0 : $standardShippingFee;
$taxAmount = $postWalletSubtotal * $taxRateMultiplier;
$finalTotal = $postWalletSubtotal + $shippingFee + $taxAmount;

// --- PROCESS ORDER PHP LOGIC ---
$orderSuccess = false;
$orderId = '';
$customerName = '';
$orderError = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (!verify_csrf()) {
        $orderError = "Security token validation failed. Please refresh and try again.";
    } elseif (empty($cartItems)) {
        $orderError = "Your cart is empty. Please add products before checking out.";
    } else {
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip = trim($_POST['zip'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? 'Cash on Delivery');

        // Customer information
        $customerData = [
            'first_name' => $fname,
            'last_name' => $lname,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'notes' => 'Web Checkout Order'
        ];

        $appliedRefCode = $_SESSION['referral_code'] ?? null;
        $isWalletApplied = isset($_SESSION['apply_wallet']) && $_SESSION['apply_wallet'] === true && $isLoggedIn;

        // Auto-register new guest user in database & establish session
        $accountInfo = PaymentService::ensureCustomerAccount($customerData, $appliedRefCode, $isLoggedIn ? ($currentUser['id'] ?? null) : null);
        $buyerUserId = $accountInfo['user_id'];
        if (!empty($accountInfo['temp_password'])) {
            $customerData['temp_password'] = $accountInfo['temp_password'];
        }
        if (!empty($accountInfo['referral_code'])) {
            $customerData['referral_code'] = $accountInfo['referral_code'];
        }

        try {
            // Determine payment method flow
            if (stripos($paymentMethod, 'cashfree') !== false || stripos($paymentMethod, 'card') !== false) {
                // CASHFREE FLOW: Create Cashfree PG session and redirect
                $cfSession = PaymentService::createCashfreeSession($customerData, $cartItems, $appliedRefCode, $isWalletApplied, $buyerUserId);

                // Clear session cart and referral
                unset($_SESSION['cart']);
                unset($_SESSION['referral_code']);
                unset($_SESSION['referral_msg']);
                unset($_SESSION['apply_wallet']);

                if (!empty($cfSession['payment_url'])) {
                    header("Location: " . $cfSession['payment_url']);
                    exit;
                } else {
                    header("Location: api/cashfree_return.php?order_id=" . urlencode($cfSession['order_number']));
                    exit;
                }
            } elseif (stripos($paymentMethod, 'upi') !== false || stripos($paymentMethod, 'scan') !== false) {
                // UPI FLOW: Create pending payment session in payments table and redirect to upi_payment.php
                $upiSession = PaymentService::createUpiSession($customerData, $cartItems, $appliedRefCode, $isWalletApplied, $buyerUserId);

                // Clear session cart and referral
                unset($_SESSION['cart']);
                unset($_SESSION['referral_code']);
                unset($_SESSION['referral_msg']);
                unset($_SESSION['apply_wallet']);

                header("Location: upi_payment.php?txn=" . urlencode($upiSession['transaction_id']));
                exit;
            } else {
                // COD FLOW: Create immediate order and redirect to thank_you.php
                $codOrder = PaymentService::createCodOrder($customerData, $cartItems, $appliedRefCode, $isWalletApplied, $buyerUserId);

                // Clear session cart and referral
                unset($_SESSION['cart']);
                unset($_SESSION['referral_code']);
                unset($_SESSION['referral_msg']);
                unset($_SESSION['apply_wallet']);

                header("Location: thank_you.php?order=" . urlencode($codOrder['order_number']));
                exit;
            }
        } catch (Exception $e) {
            $orderError = "Order processing failed: " . $e->getMessage();
        }
    }
}

// SEO Metadata
$metaTitle = $settings['meta_title'] ?? ($storeName . ' | Secure Checkout');
$metaDesc = $settings['meta_description'] ?? 'Complete your secure checkout for premium hemp products.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($metaTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>">
    
    <?php if(!empty($settings['ga_tracking_id'])): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($settings['ga_tracking_id']) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= htmlspecialchars($settings['ga_tracking_id']) ?>');
    </script>
    <?php endif; ?>

    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
        }

        /* 1. Clean Background */
        .fullscreen-bg, .bg-overlay {
            display: none !important;
        }

        /* 3. Seamless Loop Notification Bar */
        .notification-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 35px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 0, 255, 0.3);
            display: flex;
            align-items: center;
            overflow: hidden;
            z-index: 102; 
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .notification-track {
            display: flex;
            width: max-content;
            animation: seamless-marquee 35s linear infinite;
        }

        .notification-track:hover {
            animation-play-state: paused;
        }

        .marquee-content {
            display: flex;
            gap: 50px;
            padding-right: 50px;
        }

        .marquee-content span {
            text-shadow: 0 0 8px rgba(255, 0, 255, 0.6);
            white-space: nowrap; 
        }

        @keyframes seamless-marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); } 
        }

        /* 4. The Header Banner */
        .header-banner {
            position: fixed;
            top: 35px; 
            left: 0;
            width: 100%;
            height: 80px;
            background: rgba(10, 10, 10, 0.4); 
            backdrop-filter: blur(12px); 
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 0 50px;
            box-sizing: border-box;
            z-index: 100;
        }

        .header-nav {
            display: flex;
            gap: 30px;
            height: 100%; 
            align-items: center;
        }

        .header-nav > a, .nav-item-has-mega > a {
            color: #ccc;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .header-nav > a:hover, .nav-item-has-mega > a:hover {
            color: #fff;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
        }

        /* 4.1 Mega Menu Sub-Menu Dropdown */
        .nav-item-has-mega {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .mega-menu {
            position: absolute;
            top: 80px; 
            left: 0;
            width: 50vw; 
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 2px solid rgba(255, 0, 255, 0.6);
            border-radius: 0 0 15px 15px;
            padding: 35px 40px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(15px);
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 20px 40px rgba(0,0,0,0.8);
            cursor: default;
        }

        .mega-menu::before {
            content: '';
            position: absolute;
            top: -20px;
            left: 0;
            width: 100%;
            height: 20px;
            background: transparent;
        }

        .nav-item-has-mega:hover .mega-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .mega-column {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .mega-column h3 {
            color: #fff;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-nav .mega-menu a {
            color: #aaa;
            font-size: 13px;
            font-weight: 500;
            text-transform: capitalize;
            letter-spacing: 0.5px;
            padding: 8px 0;
            transition: all 0.3s ease;
            text-shadow: none;
            display: inline-block;
            width: fit-content;
        }

        .header-nav .mega-menu a:hover {
            color: #fff;
            transform: translateX(8px);
            text-shadow: 0 0 8px rgba(255, 0, 255, 0.4);
        }

        .header-logo {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-img {
            height: 45px; 
            width: auto;
            filter: drop-shadow(0 0 10px rgba(255, 0, 255, 0.6));
        }

        /* 5. Header Icons & Advanced Search Bar */
        .header-icons {
            margin-left: auto; 
            display: flex;
            gap: 25px;
            align-items: center;
        }

        .search-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 25px;
            padding: 10px 15px 10px 38px;
            color: #fff;
            font-size: 13px;
            font-family: inherit;
            width: 220px;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            outline: none;
            backdrop-filter: blur(5px);
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
            opacity: 1; 
        }

        .search-input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 0, 255, 0.5);
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.3);
            width: 280px;
        }

        .search-icon-inside {
            position: absolute;
            left: 12px;
            width: 16px;
            height: 16px;
            fill: none;
            stroke: rgba(255, 255, 255, 0.6);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            pointer-events: none; 
            transition: stroke 0.3s ease;
        }

        .search-input:focus + .search-icon-inside {
            stroke: rgba(255, 0, 255, 0.8);
        }

        /* --- Account Dropdown Styles --- */
        .account-dropdown-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            height: 100%;
        }

        .account-dropdown-wrapper > a {
            color: #ccc;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .account-dropdown-wrapper > a:hover {
            color: #fff;
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.8));
        }

        .account-dropdown-wrapper svg:not(.search-icon-inside) {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .account-dropdown {
            position: absolute;
            top: 45px; 
            right: -10px; 
            width: 220px;
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 2px solid rgba(255, 0, 255, 0.6);
            border-radius: 12px;
            padding: 10px 0;
            display: flex;
            flex-direction: column;
            opacity: 0;
            visibility: hidden;
            transform: translateY(15px);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 15px 35px rgba(0,0,0,0.8);
            z-index: 1000;
        }

        .account-dropdown::before {
            content: '';
            position: absolute;
            top: -20px;
            right: 0;
            width: 100%;
            height: 20px;
            background: transparent;
        }

        .account-dropdown-wrapper:hover .account-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .account-dropdown a {
            color: #ccc;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 12px 20px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: flex-start;
        }

        .account-dropdown a:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
            padding-left: 25px;
            text-shadow: 0 0 8px rgba(255, 0, 255, 0.4);
        }
        
        .account-dropdown a svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: rgba(255, 0, 255, 0.8);
            color: white;
            font-size: 10px;
            font-weight: bold;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 5px rgba(255, 0, 255, 0.5);
        }

        /* 6. Main Content Wrapper */
        .content-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 145px; 
            padding-bottom: 60px;
            box-sizing: border-box;
        }

        /* 7. Checkout Page Specific Styles */
        .checkout-layout {
            width: 90%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1.8fr 1.2fr;
            gap: 40px;
            margin-bottom: 80px;
            text-align: left;
        }

        .checkout-box {
            background: rgba(15, 15, 15, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            padding: 35px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
        }

        .checkout-box h2 {
            color: #fff;
            margin-top: 0;
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkout-box h2 svg {
            width: 24px;
            height: 24px;
            stroke: rgba(255, 0, 255, 0.8);
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            color: #ccc;
            margin-bottom: 8px;
            font-size: 13px;
            letter-spacing: 0.5px;
            font-weight: bold;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: #fff;
            outline: none;
            font-family: inherit;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .form-group input:focus, .form-group select:focus {
            border-color: rgba(255, 0, 255, 0.5);
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        /* Strict Validation Styling */
        .form-group input:invalid:not(:placeholder-shown) {
            border-color: #ff4d4d;
            background: rgba(255, 0, 0, 0.05);
        }
        .form-group input:valid:not(:placeholder-shown), .form-group select.is-valid {
            border-color: #4caf50;
        }
        
        .error-msg {
            color: #ff4d4d;
            font-size: 11px;
            margin-top: 5px;
            display: none;
            font-weight: 600;
        }

        .payment-methods {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .payment-method {
            flex: 1;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.02);
            color: rgba(255,255,255,0.6);
            font-size: 14px;
        }

        .payment-method:hover, .payment-method.active {
            border-color: rgba(255, 0, 255, 0.6);
            color: #fff;
            background: rgba(255, 0, 255, 0.1);
        }

        .btn-checkout {
            width: 100%;
            padding: 18px;
            background: rgba(255, 0, 255, 0.8);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(255, 0, 255, 0.3);
        }

        .btn-checkout:hover {
            background: rgba(255, 0, 255, 1);
            box-shadow: 0 5px 25px rgba(255, 0, 255, 0.5);
            transform: translateY(-2px);
        }
        
        .btn-checkout:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Order Summary Styles */
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .summary-item-details {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .summary-item-img {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            object-fit: cover;
        }

        .summary-item-name {
            font-size: 14px;
            color: #fff;
            line-height: 1.4;
        }

        .summary-item-qty {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }

        .summary-item-price {
            font-size: 15px;
            color: #ccc;
        }

        .summary-subtotal {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: rgba(255,255,255,0.7);
            font-size: 14px;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 20px;
            font-weight: bold;
            color: #e5c378; 
            text-shadow: 0 0 10px rgba(229, 195, 120, 0.3);
        }

        .security-badge {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.3);
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4caf50;
            font-size: 12px;
            font-weight: 600;
        }

        /* Success Modal Styles */
        .success-modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(8px);
            z-index: 3000;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .success-modal-content {
            background: rgba(15, 15, 15, 0.95);
            border: 1px solid rgba(76, 175, 80, 0.4);
            border-top: 3px solid #4caf50;
            border-radius: 20px;
            width: 90%;
            max-width: 450px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0,0,0,0.8), 0 0 20px rgba(76, 175, 80, 0.2);
            transform: scale(0.9);
            transition: transform 0.4s ease;
        }
        .success-modal-overlay.show {
            opacity: 1;
        }
        .success-modal-overlay.show .success-modal-content {
            transform: scale(1);
        }
        .success-icon {
            width: 70px;
            height: 70px;
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 2px solid #4caf50;
        }
        .success-icon svg {
            width: 35px;
            height: 35px;
            stroke-width: 3;
        }
        .success-title {
            color: #fff;
            font-size: 24px;
            margin: 0 0 10px;
            letter-spacing: 1px;
        }
        .success-text {
            color: #ccc;
            font-size: 14px;
            margin: 0 0 20px;
            line-height: 1.5;
        }
        .order-id-box {
            background: rgba(255,255,255,0.05);
            border: 1px dashed rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-family: monospace;
            font-size: 18px;
            color: #e5c378;
            letter-spacing: 2px;
        }
        .redirect-text {
            font-size: 12px;
            color: #888;
        }
        .redirect-text span {
            color: #ff00ff;
            font-weight: bold;
        }

        /* 10. Footer Styles */
        .site-footer {
            width: 100%;
            background: rgba(10, 10, 10, 0.7);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #ccc;
            padding: 60px 0 20px;
            margin-top: auto; 
            position: relative;
            z-index: 10;
        }

        .footer-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
            text-align: left;
        }

        .footer-column h3 {
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 20px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .footer-column p {
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 15px;
            color: rgba(255, 255, 255, 0.6);
            text-shadow: none;
        }

        .footer-logo-text {
            color: #fff;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-decoration: none;
            text-shadow: 0 0 10px rgba(255, 0, 255, 0.6);
            display: inline-block;
            margin-bottom: 15px;
        }

        .footer-column nav {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-column nav a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
            width: fit-content;
        }

        .footer-column nav a:hover {
            color: #fff;
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.8);
            transform: translateX(5px);
        }

        .footer-subscribe {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 5px;
            margin-top: 10px;
        }

        .footer-subscribe input {
            background: transparent;
            border: none;
            color: #fff;
            padding: 8px 15px;
            font-size: 13px;
            outline: none;
            width: 100%;
        }

        .footer-subscribe input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .footer-subscribe button {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 15px;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .footer-subscribe button:hover {
            background: rgba(255, 0, 255, 0.6);
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.4);
        }

        .footer-subscribe button svg {
            width: 14px;
            height: 14px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
        }

        .footer-bottom {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-bottom p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            margin: 0;
            text-shadow: none;
        }

        .social-icons {
            display: flex;
            gap: 15px;
        }

        .social-icons a {
            color: rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            color: #fff;
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.8));
            transform: translateY(-2px);
        }

        .social-icons svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        /* --- Responsive Media Queries --- */

        @media (max-width: 900px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
            .footer-container {
                gap: 20px;
            }
        }

        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        @media (max-width: 480px) {
            .checkout-box {
                padding: 25px 20px;
            }
            .payment-methods {
                flex-direction: column;
            }
            .footer-bottom {
                flex-direction: column;
                justify-content: center;
                text-align: center;
                gap: 15px;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <div class="theme-container" style="padding-top: 30px;">
            <h1 style="margin-bottom: 30px; font-weight: 800; font-family: var(--font-heading); color: #fff;">Complete Your Order</h1>

        <div class="checkout-layout">
            
            <div class="checkout-box">
                <div class="security-badge">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <span>SECURE CHECKOUT - Data integrity enforcement active. Only original and accurate matching information is accepted.</span>
                </div>
                
                <h2>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.242-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Billing & Shipping
                </h2>
                
                <form action="checkout.php" method="POST" id="checkoutForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="place_order">
                    <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="Cash on Delivery">
                    
                    <?php if (!empty($orderError)): ?>
                        <div style="background: rgba(255, 77, 77, 0.15); border: 1px solid #ff4d4d; color: #ff4d4d; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
                            <?= htmlspecialchars($orderError) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($savedAddresses)): ?>
                        <div class="form-group" style="margin-bottom: 25px;">
                            <label for="saved_address">Choose a Saved Address</label>
                            <select id="saved_address" style="background: rgba(255,0,255,0.1); border-color: rgba(255,0,255,0.5);">
                                <option value="">-- Select a saved address or enter new below --</option>
                                <?php foreach($savedAddresses as $index => $addr): 
                                    $streetVal = $addr['street_address'] ?? ($addr['street'] ?? '');
                                    $zipVal = $addr['postal_code'] ?? ($addr['zip'] ?? '');
                                    $titleVal = $addr['address_title'] ?? ($addr['title'] ?? 'Address');
                                ?>
                                    <option value="<?= $index ?>" 
                                            data-street="<?= htmlspecialchars($streetVal) ?>"
                                            data-city="<?= htmlspecialchars($addr['city']) ?>"
                                            data-state="<?= htmlspecialchars($addr['state']) ?>"
                                            data-zip="<?= htmlspecialchars($zipVal) ?>">
                                        <?= htmlspecialchars($titleVal) ?> - <?= htmlspecialchars($streetVal) ?>, <?= htmlspecialchars($addr['city']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="new">+ Enter a New Address</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fname">First Name</label>
                            <input type="text" id="fname" name="fname" placeholder="Abhinav" required pattern="^[A-Za-z\s]{2,50}$" title="Only letters and spaces allowed, minimum 2 characters." value="<?= htmlspecialchars($defFname) ?>">
                        </div>
                        <div class="form-group">
                            <label for="lname">Last Name</label>
                            <input type="text" id="lname" name="lname" placeholder="Singh" required pattern="^[A-Za-z\s]{2,50}$" title="Only letters and spaces allowed." value="<?= htmlspecialchars($defLname) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="email@example.com" required value="<?= htmlspecialchars($defEmail) ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" placeholder="9876543210" required pattern="^[6-9]\d{9}$" title="Enter a valid 10-digit Indian mobile number starting with 6-9." maxlength="10" value="<?= htmlspecialchars($defPhone) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Street Address</label>
                        <input type="text" id="address" name="address" placeholder="House/Flat No., Street, Landmark" required minlength="10">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" placeholder="New Delhi" required pattern="^[A-Za-z\s]{2,50}$">
                        </div>
                        <div class="form-group">
                            <label for="state">State / Union Territory</label>
                            <select id="state" name="state" required>
                                <option value="" disabled selected>Select State</option>
                                <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                                <option value="Andhra Pradesh">Andhra Pradesh</option>
                                <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                <option value="Assam">Assam</option>
                                <option value="Bihar">Bihar</option>
                                <option value="Chandigarh">Chandigarh</option>
                                <option value="Chhattisgarh">Chhattisgarh</option>
                                <option value="Dadra and Nagar Haveli">Dadra and Nagar Haveli</option>
                                <option value="Daman and Diu">Daman and Diu</option>
                                <option value="Delhi">Delhi</option>
                                <option value="Goa">Goa</option>
                                <option value="Gujarat">Gujarat</option>
                                <option value="Haryana">Haryana</option>
                                <option value="Himachal Pradesh">Himachal Pradesh</option>
                                <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                                <option value="Jharkhand">Jharkhand</option>
                                <option value="Karnataka">Karnataka</option>
                                <option value="Kerala">Kerala</option>
                                <option value="Lakshadweep">Lakshadweep</option>
                                <option value="Madhya Pradesh">Madhya Pradesh</option>
                                <option value="Maharashtra">Maharashtra</option>
                                <option value="Manipur">Manipur</option>
                                <option value="Meghalaya">Meghalaya</option>
                                <option value="Mizoram">Mizoram</option>
                                <option value="Nagaland">Nagaland</option>
                                <option value="Odisha">Odisha</option>
                                <option value="Puducherry">Puducherry</option>
                                <option value="Punjab">Punjab</option>
                                <option value="Rajasthan">Rajasthan</option>
                                <option value="Sikkim">Sikkim</option>
                                <option value="Tamil Nadu">Tamil Nadu</option>
                                <option value="Telangana">Telangana</option>
                                <option value="Tripura">Tripura</option>
                                <option value="Uttar Pradesh">Uttar Pradesh</option>
                                <option value="Uttarakhand">Uttarakhand</option>
                                <option value="West Bengal">West Bengal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="zip">PIN Code</label>
                            <input type="text" id="zip" name="zip" placeholder="110075" required pattern="^[1-9][0-9]{5}$" maxlength="6" title="Enter a valid 6-digit Indian PIN code.">
                            <span id="pinError" class="error-msg">PIN Code does not match the selected State.</span>
                        </div>
                    </div>

                    <h2 style="margin-top: 30px;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        Payment Method
                    </h2>
                    
                    <div class="payment-methods">
                        <div class="payment-method active" data-method="Cash on Delivery" onclick="setPaymentMethod('Cash on Delivery', this)">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; font-size: 15px; margin-bottom: 4px;">
                                <span style="font-size: 18px;">💵</span> Cash on Delivery
                            </div>
                            <div style="font-size: 12px; color: #aaa;">Pay with cash upon package delivery</div>
                        </div>

                        <div class="payment-method" data-method="UPI" onclick="setPaymentMethod('UPI', this)">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; font-size: 15px; margin-bottom: 4px;">
                                <span style="font-size: 18px;">⚡</span> UPI / Scan & Pay
                            </div>
                            <div style="font-size: 12px; color: #aaa;">Dynamic QR & Direct UPI Apps (GPay, PhonePe, Paytm)</div>
                        </div>

                        <?php if ($enableCashfree): ?>
                        <div class="payment-method" data-method="Cashfree" onclick="setPaymentMethod('Cashfree', this)">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; font-size: 15px; margin-bottom: 4px;">
                                <span style="font-size: 18px;">💳</span> Cards & NetBanking
                            </div>
                            <div style="font-size: 12px; color: #aaa;">Cashfree Gateway (Cards, NetBanking, UPI)</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-checkout" id="submitBtn">Place Order (Cash on Delivery)</button>
                </form>
            </div>

            <div class="checkout-box">
                <h2>
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    Order Summary
                </h2>

                <div class="summary-items-list">
                    <?php if (!empty($cartItems)): ?>
                        <?php foreach($cartItems as $item): ?>
                            <div class="summary-item">
                                <div class="summary-item-details">
                                    <?php 
                                        // Dynamically check multiple possible keys in your session array for the product image
                                        $itemImg = $item['image'] ?? ($item['img'] ?? ($item['images'][0] ?? '')); 
                                    ?>
                                    <?php if(!empty($itemImg)): ?>
                                        <img src="<?= htmlspecialchars($itemImg) ?>" alt="<?= htmlspecialchars($item['name'] ?? 'Product') ?>" class="summary-item-img" onerror="this.onerror=null; this.outerHTML='<div class=\'summary-item-img\' style=\'background: rgba(255,0,255,0.1);\'></div>';">
                                    <?php else: ?>
                                        <div class="summary-item-img" style="background: rgba(255,0,255,0.1);"></div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <div class="summary-item-name"><?= htmlspecialchars($item['name'] ?? 'CBD Product') ?></div>
                                        <div class="summary-item-qty">Qty: <?= intval($item['qty'] ?? 1) ?></div>
                                    </div>
                                </div>
                                <div class="summary-item-price"><?= $currencySymbol ?><?= htmlspecialchars($item['price'] ?? '0.00') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="summary-item">
                            <div class="summary-item-details">
                                <div class="summary-item-img" style="background: rgba(255,0,255,0.1);"></div>
                                <div>
                                    <div class="summary-item-name">Full Spectrum Vijaya Extract</div>
                                    <div class="summary-item-qty">Qty: 1</div>
                                </div>
                            </div>
                            <div class="summary-item-price"><?= $currencySymbol ?>2,499.00</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-details">
                                <div class="summary-item-img" style="background: rgba(255,0,255,0.1);"></div>
                                <div>
                                    <div class="summary-item-name">CBD Sleep Drops (1000mg)</div>
                                    <div class="summary-item-qty">Qty: 1</div>
                                </div>
                            </div>
                            <div class="summary-item-price"><?= $currencySymbol ?>1,899.00</div>
                        </div>
                        <?php 
                            // Fallback calculations if cart is empty for demonstration
                            $cartTotal = 4398.00;
                            $shippingFee = ($cartTotal >= $freeShippingThreshold) ? 0 : $standardShippingFee;
                            $taxAmount = $cartTotal * $taxRateMultiplier;
                            $finalTotal = $cartTotal + $shippingFee + $taxAmount;
                        ?>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 25px;">
                    <div class="summary-subtotal">
                        <span>Subtotal</span>
                        <span><?= $currencySymbol ?><?= number_format($cartTotal, 2) ?></span>
                    </div>
                    
                    <?php if ($discountAmount > 0): ?>
                        <div class="summary-subtotal" style="color: #4caf50;">
                            <span>Referral Discount (<?= $referralDiscountPercent ?>%)</span>
                            <span>- <?= $currencySymbol ?><?= number_format($discountAmount, 2) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['referral_error'])): ?>
                        <div class="summary-subtotal" style="color: #ff4d4d; font-size: 12px; margin-top: -5px; margin-bottom: 10px;">
                            <span><?= htmlspecialchars($_SESSION['referral_error']) ?></span>
                        </div>
                        <?php unset($_SESSION['referral_error']); ?>
                    <?php endif; ?>

                    <?php if ($walletDeduction > 0): ?>
                        <div class="summary-subtotal" style="color: #e5c378;">
                            <span>Wallet Cashback Applied</span>
                            <span>- <?= $currencySymbol ?><?= number_format($walletDeduction, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-subtotal">
                        <span>Shipping</span>
                        <?php if ($shippingFee == 0 && $cartTotal > 0): ?>
                            <span style="color: #4caf50;">Free (over <?= $currencySymbol . number_format($freeShippingThreshold, 0) ?>)</span>
                        <?php elseif ($cartTotal == 0): ?>
                            <span><?= $currencySymbol ?>0.00</span>
                        <?php else: ?>
                            <span><?= $currencySymbol ?><?= number_format($shippingFee, 2) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="summary-subtotal">
                        <span>Estimated Tax (<?= $taxRatePercentage ?>%)</span>
                        <span><?= $currencySymbol ?><?= number_format($taxAmount, 2) ?></span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Total</span>
                        <span><?= $currencySymbol ?><?= number_format($finalTotal, 2) ?></span>
                    </div>
                </div>

                <?php if ($isLoggedIn && $walletBalance > 0): ?>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(229, 195, 120, 0.05); border: 1px dashed rgba(229, 195, 120, 0.4); padding: 12px 15px; border-radius: 8px;">
                            <div>
                                <span style="color: #e5c378; font-size: 14px; font-weight: bold; display: block; margin-bottom: 3px;">Wallet Balance: <?= $currencySymbol ?><?= number_format($walletBalance, 2) ?></span>
                                <span style="color: #aaa; font-size: 11px;">You can redeem up to 50% (<?= $currencySymbol ?><?= number_format($walletBalance * 0.5, 2) ?>)</span>
                            </div>
                            <?php if (!$walletApplied): ?>
                                <button type="button" class="btn-outline" style="padding: 6px 12px; font-size: 11px; color: #e5c378; border-color: #e5c378;" onclick="window.location.href='checkout.php?action=apply_wallet'">Apply</button>
                            <?php else: ?>
                                <button type="button" class="btn-outline" style="padding: 6px 12px; font-size: 11px; color: #ff4d4d; border-color: #ff4d4d; background: rgba(255, 77, 77, 0.1);" onclick="window.location.href='checkout.php?action=remove_wallet'">Remove</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 25px; font-size: 11px; color: rgba(255,255,255,0.4); text-align: center; line-height: 1.5;">
                    <svg style="width:12px; height:12px; vertical-align: middle; margin-right: 4px; stroke: currentColor;" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Payments are encrypted and secured by bank-grade SSL. Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our privacy policy.
                </div>
            </div>

        </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <div id="successModal" class="success-modal-overlay">
        <div class="success-modal-content">
            <div class="success-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h2 class="success-title">Thank You, <span id="customerNameSpan">Customer</span>!</h2>
            <p class="success-text">Your order has been placed securely and is now being processed. A confirmation email will be sent to you shortly.</p>
            
            <div class="order-id-box">
                Order ID: <span id="orderIdSpan">#ORD-XXXXXX</span>
            </div>
            
            <div class="redirect-text">
                Redirecting to home page in <span id="countdownTimer">5</span> seconds...
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Search Typewriter Effect
            const searchInput = document.getElementById('auto-search');
            
            if (searchInput) {
                const phrases = ["Search 'Vijaya Extract'...", "Search 'Sleep Drops'...", "Search 'Pain Relief'...", "Search 'Full Spectrum'..."];
                let phraseIndex = 0, charIndex = 0, isDeleting = false;

                function typeEffect() {
                    const currentPhrase = phrases[phraseIndex];
                    if (isDeleting) {
                        searchInput.placeholder = currentPhrase.substring(0, charIndex - 1);
                        charIndex--;
                    } else {
                        searchInput.placeholder = currentPhrase.substring(0, charIndex + 1);
                        charIndex++;
                    }

                    let typeSpeed = isDeleting ? 40 : 80;
                    if (!isDeleting && charIndex === currentPhrase.length) {
                        typeSpeed = 2000; isDeleting = true;
                    } else if (isDeleting && charIndex === 0) {
                        isDeleting = false; phraseIndex = (phraseIndex + 1) % phrases.length; typeSpeed = 500; 
                    }
                    setTimeout(typeEffect, typeSpeed);
                }
                setTimeout(typeEffect, 1000);
            }

            // Payment Method Selector Script
            window.setPaymentMethod = function(method, el) {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
                if (el) el.classList.add('active');
                const hiddenInput = document.getElementById('selectedPaymentMethod');
                if (hiddenInput) {
                    hiddenInput.value = method;
                }
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    if (method === 'UPI') {
                        submitBtn.innerText = "Proceed to UPI Payment →";
                    } else if (method === 'Cashfree') {
                        submitBtn.innerText = "Proceed to Online Payment (Cashfree) →";
                    } else {
                        submitBtn.innerText = "Place Order (Cash on Delivery)";
                    }
                }
            };

            // STRICT STATE vs PIN CODE VALIDATION ENGINE
            const statePinMapping = {
                "Andaman and Nicobar Islands": ["74"],
                "Andhra Pradesh": ["51", "52", "53"],
                "Arunachal Pradesh": ["79"],
                "Assam": ["78"],
                "Bihar": ["80", "81", "82", "83", "84", "85"],
                "Chandigarh": ["16"],
                "Chhattisgarh": ["49"],
                "Dadra and Nagar Haveli": ["39"],
                "Daman and Diu": ["39"],
                "Delhi": ["11"],
                "Goa": ["40"],
                "Gujarat": ["36", "37", "38", "39"],
                "Haryana": ["12", "13"],
                "Himachal Pradesh": ["17"],
                "Jammu and Kashmir": ["19"],
                "Jharkhand": ["81", "82", "83"],
                "Karnataka": ["56", "57", "58", "59"],
                "Kerala": ["67", "68", "69"],
                "Lakshadweep": ["68"],
                "Madhya Pradesh": ["45", "46", "47", "48"],
                "Maharashtra": ["40", "41", "42", "43", "44"],
                "Manipur": ["79"],
                "Meghalaya": ["79"],
                "Mizoram": ["79"],
                "Nagaland": ["79"],
                "Odisha": ["75", "76", "77"],
                "Puducherry": ["60"],
                "Punjab": ["14", "15"],
                "Rajasthan": ["30", "31", "32", "33", "34"],
                "Sikkim": ["73"],
                "Tamil Nadu": ["60", "61", "62", "63", "64"],
                "Telangana": ["50"],
                "Tripura": ["79"],
                "Uttar Pradesh": ["20", "21", "22", "23", "24", "25", "26", "27", "28"],
                "Uttarakhand": ["24", "26"],
                "West Bengal": ["70", "71", "72", "73", "74"]
            };

            const stateSelect = document.getElementById('state');
            const zipInput = document.getElementById('zip');
            const pinError = document.getElementById('pinError');
            const submitBtn = document.getElementById('submitBtn');

            function validatePinWithState() {
                const selectedState = stateSelect.value;
                const pinValue = zipInput.value.trim();
                
                // Only validate if both are filled and PIN has 6 digits
                if (selectedState && pinValue.length === 6) {
                    const pinPrefix = pinValue.substring(0, 2);
                    const validPrefixes = statePinMapping[selectedState] || [];
                    
                    if (validPrefixes.includes(pinPrefix)) {
                        // Valid matching PIN
                        zipInput.setCustomValidity("");
                        pinError.style.display = "none";
                        stateSelect.classList.add('is-valid');
                        submitBtn.disabled = false;
                    } else {
                        // Invalid mismatching PIN
                        zipInput.setCustomValidity("PIN code does not belong to the selected state.");
                        pinError.style.display = "block";
                        stateSelect.classList.remove('is-valid');
                        submitBtn.disabled = true;
                    }
                } else {
                    // Reset if incomplete
                    zipInput.setCustomValidity("");
                    pinError.style.display = "none";
                    stateSelect.classList.remove('is-valid');
                    submitBtn.disabled = false; // Let normal HTML5 validation handle incomplete fields
                }
            }

            stateSelect.addEventListener('change', validatePinWithState);
            zipInput.addEventListener('input', function(e) {
                // Force numeric only
                this.value = this.value.replace(/[^0-9]/g, '');
                validatePinWithState();
            });

            // SAVED ADDRESS SELECTION ENGINE
            const savedAddressSelect = document.getElementById('saved_address');
            if (savedAddressSelect) {
                savedAddressSelect.addEventListener('change', function() {
                    const selected = this.options[this.selectedIndex];
                    
                    if (this.value !== "" && this.value !== "new") {
                        // Auto-fill fields
                        document.getElementById('address').value = selected.getAttribute('data-street');
                        document.getElementById('city').value = selected.getAttribute('data-city');
                        document.getElementById('state').value = selected.getAttribute('data-state');
                        document.getElementById('zip').value = selected.getAttribute('data-zip');
                        
                        // Force validation update
                        validatePinWithState();
                    } else if (this.value === "new") {
                        // Clear fields for new address
                        document.getElementById('address').value = '';
                        document.getElementById('city').value = '';
                        document.getElementById('state').value = '';
                        document.getElementById('zip').value = '';
                        validatePinWithState();
                    }
                });
            }

            // HANDLE PHP SUCCESS INJECTION
            <?php if ($orderSuccess): ?>
                const successModal = document.getElementById('successModal');
                const customerNameSpan = document.getElementById('customerNameSpan');
                const orderIdSpan = document.getElementById('orderIdSpan');
                const countdownTimer = document.getElementById('countdownTimer');

                customerNameSpan.innerText = "<?= $customerName ?>";
                orderIdSpan.innerText = "<?= $orderId ?>";

                successModal.style.display = 'flex';
                void successModal.offsetWidth; // trigger reflow
                successModal.classList.add('show');

                let timeLeft = 5;
                const timerInterval = setInterval(() => {
                    timeLeft--;
                    countdownTimer.innerText = timeLeft;
                    
                    if(timeLeft <= 0) {
                        clearInterval(timerInterval);
                        window.location.href = 'cbd.php';
                    }
                }, 1000);
            <?php else: ?>
                // IF NOT SUCCESS, ONLY ADD LOADING STATE ON SUBMIT
                const checkoutForm = document.getElementById('checkoutForm');
                if(checkoutForm) {
                    checkoutForm.addEventListener('submit', function() {
                        submitBtn.disabled = true;
                        submitBtn.innerText = "Processing Payment...";
                    });
                }
            <?php endif; ?>

        });
    </script>
    <script src="assets/js/responsive.js"></script>
</body>
</html>