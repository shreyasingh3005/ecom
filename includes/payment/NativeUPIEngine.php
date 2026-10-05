<?php
// includes/payment/NativeUPIEngine.php
// Production-ready Native Dynamic UPI Engine with cryptographic signature verification

require_once __DIR__ . '/PaymentProviderInterface.php';
require_once __DIR__ . '/../settings.php';

class NativeUPIEngine implements PaymentProviderInterface {
    
    public function getIdentifier(): string {
        return 'native_upi';
    }

    public function getDisplayName(): string {
        return 'Direct UPI & Dynamic QR';
    }

    /**
     * Generate standard UPI Payment URI
     */
    public function generateUpiUri(string $upiId, string $merchantName, float $amount, string $orderNumber, string $transactionId): string {
        $cleanUpi = trim($upiId);
        $encodedName = rawurlencode(trim($merchantName));
        $amountFormatted = number_format($amount, 2, '.', '');
        $encodedNote = rawurlencode("Payment for Order " . $orderNumber);
        
        // Standard NPCI UPI URI Specification:
        // pa = Payee Address (VPA)
        // pn = Payee Name
        // am = Transaction Amount
        // cu = Currency Code (INR)
        // tr = Transaction Reference ID
        // tn = Transaction Note
        return "upi://pay?pa={$cleanUpi}&pn={$encodedName}&am={$amountFormatted}&cu=INR&tr={$transactionId}&tn={$encodedNote}";
    }

    /**
     * Generate app-specific deep links
     */
    public function generateAppDeepLinks(string $baseUpiUri): array {
        $cleanUri = preg_replace('/^upi:\/\/pay\?/', '', $baseUpiUri);
        return [
            'generic' => $baseUpiUri,
            'gpay'    => 'gpay://upi/pay?' . $cleanUri,
            'phonepe' => 'phonepe://pay?' . $cleanUri,
            'paytm'   => 'paytmmp://pay?' . $cleanUri,
            'bhim'    => 'bhim://pay?' . $cleanUri
        ];
    }

    /**
     * Create payment session with dynamic QR code
     */
    public function createPayment(array $paymentData): array {
        $upiId = Settings::get('upi_id', 'kamshemp@upi');
        $merchantName = Settings::get('upi_merchant_name', 'KAMS HEMP India');
        $amount = (float)($paymentData['amount'] ?? 0);
        $orderNumber = $paymentData['order_number'] ?? ('ORD-' . time());
        $transactionId = $paymentData['transaction_id'] ?? ('TXN-' . strtoupper(bin2hex(random_bytes(8))));

        $upiUri = $this->generateUpiUri($upiId, $merchantName, $amount, $orderNumber, $transactionId);
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=" . rawurlencode($upiUri);
        $appLinks = $this->generateAppDeepLinks($upiUri);

        return [
            'success' => true,
            'provider' => $this->getIdentifier(),
            'transaction_id' => $transactionId,
            'order_number' => $orderNumber,
            'amount' => $amount,
            'currency' => 'INR',
            'upi_id' => $upiId,
            'merchant_name' => $merchantName,
            'upi_uri' => $upiUri,
            'qr_code_url' => $qrCodeUrl,
            'app_links' => $appLinks
        ];
    }

    /**
     * Verify payment status server-side
     */
    public function verifyPayment(string $transactionId, array $context = []): array {
        // Query database payments table
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM payments WHERE transaction_id = ? LIMIT 1");
        $stmt->execute([$transactionId]);
        $payment = $stmt->fetch();

        if (!$payment) {
            return [
                'verified' => false,
                'status' => 'FAILED',
                'error' => 'Payment transaction not found.'
            ];
        }

        return [
            'verified' => ($payment['status'] === 'SUCCESS'),
            'status' => $payment['status'],
            'transaction_id' => $payment['transaction_id'],
            'order_number' => $payment['order_number'],
            'amount' => (float)$payment['amount'],
            'provider_payment_id' => $payment['provider_payment_id'] ?? '',
            'paid_at' => $payment['paid_at'] ?? null
        ];
    }

    /**
     * Cryptographically verify and parse incoming webhook payload
     */
    public function handleWebhook($rawPayload, array $headers): array {
        $secret = Settings::get('payment_webhook_secret', 'whsec_kams_upi_2026');
        
        $data = is_array($rawPayload) ? $rawPayload : json_decode($rawPayload, true);
        if (!$data || !is_array($data)) {
            return [
                'verified' => false,
                'error' => 'Invalid JSON webhook payload'
            ];
        }

        // Expected webhook schema:
        // transaction_id, provider_payment_id (e.g. UTR or bank ref), amount, currency, status ('SUCCESS'|'FAILED'), signature
        $transactionId = trim($data['transaction_id'] ?? '');
        $providerPaymentId = trim($data['provider_payment_id'] ?? ($data['utr'] ?? ''));
        $amount = (float)($data['amount'] ?? 0);
        $currency = strtoupper(trim($data['currency'] ?? 'INR'));
        $status = strtoupper(trim($data['status'] ?? ''));
        $signature = $data['signature'] ?? ($headers['X-Webhook-Signature'] ?? ($headers['x-webhook-signature'] ?? ''));

        if (empty($transactionId) || empty($status) || $amount <= 0) {
            return [
                'verified' => false,
                'error' => 'Missing mandatory webhook fields: transaction_id, status, or amount.'
            ];
        }

        // Validate HMAC-SHA256 signature
        // String to sign: transaction_id|provider_payment_id|amount|currency|status
        $payloadStringToSign = "{$transactionId}|{$providerPaymentId}|" . number_format($amount, 2, '.', '') . "|{$currency}|{$status}";
        $expectedSignature = hash_hmac('sha256', $payloadStringToSign, $secret);

        if (!hash_equals($expectedSignature, (string)$signature)) {
            return [
                'verified' => false,
                'error' => 'Invalid webhook cryptographic signature. Verification rejected.'
            ];
        }

        return [
            'verified' => true,
            'transaction_id' => $transactionId,
            'provider_payment_id' => $providerPaymentId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'raw' => $data
        ];
    }

    /**
     * Helper to compute valid signature for simulation / gateway integration
     */
    public static function computeSignature(string $transactionId, string $providerPaymentId, float $amount, string $currency, string $status, string $secret): string {
        $payloadStringToSign = "{$transactionId}|{$providerPaymentId}|" . number_format($amount, 2, '.', '') . "|{$currency}|{$status}";
        return hash_hmac('sha256', $payloadStringToSign, $secret);
    }
}
