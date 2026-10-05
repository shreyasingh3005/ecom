<?php
// api/simulate_upi_payment.php
// Simulator for sandbox testing of automated server-side UPI payment verification

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/payment/PaymentService.php';
require_once __DIR__ . '/../includes/payment/NativeUPIEngine.php';

$transactionId = trim($_REQUEST['transaction_id'] ?? '');

if (empty($transactionId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing transaction_id']);
    exit;
}

$payment = PaymentService::getPaymentStatus($transactionId);
if (!$payment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Transaction not found']);
    exit;
}

if ($payment['status'] === 'SUCCESS') {
    echo json_encode([
        'success' => true,
        'message' => 'Payment already confirmed.',
        'status' => 'SUCCESS',
        'order_number' => $payment['order_number']
    ]);
    exit;
}

$amount = (float)$payment['amount'];
$currency = 'INR';
$status = 'SUCCESS';
$mockUtr = 'UPI/' . date('Ymd') . '/' . rand(100000000, 999999999);
$secret = Settings::get('payment_webhook_secret', 'whsec_kams_upi_2026');

// Compute genuine HMAC-SHA256 signature
$signature = NativeUPIEngine::computeSignature($transactionId, $mockUtr, $amount, $currency, $status, $secret);

$webhookPayload = [
    'transaction_id' => $transactionId,
    'provider_payment_id' => $mockUtr,
    'amount' => $amount,
    'currency' => $currency,
    'status' => $status,
    'signature' => $signature,
    'timestamp' => time()
];

// Execute server-side confirmation via PaymentService
$result = PaymentService::confirmUpiPayment($transactionId, $mockUtr, $amount, $webhookPayload);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Simulated UPI payment verified and order confirmed successfully.',
        'transaction_id' => $transactionId,
        'order_number' => $result['order_number'],
        'provider_payment_id' => $mockUtr,
        'amount' => $amount,
        'signature' => $signature
    ]);
} else {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
}
