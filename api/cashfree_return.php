<?php
// api/cashfree_return.php
// Return endpoint after customer completes checkout on Cashfree PG

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/payment/PaymentService.php';
require_once __DIR__ . '/../includes/payment/CashfreeEngine.php';

$orderId = trim($_GET['order_id'] ?? ($_POST['order_id'] ?? ''));
$isMock = !empty($_GET['mock']);

if (empty($orderId)) {
    header("Location: ../checkout.php?error=" . urlencode("Missing order reference from payment gateway."));
    exit;
}

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM payments WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $payment = $stmt->fetch();

    if (!$payment) {
        // Fallback: check transaction_id
        $stmt2 = $db->prepare("SELECT * FROM payments WHERE transaction_id = ? LIMIT 1");
        $stmt2->execute([$orderId]);
        $payment = $stmt2->fetch();
    }

    if (!$payment) {
        header("Location: ../checkout.php?error=" . urlencode("Payment session not found. Please try again or contact support."));
        exit;
    }

    $transactionId = $payment['transaction_id'];

    // If payment is already confirmed
    if ($payment['status'] === 'SUCCESS' && !empty($payment['order_number'])) {
        header("Location: ../thank_you.php?order=" . urlencode($payment['order_number']));
        exit;
    }

    // Verify payment with Cashfree API
    $engine = new CashfreeEngine();
    $verifyResult = $engine->verifyPayment($transactionId, [
        'order_number' => $orderId,
        'amount' => (float)$payment['amount'],
        'mock' => $isMock
    ]);

    if (!empty($verifyResult['verified']) && $verifyResult['status'] === 'SUCCESS') {
        $providerPaymentId = $verifyResult['provider_payment_id'] ?? ('CF-' . time());
        $amount = (float)($verifyResult['amount'] ?? $payment['amount']);

        $confirm = PaymentService::confirmUpiPayment($transactionId, $providerPaymentId, $amount, $verifyResult['raw'] ?? []);

        if ($confirm['success']) {
            header("Location: ../thank_you.php?order=" . urlencode($confirm['order_number']));
            exit;
        } else {
            header("Location: ../checkout.php?error=" . urlencode($confirm['error'] ?? 'Order fulfillment failed.'));
            exit;
        }
    } else {
        // Payment failed or cancelled
        $failReason = $verifyResult['error'] ?? 'Payment was cancelled or not completed.';
        $failStmt = $db->prepare("UPDATE payments SET status = 'FAILED', failure_reason = ?, updated_at = NOW() WHERE transaction_id = ?");
        $failStmt->execute([$failReason, $transactionId]);

        header("Location: ../checkout.php?error=" . urlencode("Payment unsuccessful: " . $failReason));
        exit;
    }

} catch (\Throwable $e) {
    error_log("Cashfree return error: " . $e->getMessage());
    header("Location: ../checkout.php?error=" . urlencode("Verification error occurred. Please contact support."));
    exit;
}
