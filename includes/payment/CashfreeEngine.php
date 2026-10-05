<?php
// includes/payment/CashfreeEngine.php
// Production-Ready Cashfree Payment Gateway Engine (PG Orders API v2023-08-01)

require_once __DIR__ . '/PaymentProviderInterface.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/../db.php';

class CashfreeEngine implements PaymentProviderInterface {

    private string $appId;
    private string $secretKey;
    private string $env;
    private string $baseUrl;
    private string $apiVersion = '2023-08-01';

    public function __construct() {
        $this->appId = trim(Settings::get('cashfree_app_id', ''));
        $this->secretKey = trim(Settings::get('cashfree_secret_key', ''));
        $this->env = strtolower(trim(Settings::get('cashfree_env', 'sandbox')));
        $this->baseUrl = ($this->env === 'production') 
            ? 'https://api.cashfree.com/pg' 
            : 'https://sandbox.cashfree.com/pg';
    }

    public function getIdentifier(): string {
        return 'cashfree';
    }

    public function getDisplayName(): string {
        return 'Cashfree Payment Gateway (Cards, NetBanking, UPI)';
    }

    /**
     * Standard UPI URI generation
     */
    public function generateUpiUri(string $upiId, string $merchantName, float $amount, string $orderNumber, string $transactionId): string {
        $cleanUpi = trim($upiId);
        $encodedName = rawurlencode(trim($merchantName));
        $amountFormatted = number_format($amount, 2, '.', '');
        $encodedNote = rawurlencode("Cashfree Order " . $orderNumber);
        return "upi://pay?pa={$cleanUpi}&pn={$encodedName}&am={$amountFormatted}&cu=INR&tr={$transactionId}&tn={$encodedNote}";
    }

    /**
     * Create Cashfree PG Order session
     */
    public function createPayment(array $paymentData): array {
        $orderNumber = $paymentData['order_number'] ?? ('ORD-' . time());
        $transactionId = $paymentData['transaction_id'] ?? ('CF-' . strtoupper(bin2hex(random_bytes(8))));
        $amount = (float)($paymentData['amount'] ?? 0);
        $currency = $paymentData['currency'] ?? 'INR';

        $customer = $paymentData['customer'] ?? [];
        $custId = !empty($customer['id']) 
            ? 'CUST_' . $customer['id'] 
            : ('CUST_' . substr(md5($customer['email'] ?? microtime()), 0, 12));
        $custName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: 'Valued Customer';
        $custEmail = !empty($customer['email']) ? trim($customer['email']) : 'order@kamshemp.com';
        $custPhone = preg_replace('/[^0-9]/', '', $customer['phone'] ?? '') ?: '9876543210';
        if (strlen($custPhone) > 10) {
            $custPhone = substr($custPhone, -10);
        }

        // Determine protocol and host for return URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUri = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        $returnUrl = "{$protocol}{$host}{$baseUri}/api/cashfree_return.php?order_id={$orderNumber}";

        // If credentials are missing, generate simulation session
        if (empty($this->appId) || empty($this->secretKey)) {
            return [
                'success' => true,
                'provider' => $this->getIdentifier(),
                'transaction_id' => $transactionId,
                'order_number' => $orderNumber,
                'amount' => $amount,
                'currency' => $currency,
                'payment_session_id' => 'session_mock_' . bin2hex(random_bytes(10)),
                'environment' => $this->env,
                'payment_url' => $returnUrl . '&mock=1',
                'is_mock' => true
            ];
        }

        $payload = [
            'order_id' => $orderNumber,
            'order_amount' => round($amount, 2),
            'order_currency' => $currency,
            'customer_details' => [
                'customer_id' => $custId,
                'customer_name' => $custName,
                'customer_email' => $custEmail,
                'customer_phone' => $custPhone
            ],
            'order_meta' => [
                'return_url' => $returnUrl
            ],
            'order_note' => "Order #{$orderNumber} at KAMS HEMP"
        ];

        $response = $this->callApi('/orders', 'POST', $payload);

        if (!empty($response['payment_session_id'])) {
            return [
                'success' => true,
                'provider' => $this->getIdentifier(),
                'transaction_id' => $transactionId,
                'order_number' => $orderNumber,
                'amount' => $amount,
                'currency' => $currency,
                'payment_session_id' => $response['payment_session_id'],
                'cf_order_id' => $response['cf_order_id'] ?? $orderNumber,
                'order_status' => $response['order_status'] ?? 'ACTIVE',
                'environment' => $this->env,
                'raw' => $response
            ];
        }

        $errorMsg = $response['message'] ?? ($response['error'] ?? 'Cashfree Order creation failed.');
        return [
            'success' => false,
            'error' => $errorMsg,
            'raw' => $response
        ];
    }

