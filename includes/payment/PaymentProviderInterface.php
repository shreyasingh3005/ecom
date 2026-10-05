<?php
// includes/payment/PaymentProviderInterface.php
// Modular interface for payment gateways and UPI engines

interface PaymentProviderInterface {
    /**
     * Get unique provider identifier (e.g., 'native_upi', 'razorpay')
     */
    public function getIdentifier(): string;

    /**
     * Get human-readable provider name
     */
    public function getDisplayName(): string;

    /**
     * Create a payment transaction session
     *
     * @param array $paymentData [order_number, transaction_id, amount, currency, customer, items]
     * @return array [success => bool, transaction_id => string, qr_uri => string, data => array]
     */
    public function createPayment(array $paymentData): array;

    /**
     * Generate dynamic standard UPI URI
     *
     * @param string $upiId
     * @param string $merchantName
     * @param float $amount
     * @param string $orderNumber
     * @param string $transactionId
     * @return string
     */
    public function generateUpiUri(string $upiId, string $merchantName, float $amount, string $orderNumber, string $transactionId): string;

    /**
     * Verify payment status server-side (query provider or verify transaction)
     *
     * @param string $transactionId
     * @param array $context
     * @return array [verified => bool, status => string, provider_payment_id => string, amount => float, error => string]
     */
    public function verifyPayment(string $transactionId, array $context = []): array;

    /**
     * Handle and verify an incoming webhook payload
     *
     * @param array|string $rawPayload
     * @param array $headers
     * @return array [verified => bool, transaction_id => string, provider_payment_id => string, status => string, amount => float, error => string]
     */
    public function handleWebhook($rawPayload, array $headers): array;
}
