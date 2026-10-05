<?php
// api/payment_webhook.php
// Production Webhook Endpoint for Automatic Server-Side Payment Verification

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/payment/PaymentService.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Read raw body and headers
$rawInput = file_get_contents('php://input');
$headers = getallheaders();

if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty request payload']);
    exit;
}

$provider = PaymentService::getProvider();
$verification = $provider->handleWebhook($rawInput, $headers);

if (!$verification['verified']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $verification['error'] ?? 'Webhook signature verification failed'
    ]);
    exit;
}

$transactionId = $verification['transaction_id'];
$providerPaymentId = $verification['provider_payment_id'] ?? ('UTR-' . time());
$amount = (float)$verification['amount'];
$currency = $verification['currency'] ?? 'INR';
$status = strtoupper($verification['status'] ?? '');

if ($status !== 'SUCCESS') {
    // Payment failed at provider level
    $db = Database::getInstance();
    $stmt = $db->prepare("UPDATE payments SET status = 'FAILED', failure_reason = 'Payment provider reported failure', updated_at = NOW() WHERE transaction_id = ?");
    $stmt->execute([$transactionId]);

    http_response_code(200);
    echo json_encode(['success' => true, 'status' => 'PAYMENT_FAILED_RECORDED']);
    exit;
}

// Confirm order idempotently and deduct inventory atomically
$result = PaymentService::confirmUpiPayment($transactionId, $providerPaymentId, $amount, $verification['raw'] ?? []);

if (!$result['success']) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
    exit;
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'status' => 'CONFIRMED',
    'order_number' => $result['order_number'],
    'order_id' => $result['order_id'],
    'transaction_id' => $transactionId,
    'already_confirmed' => $result['already_confirmed'] ?? false
]);
