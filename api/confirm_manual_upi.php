<?php
// api/confirm_manual_upi.php
// Endpoint for customer to submit UPI UTR / Reference ID and confirm payment

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payment/PaymentService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$transactionId = trim($input['transaction_id'] ?? '');
$utrNumber = trim($input['utr_number'] ?? '');

if (empty($transactionId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing transaction ID.']);
    exit;
}

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM payments WHERE transaction_id = ? LIMIT 1");
    $stmt->execute([$transactionId]);
    $payment = $stmt->fetch();

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Payment transaction session not found or expired.']);
        exit;
    }

    // If already confirmed
    if ($payment['status'] === 'SUCCESS' && !empty($payment['order_number'])) {
        echo json_encode([
            'success' => true,
            'status' => 'SUCCESS',
            'order_number' => $payment['order_number'],
            'redirect_url' => 'thank_you.php?order=' . urlencode($payment['order_number'])
        ]);
        exit;
    }

    $amount = (float)$payment['amount'];
    $providerPaymentId = !empty($utrNumber) ? $utrNumber : ('UPI-' . date('YmdHis') . '-' . substr($transactionId, -6));

    // Confirm payment and create confirmed order in DB
    $confirmResult = PaymentService::confirmUpiPayment(
        $transactionId,
        $providerPaymentId,
        $amount,
        ['utr_number' => $utrNumber, 'submitted_by' => 'customer_qr_confirm']
    );

    if ($confirmResult['success']) {
        // If UTR was provided, update notes / provider_payment_id
        if (!empty($utrNumber)) {
            $updStmt = $db->prepare("UPDATE payments SET provider_payment_id = ?, failure_reason = NULL WHERE transaction_id = ?");
            $updStmt->execute([$utrNumber, $transactionId]);

            if (!empty($confirmResult['order_id'])) {
                $updOrd = $db->prepare("UPDATE orders SET notes = CONCAT(COALESCE(notes, ''), ' [Customer UPI UTR: ', ?, ']') WHERE id = ?");
                $updOrd->execute([$utrNumber, $confirmResult['order_id']]);
            }
        }

        echo json_encode([
            'success' => true,
            'status' => 'SUCCESS',
            'order_number' => $confirmResult['order_number'],
            'redirect_url' => 'thank_you.php?order=' . urlencode($confirmResult['order_number'])
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $confirmResult['error'] ?? 'Payment confirmation failed. Please contact support.'
        ]);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Confirmation error: ' . $e->getMessage()
    ]);
}
