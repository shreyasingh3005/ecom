<?php
// api/payment_status.php
// Real-time payment verification polling endpoint

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payment/PaymentService.php';

$transactionId = trim($_GET['transaction_id'] ?? '');

if (empty($transactionId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing transaction_id parameter']);
    exit;
}

$payment = PaymentService::getPaymentStatus($transactionId);

if (!$payment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Transaction not found']);
    exit;
}

$status = $payment['status']; // 'PENDING', 'PROCESSING', 'SUCCESS', 'FAILED', 'EXPIRED'
$orderNumber = $payment['order_number'];
$amount = (float)$payment['amount'];

$response = [
    'success' => true,
    'status' => $status,
    'transaction_id' => $transactionId,
    'order_number' => $orderNumber,
    'amount' => $amount,
    'currency' => $payment['currency'] ?? 'INR'
];

if ($status === 'SUCCESS') {
    $response['redirect_url'] = 'thank_you.php?order=' . urlencode($orderNumber);
    $response['paid_at'] = $payment['paid_at'];
    $response['provider_payment_id'] = $payment['provider_payment_id'];
} elseif ($status === 'FAILED') {
    $response['error'] = $payment['failure_reason'] ?? 'Payment failed or rejected by provider.';
}

echo json_encode($response);
