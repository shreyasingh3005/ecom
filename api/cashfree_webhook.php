<?php
// api/cashfree_webhook.php
// Dedicated Webhook Handler for Cashfree Payment Gateway notifications

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/payment/PaymentService.php';
require_once __DIR__ . '/../includes/payment/CashfreeEngine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];

if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty request payload']);
    exit;
}

$engine = new CashfreeEngine();
$verification = $engine->handleWebhook($rawInput, $headers);

if (!$verification['verified']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $verification['error'] ?? 'Signature verification failed'
    ]);
    exit;
}

$transactionId = $verification['transaction_id'];
$orderNumber = $verification['order_number'];
$providerPaymentId = $verification['provider_payment_id'] ?? ('CF-' . time());
$amount = (float)$verification['amount'];
$status = strtoupper($verification['status'] ?? '');

$db = Database::getInstance();

if ($status !== 'SUCCESS') {
    $stmt = $db->prepare("UPDATE payments SET status = 'FAILED', failure_reason = 'Cashfree reported failed status', updated_at = NOW() WHERE transaction_id = ? OR order_number = ?");
    $stmt->execute([$transactionId, $orderNumber]);

    http_response_code(200);
    echo json_encode(['success' => true, 'status' => 'FAILED_RECORDED']);
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
