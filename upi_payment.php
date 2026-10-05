<?php
// upi_payment.php - Dynamic UPI Scan & Pay Screen with Real-Time Server Verification
session_start();

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/payment/PaymentService.php';
require_once __DIR__ . '/includes/payment/NativeUPIEngine.php';

$transactionId = trim($_GET['txn'] ?? '');

if (empty($transactionId)) {
    header("Location: cart.php");
    exit;
}

$payment = PaymentService::getPaymentStatus($transactionId);

if (!$payment) {
    die("Payment session not found or has expired. <a href='cart.php'>Return to cart</a>");
}

// If already confirmed, redirect directly to thank you page
if ($payment['status'] === 'SUCCESS') {
    header("Location: thank_you.php?order=" . urlencode($payment['order_number']));
    exit;
}

// Handle Direct POST confirmation if JS is disabled
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_payment') {
    $utr = trim($_POST['utr_number'] ?? '');
    $res = PaymentService::confirmUpiPayment($transactionId, $utr ?: ('UPI-' . date('YmdHis')), (float)$payment['amount'], ['utr' => $utr]);
    if ($res['success']) {
        if (!empty($utr)) {
            $db = Database::getInstance();
            $db->prepare("UPDATE payments SET provider_payment_id = ? WHERE transaction_id = ?")->execute([$utr, $transactionId]);
        }
        header("Location: thank_you.php?order=" . urlencode($payment['order_number']));
        exit;
    }
}

$storeName = Settings::get('store_name', 'KAMS HEMP');
$merchantName = Settings::get('upi_merchant_name', 'KAMS HEMP India');
$adminUpiId = Settings::get('upi_id', 'kamshemp@upi');
$amount = (float)$payment['amount'];
$orderNumber = $payment['order_number'];

// Generate dynamic UPI URI and App Deep Links
$engine = new NativeUPIEngine();
$upiUri = $engine->generateUpiUri($adminUpiId, $merchantName, $amount, $orderNumber, $transactionId);
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=12&data=" . rawurlencode($upiUri);
$deepLinks = $engine->generateAppDeepLinks($upiUri);