    /**
     * Verify payment status server-side via Cashfree PG API
     */
    public function verifyPayment(string $transactionId, array $context = []): array {
        $orderNumber = $context['order_number'] ?? $transactionId;

        // If mock / demo mode
        if (!empty($context['mock']) || (empty($this->appId) && empty($this->secretKey))) {
            return [
                'verified' => true,
                'status' => 'SUCCESS',
                'provider_payment_id' => 'CF-SIM-' . time(),
                'amount' => (float)($context['amount'] ?? 0),
                'order_number' => $orderNumber
            ];
        }

        $response = $this->callApi('/orders/' . urlencode($orderNumber), 'GET');

        if (!empty($response['order_status'])) {
            $isPaid = strtoupper($response['order_status']) === 'PAID';
            $amount = (float)($response['order_amount'] ?? 0);
            return [
                'verified' => $isPaid,
                'status' => $isPaid ? 'SUCCESS' : ($response['order_status'] === 'EXPIRED' ? 'FAILED' : 'PENDING'),
                'provider_payment_id' => $response['cf_order_id'] ?? ('CF-' . $orderNumber),
                'amount' => $amount,
                'order_number' => $orderNumber,
                'raw' => $response
            ];
        }

        return [
            'verified' => false,
            'status' => 'FAILED',
            'error' => $response['message'] ?? 'Unable to verify order with Cashfree'
        ];
    }

    /**
     * Handle and verify Cashfree webhook callback
     */
    public function handleWebhook($rawPayload, array $headers): array {
        $data = is_array($rawPayload) ? $rawPayload : json_decode($rawPayload, true);
        if (!$data) {
            return ['verified' => false, 'error' => 'Invalid JSON payload'];
        }

        // Webhook signature verification if secret is provided
        $webhookSecret = trim(Settings::get('cashfree_webhook_secret', ''));
        if (!empty($webhookSecret)) {
            $timestamp = $headers['x-webhook-timestamp'] ?? ($headers['X-Webhook-Timestamp'] ?? '');
            $signature = $headers['x-webhook-signature'] ?? ($headers['X-Webhook-Signature'] ?? '');
            $rawBody = is_string($rawPayload) ? $rawPayload : json_encode($rawPayload);
            $computedSignature = base64_encode(hash_hmac('sha256', $timestamp . $rawBody, $webhookSecret, true));
            if (!hash_equals($signature, $computedSignature)) {
                return ['verified' => false, 'error' => 'Invalid Cashfree webhook signature'];
            }
        }

        $event = $data['type'] ?? ($data['event'] ?? '');
        $order = $data['data']['order'] ?? [];
        $payment = $data['data']['payment'] ?? [];

        $orderNumber = $order['order_id'] ?? ($data['order_id'] ?? '');
        $paymentStatus = strtoupper($payment['payment_status'] ?? ($data['payment_status'] ?? ''));
        $cfPaymentId = $payment['cf_payment_id'] ?? ($data['reference_id'] ?? ('CF-' . time()));
        $amount = (float)($payment['payment_amount'] ?? ($order['order_amount'] ?? 0));

        $isSuccess = in_array($paymentStatus, ['SUCCESS', 'PAID']) || ($event === 'PAYMENT_SUCCESS_WEBHOOK');

        // Look up transaction_id from DB by order_number
        $transactionId = $orderNumber;
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT transaction_id FROM payments WHERE order_number = ? LIMIT 1");
            $stmt->execute([$orderNumber]);
            $found = $stmt->fetch();
            if ($found) {
                $transactionId = $found['transaction_id'];
            }
        } catch (\Exception $e) {}

        return [
            'verified' => true,
            'status' => $isSuccess ? 'SUCCESS' : 'FAILED',
            'transaction_id' => $transactionId,
            'order_number' => $orderNumber,
            'provider_payment_id' => (string)$cfPaymentId,
            'amount' => $amount,
            'currency' => 'INR',
            'raw' => $data
        ];
    }

    /**
     * Internal cURL helper for Cashfree PG APIs
     */
    private function callApi(string $endpoint, string $method = 'GET', ?array $data = null): array {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        $headers = [
            'x-client-id: ' . $this->appId,
            'x-client-secret: ' . $this->secretKey,
            'x-api-version: ' . $this->apiVersion,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => 'cURL Error: ' . $err];
        }

        $decoded = json_decode($res, true);
        return is_array($decoded) ? $decoded : ['error' => 'Invalid JSON response from Cashfree: ' . $res];
    }
}
