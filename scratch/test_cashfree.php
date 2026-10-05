<?php
require_once __DIR__ . '/../includes/payment/PaymentProviderInterface.php';
require_once __DIR__ . '/../includes/payment/CashfreeEngine.php';

$engine = new CashfreeEngine();
echo "Provider: " . $engine->getIdentifier() . "\n";
echo "Name: " . $engine->getDisplayName() . "\n";

$upiUri = $engine->generateUpiUri('test@upi', 'KAMS HEMP', 500.0, 'ORD-123', 'TXN-456');
echo "UPI URI: " . $upiUri . "\n";

// Mock payment session
$paymentResult = $engine->createPayment([
    'order_number' => 'ORD-TEST-99',
    'transaction_id' => 'TXN-CF-TEST',
    'amount' => 1499.00,
    'currency' => 'INR',
    'customer' => [
        'first_name' => 'Aman',
        'last_name' => 'Chaursiya',
        'email' => 'aman@example.com',
        'phone' => '9876543210'
    ]
]);

echo "Create Payment Success: " . ($paymentResult['success'] ? 'YES' : 'NO') . "\n";
echo "Payment URL: " . ($paymentResult['payment_url'] ?? 'N/A') . "\n";
echo "Is Mock: " . (!empty($paymentResult['is_mock']) ? 'YES' : 'NO') . "\n";

$verify = $engine->verifyPayment('ORD-TEST-99', ['order_number' => 'ORD-TEST-99', 'mock' => true]);
echo "Verify Status: " . $verify['status'] . "\n";