$currencySymbol = Settings::getCurrencySymbol();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay via UPI | <?= htmlspecialchars($storeName) ?></title>
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
            --accent-purple: #c084fc;
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
                radial-gradient(circle at 15% 15%, rgba(0, 255, 204, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(192, 132, 252, 0.05) 0%, transparent 40%);
            color: var(--text-primary);
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .payment-container {
            width: 100%;
            max-width: 520px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            padding: 32px 28px;
            position: relative;
            overflow: hidden;
        }

        .payment-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #00ffcc, #38bdf8, #818cf8, #c084fc);
        }

        .header-section {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(0, 255, 204, 0.08);
            border: 1px solid rgba(0, 255, 204, 0.2);
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            color: var(--accent-cyan);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .header-title {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .order-meta {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .order-badge {
            color: #e2e8f0;
            font-family: monospace;
            background: rgba(255, 255, 255, 0.06);
            padding: 2px 8px;
            border-radius: 6px;
        }

        .amount-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01));
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .amount-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .amount-value {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 700;
            color: var(--accent-cyan);
        }

        .qr-wrapper {
            background: #ffffff;
            border-radius: 20px;
            padding: 18px;
            width: fit-content;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .qr-image {
            width: 260px;
            height: 260px;
            display: block;
            border-radius: 12px;
        }

        .qr-caption {
            color: #334155;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .upi-id-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 20px;
        }

        .upi-id-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            overflow: hidden;
        }

        .upi-id-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .upi-id-val {
            font-family: monospace;
            font-size: 14px;
            color: #f8fafc;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .btn-copy {
            background: rgba(0, 255, 204, 0.12);
            color: var(--accent-cyan);
            border: 1px solid rgba(0, 255, 204, 0.3);
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-copy:hover {
            background: rgba(0, 255, 204, 0.25);
            transform: translateY(-1px);
        }

        .status-tracker {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .pulse-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--accent-cyan);
            position: relative;
            flex-shrink: 0;
        }

        .pulse-dot::after {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            border-radius: 50%;
            background: var(--accent-cyan);
            opacity: 0.4;
            animation: pulse-ring 1.8s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 0.8; }
            50% { transform: scale(1.6); opacity: 0.2; }
            100% { transform: scale(0.8); opacity: 0.8; }
        }

        .status-text {
            flex-grow: 1;
        }

        .status-title {
            font-size: 13px;
            font-weight: 600;
            color: #f1f5f9;
        }

        .status-subtitle {
            font-size: 11px;
            color: var(--text-secondary);
        }

        .app-links-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-secondary);
            font-weight: 600;
            text-align: center;
            margin-bottom: 12px;
        }

        .app-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 24px;
        }

        .app-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 10px 6px;
            text-align: center;
            text-decoration: none;
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .app-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            color: #fff;
        }

        .app-icon {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .simulation-banner {
            border-top: 1px dashed rgba(255, 255, 255, 0.1);
            padding-top: 18px;
            text-align: center;
        }

        .btn-simulate {
            background: linear-gradient(135deg, rgba(37, 211, 102, 0.2), rgba(0, 255, 204, 0.2));
            border: 1px solid rgba(37, 211, 102, 0.4);
            color: #4ade80;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-simulate:hover {
            background: linear-gradient(135deg, rgba(37, 211, 102, 0.35), rgba(0, 255, 204, 0.35));
            transform: translateY(-1px);
        }

        /* Success Overlay */
        .success-overlay {
            position: absolute;
            inset: 0;
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 32px;
            z-index: 50;
        }

        .success-overlay.active {
            display: flex;
            animation: fadeIn 0.4s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .success-icon-wrap {
            width: 72px;
            height: 72px;
            background: rgba(37, 211, 102, 0.15);
            border: 2px solid var(--accent-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-green);
            margin-bottom: 20px;
        }

        .success-icon-wrap svg {
            width: 36px;
            height: 36px;
            stroke-width: 3;
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>

    <div class="payment-container" id="paymentContainer">
        <!-- Success Screen Overlay -->
        <div class="success-overlay" id="successOverlay">
            <div class="success-icon-wrap">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 style="font-family: var(--font-display); font-size: 26px; margin-bottom: 8px; color: #fff;">Payment Verified!</h2>
            <p style="color: var(--text-secondary); font-size: 14px; max-width: 340px; margin-bottom: 20px;">
                Your UPI payment of <strong style="color: #fff;"><?= $currencySymbol ?><?= number_format($amount, 2) ?></strong> was confirmed server-side by our banking gateway.
            </p>
            <div style="font-size: 12px; color: var(--accent-cyan);">Redirecting to your order confirmation in <span id="countdownSecs">3</span>s...</div>
        </div>

        <!-- Header -->
        <div class="header-section">
            <div class="brand-pill">
                <span>⚡</span> Secure UPI Checkout
            </div>
            <h1 class="header-title">Scan & Pay via UPI</h1>
            <div class="order-meta">
                Order Reference: <span class="order-badge"><?= htmlspecialchars($orderNumber) ?></span>
            </div>
        </div>

        <!-- Payable Amount Box -->
        <div class="amount-card">
            <div class="amount-label">Amount Payable</div>
            <div class="amount-value"><?= $currencySymbol ?><?= number_format($amount, 2) ?></div>
        </div>

        <!-- Dynamic QR Code -->
        <div class="qr-wrapper">
            <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="UPI QR Code" class="qr-image" id="qrImg">
            <div class="qr-caption">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Scan with any UPI App
            </div>
        </div>

        <!-- UPI ID Box with Copy Button -->
        <div class="upi-id-box">
            <div class="upi-id-info">
                <span class="upi-id-label">Merchant UPI ID / VPA</span>
                <span class="upi-id-val" id="upiIdText"><?= htmlspecialchars($adminUpiId) ?></span>
            </div>
            <button class="btn-copy" type="button" onclick="copyUpiId()" id="copyBtn">Copy</button>
        </div>

        <!-- Real-Time Status Tracker -->
        <div class="status-tracker">
            <div class="pulse-dot" id="pulseDot"></div>
            <div class="status-text">
                <div class="status-title" id="statusTitle">Waiting for Payment...</div>
                <div class="status-subtitle" id="statusSubtitle">Scan the QR code and authorize payment in your UPI app.</div>
            </div>
        </div>

        <!-- Open in UPI App Deep Links -->
        <div class="app-links-title">Or Pay Directly from Installed App</div>
        <div class="app-grid">
            <a href="<?= htmlspecialchars($deepLinks['gpay']) ?>" class="app-btn">
                <span class="app-icon" style="background:#ea4335;">G</span>
                <span>GPay</span>
            </a>
            <a href="<?= htmlspecialchars($deepLinks['phonepe']) ?>" class="app-btn">
                <span class="app-icon" style="background:#5f259f;">P</span>
                <span>PhonePe</span>
            </a>
            <a href="<?= htmlspecialchars($deepLinks['paytm']) ?>" class="app-btn">
                <span class="app-icon" style="background:#00b9f5;">₹</span>
                <span>Paytm</span>
            </a>
            <a href="<?= htmlspecialchars($deepLinks['generic']) ?>" class="app-btn">
                <span class="app-icon" style="background:#00c853;">UPI</span>
                <span>Any UPI</span>
            </a>
        </div>

        <!-- Customer Payment Confirmation Form -->
        <div class="utr-confirmation-card" style="margin-top: 24px; padding: 20px; background: rgba(0, 255, 204, 0.05); border: 1px solid rgba(0, 255, 204, 0.25); border-radius: 16px; text-align: left;">
            <div style="font-weight: 700; font-size: 15px; color: #fff; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                <span style="color: #00ffcc;">✓</span> Done with Payment?
            </div>
            <p style="font-size: 12px; color: #94a3b8; margin-bottom: 14px; line-height: 1.4;">
                After completing the payment in your UPI app, enter your 12-digit UTR / Reference No. below or click confirm to verify your order immediately.
            </p>
            <form id="utrConfirmForm" onsubmit="submitCustomerUtr(event)" style="display: flex; flex-direction: column; gap: 10px;">
                <input type="text" id="utrInput" name="utr_number" placeholder="Enter 12-digit UPI UTR / Ref No. (Optional)" 
                       style="width: 100%; padding: 12px 14px; background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 10px; color: #fff; font-size: 14px; outline: none; box-sizing: border-box;">
                <button type="submit" id="utrSubmitBtn" 
                        style="width: 100%; padding: 14px; background: linear-gradient(135deg, #00ffcc 0%, #25d366 100%); color: #000; font-weight: 700; font-size: 14px; border: none; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span>✓</span> I Have Paid — Confirm My Order
                </button>
            </form>
        </div>

        <!-- Simulation Testing Trigger -->
        <div class="simulation-banner">
            <button class="btn-simulate" type="button" onclick="simulatePayment()" id="simBtn">
                <span>⚡</span>
                Simulate Successful Bank Payment
            </button>
            <div style="font-size: 11px; color: #64748b; margin-top: 8px;">
                Demonstrates server-side HMAC signature verification & webhook callback
            </div>
        </div>
    </div>

    <script>
        const TXN_ID = "<?= htmlspecialchars($transactionId) ?>";
        let isPolling = true;
        let pollInterval = null;

        function copyUpiId() {
            const text = document.getElementById('upiIdText').innerText;
            navigator.clipboard.writeText(text).then(() => {
                const btn = document.getElementById('copyBtn');
                btn.innerText = "Copied!";
                btn.style.background = "rgba(37, 211, 102, 0.2)";
                btn.style.color = "#25d366";
                btn.style.borderColor = "#25d366";
                setTimeout(() => {
                    btn.innerText = "Copy";
                    btn.style.background = "";
                    btn.style.color = "";
                    btn.style.borderColor = "";
                }, 2000);
            });
        }

        async function checkPaymentStatus() {
            if (!isPolling) return;

            try {
                const res = await fetch(`api/payment_status.php?transaction_id=${encodeURIComponent(TXN_ID)}&_t=${Date.now()}`);
                if (!res.ok) return;

                const data = await res.json();
                if (data.success && data.status === 'SUCCESS') {
                    // STOP POLLING
                    isPolling = false;
                    clearInterval(pollInterval);

                    // Show success UI
                    handlePaymentSuccess(data.redirect_url || `thank_you.php?order=${encodeURIComponent(data.order_number)}`);
                } else if (data.status === 'FAILED') {
                    isPolling = false;
                    clearInterval(pollInterval);
                    document.getElementById('statusTitle').innerText = "Payment Failed";
                    document.getElementById('statusSubtitle').innerText = data.error || "The payment transaction was rejected.";
                    document.getElementById('pulseDot').style.background = "#ff4d4d";
                }
            } catch (err) {
                console.error("Status polling error:", err);
            }
        }

        function handlePaymentSuccess(redirectUrl) {
            const overlay = document.getElementById('successOverlay');
            overlay.classList.add('active');

            let countdown = 3;
            const cdSpan = document.getElementById('countdownSecs');
            const cdTimer = setInterval(() => {
                countdown--;
                if (cdSpan) cdSpan.innerText = countdown;
                if (countdown <= 0) {
                    clearInterval(cdTimer);
                    window.location.href = redirectUrl;
                }
            }, 1000);
        }

        // Customer manual UPI confirmation trigger
        async function submitCustomerUtr(e) {
            if (e) e.preventDefault();
            const btn = document.getElementById('utrSubmitBtn');
            const input = document.getElementById('utrInput');
            const utr = input ? input.value.trim() : '';

            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = `<span>⏳</span> Confirming Payment...`;

            try {
                const res = await fetch('api/confirm_manual_upi.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        transaction_id: TXN_ID,
                        utr_number: utr
                    })
                });

                const data = await res.json();
                if (data.success && data.status === 'SUCCESS') {
                    isPolling = false;
                    clearInterval(pollInterval);
                    handlePaymentSuccess(data.redirect_url || `thank_you.php?order=${encodeURIComponent(data.order_number)}`);
                } else {
                    alert(data.error || 'Payment confirmation failed. Please check the reference number and try again.');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (err) {
                console.error('Confirmation error:', err);
                alert('Network or server error while confirming. Please try again.');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // Sandbox simulator trigger for testing
        async function simulatePayment() {
            const btn = document.getElementById('simBtn');
            btn.disabled = true;
            btn.innerHTML = `<span>⏳</span> Verifying with Bank...`;

            try {
                const res = await fetch(`api/simulate_upi_payment.php?transaction_id=${encodeURIComponent(TXN_ID)}`);
                const data = await res.json();
                if (data.success) {
                    // Polling loop will catch SUCCESS in next iteration, or trigger directly
                    checkPaymentStatus();
                } else {
                    alert("Simulation error: " + (data.error || "Unknown error"));
                    btn.disabled = false;
                    btn.innerHTML = `<span>⚡</span> Simulate Successful Bank Payment`;
                }
            } catch (e) {
                console.error(e);
                btn.disabled = false;
                btn.innerHTML = `<span>⚡</span> Simulate Successful Bank Payment`;
            }
        }

        // Start polling every 2.5 seconds
        pollInterval = setInterval(checkPaymentStatus, 2500);
        // Immediate first check
        checkPaymentStatus();
    </script>
    <script src="assets/js/responsive.js"></script>
</body>
</html>
