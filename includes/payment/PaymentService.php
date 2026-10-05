<?php
// includes/payment/PaymentService.php
// Production-Ready Unified Payment & Order Service with Idempotency and Server-Side Validation

require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/../inventory.php';
require_once __DIR__ . '/../referral.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/PaymentProviderInterface.php';
require_once __DIR__ . '/NativeUPIEngine.php';
require_once __DIR__ . '/CashfreeEngine.php';

class PaymentService {

    /**
     * Get instance of configured payment provider
     */
    public static function getProvider(?string $providerName = null): PaymentProviderInterface {
        if (!$providerName) {
            $providerName = Settings::get('payment_gateway_provider', 'native_upi');
        }

        switch ($providerName) {
            case 'cashfree':
                return new CashfreeEngine();
            case 'native_upi':
            default:
                return new NativeUPIEngine();
        }
    }

    /**
     * Resolve product ID from item payload (by id, product_id, or name fallback)
     */
    public static function resolveProductId(array $item): int {
        $prodId = (int)($item['id'] ?? $item['product_id'] ?? 0);
        if ($prodId <= 0 && !empty($item['name'])) {
            try {
                $db = Database::getInstance();
                $stmt = $db->prepare("SELECT id FROM products WHERE name = ? OR name LIKE ? LIMIT 1");
                $stmt->execute([$item['name'], '%' . $item['name'] . '%']);
                $found = $stmt->fetch();
                if ($found) {
                    $prodId = (int)$found['id'];
                }
            } catch (Exception $e) {
                // Ignore fallback error
            }
        }
        return $prodId;
    }

    /**
     * Save customer address to `user_addresses` table if not already present.
     */
    public static function saveUserAddress(int $userId, array $customerData, bool $isDefault = false): void {
        try {
            $db = Database::getInstance();
            $street = trim($customerData['address'] ?? ($customerData['street'] ?? ''));
            $zip = trim($customerData['zip'] ?? ($customerData['postal_code'] ?? ''));
            $city = trim($customerData['city'] ?? '');
            $state = trim($customerData['state'] ?? '');
            $phone = trim($customerData['phone'] ?? '');
            $firstName = trim($customerData['first_name'] ?? '');
            $lastName = trim($customerData['last_name'] ?? '');
            $recipientName = trim("{$firstName} {$lastName}") ?: 'Customer';

            if (empty($street) || empty($city)) {
                return;
            }

            // Check if exact address already exists for user
            $chkAddr = $db->prepare("SELECT id FROM user_addresses WHERE user_id = ? AND street = ? AND zip = ? LIMIT 1");
            $chkAddr->execute([$userId, $street, $zip]);
            $existing = $chkAddr->fetch();

            if (!$existing) {
                // If user has no existing addresses, make it default
                $countStmt = $db->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
                $countStmt->execute([$userId]);
                $hasAddresses = (int)$countStmt->fetchColumn() > 0;
                $defaultFlag = (!$hasAddresses || $isDefault) ? 1 : 0;

                $insAddr = $db->prepare("
                    INSERT INTO user_addresses (user_id, title, name, phone, street, city, state, zip, country, is_default, created_at)
                    VALUES (?, 'Home', ?, ?, ?, ?, ?, ?, 'India', ?, NOW())
                ");
                $insAddr->execute([$userId, $recipientName, $phone, $street, $city, $state, $zip, $defaultFlag]);
            }
        } catch (\Throwable $e) {
            error_log("Failed to save user address: " . $e->getMessage());
        }
    }

    /**
     * Ensure a customer account exists in `users` table for the order.
     * If user does not exist, registers them automatically with active status,
     * generates a unique referral code, assigns a secure temporary password, logs them in,
     * and saves their shipping address.
     *
     * @param array $customerData Customer checkout details (name, email, phone, address, etc.)
     * @param string|null $appliedReferralCode Referral code used at checkout if any
     * @param int|null $currentUserId Currently logged-in user ID if any
     * @return array Array containing 'user_id', 'is_new', 'temp_password', 'referral_code'
     */
    public static function ensureCustomerAccount(array $customerData, ?string $appliedReferralCode = null, ?int $currentUserId = null): array {
        $db = Database::getInstance();
        $email = strtolower(trim($customerData['email'] ?? ''));
        $phone = trim($customerData['phone'] ?? '');
        $firstName = trim($customerData['first_name'] ?? '') ?: 'Customer';
        $lastName = trim($customerData['last_name'] ?? '');

        // 1. If user is already identified by ID
        if ($currentUserId && $currentUserId > 0) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$currentUserId]);
            $user = $stmt->fetch();
            if ($user) {
                if (empty($user['phone']) && !empty($phone)) {
                    $db->prepare("UPDATE users SET phone = ?, updated_at = NOW() WHERE id = ?")->execute([$phone, $currentUserId]);
                    $user['phone'] = $phone;
                }
                self::saveUserAddress($currentUserId, $customerData);
                return [
                    'user_id' => $currentUserId,
                    'is_new' => false,
                    'referral_code' => $user['referral_code'],
                    'temp_password' => null,
                    'user' => $user
                ];
            }
        }

        // 2. If no valid email provided, cannot auto-register
        if (empty($email)) {
            return [
                'user_id' => null,
                'is_new' => false,
                'referral_code' => null,
                'temp_password' => null,
                'user' => null
            ];
        }

        // 3. Check if account already exists with this email
        $stmtEmail = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmtEmail->execute([$email]);
        $existingUser = $stmtEmail->fetch();

        if ($existingUser) {
            $userId = (int)$existingUser['id'];

            if (empty($existingUser['phone']) && !empty($phone)) {
                $db->prepare("UPDATE users SET phone = ?, updated_at = NOW() WHERE id = ?")->execute([$phone, $userId]);
                $existingUser['phone'] = $phone;
            }

            self::saveUserAddress($userId, $customerData);

            // Log customer into session if not already logged in
            if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['user_logged_in'])) {
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $existingUser['email'];
                $_SESSION['user_name'] = trim(($existingUser['first_name'] ?? '') . ' ' . ($existingUser['last_name'] ?? ''));
                $_SESSION['user'] = $existingUser;
            }

            return [
                'user_id' => $userId,
                'is_new' => false,
                'referral_code' => $existingUser['referral_code'],
                'temp_password' => null,
                'user' => $existingUser
            ];
        }

        // 4. NEW USER: Auto-register into `users` table
        require_once __DIR__ . '/../referral.php';
        $newReferralCode = ReferralSystem::generateCode($firstName);

        // Friendly temporary password (e.g. Kams + last 4 digits of phone, or 4 random digits)
        $cleanDigits = preg_replace('/\D/', '', $phone);
        $phoneSuffix = strlen($cleanDigits) >= 4 ? substr($cleanDigits, -4) : (string)random_int(1000, 9999);
        $plainPassword = 'Kams' . $phoneSuffix;
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

        // Resolve referral linkage if code applied
        $referredBy = null;
        $hasUsedReferral = 0;
        if (!empty($appliedReferralCode)) {
            $cleanRefCode = strtoupper(trim($appliedReferralCode));
            $refCheck = $db->prepare("SELECT id FROM users WHERE referral_code = ? AND status = 'Active' LIMIT 1");
            $refCheck->execute([$cleanRefCode]);
            $refRow = $refCheck->fetch();
            if ($refRow) {
                $referredBy = (int)$refRow['id'];
                $hasUsedReferral = 1;
            }
        }

        $insUser = $db->prepare("
            INSERT INTO users (
                first_name, last_name, email, phone, password_hash, referral_code,
                referred_by_user_id, has_used_referral, wallet_balance, is_verified, status,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, 0.00, 1, 'Active',
                NOW(), NOW()
            )
        ");
        $insUser->execute([
            $firstName,
            $lastName,
            $email,
            $phone,
            $passwordHash,
            $newReferralCode,
            $referredBy,
            $hasUsedReferral
        ]);

        $newUserId = (int)$db->lastInsertId();

        // If referred by another user, record initial pending referral
        if ($referredBy) {
            try {
                $refRecord = $db->prepare("
                    INSERT INTO referrals (referrer_id, referee_id, referral_code, status, created_at)
                    VALUES (?, ?, ?, 'Pending', NOW())
                ");
                $refRecord->execute([$referredBy, $newUserId, strtoupper(trim($appliedReferralCode))]);
            } catch (\Throwable $refEx) {
                error_log("Referral record creation error: " . $refEx->getMessage());
            }
        }

        // Save address as default
        self::saveUserAddress($newUserId, $customerData, true);

        // Establish session so user is logged in
        $newUserData = [
            'id' => $newUserId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'referral_code' => $newReferralCode,
            'wallet_balance' => 0.00,
            'is_verified' => 1,
            'status' => 'Active'
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = trim("{$firstName} {$lastName}");
            $_SESSION['user'] = $newUserData;
            $_SESSION['new_account_created'] = [
                'user_id' => $newUserId,
                'email' => $email,
                'temp_password' => $plainPassword,
                'referral_code' => $newReferralCode
            ];
        }

        return [
            'user_id' => $newUserId,
            'is_new' => true,
            'referral_code' => $newReferralCode,
            'temp_password' => $plainPassword,
            'user' => $newUserData
        ];
    }

    /**
     * Calculate checkout totals strictly server-side using current DB prices
     */
    public static function calculateTotals(array $cartItems, ?string $referralCode = null, bool $applyWallet = false, ?int $userId = null): array {
        $db = Database::getInstance();
        $subtotal = 0.00;
        $reconciledItems = [];

        foreach ($cartItems as $item) {
            $prodId = self::resolveProductId($item);
            $qty = max(1, (int)($item['qty'] ?? 1));

            // Fetch live product data from database
            $dbProd = $prodId > 0 ? Inventory::getProduct($prodId) : null;
            if ($dbProd) {
                $prodId = (int)$dbProd['id'];
                $unitPrice = (float)$dbProd['price'];
                $productName = $dbProd['name'];
                $image = $dbProd['primary_image'] ?? ($item['image'] ?? ($item['img'] ?? ''));
            } else {
                $unitPrice = (float)($item['price'] ?? 0);
                $productName = $item['name'] ?? 'Product';
                $image = $item['image'] ?? ($item['img'] ?? '');
            }

            $lineTotal = round($unitPrice * $qty, 2);
            $subtotal += $lineTotal;

            $reconciledItems[] = [
                'id' => $prodId,
                'product_id' => $prodId,
                'name' => $productName,
                'price' => $unitPrice,
                'qty' => $qty,
                'subtotal' => $lineTotal,
                'image' => $image,
                'img' => $image
            ];
        }

        // Referral Discount
        $discountAmount = 0.00;
        $validReferralCode = null;
        if (!empty($referralCode) && $subtotal > 0) {
            $val = ReferralSystem::validateReferralCode($referralCode, $userId);
            if (!empty($val['valid'])) {
                $validReferralCode = strtoupper(trim($referralCode));
                $discountPercent = (float)($val['discount_percent'] ?? Settings::get('referral_discount_percent', 10));
                $discountAmount = round($subtotal * ($discountPercent / 100), 2);
            }
        }

        $postDiscountSubtotal = max(0, $subtotal - $discountAmount);

        // Wallet Deduction (Capped by max percentage in settings, e.g. 50%)
        $walletDeduction = 0.00;
        $walletBalance = 0.00;
        if ($applyWallet && $userId) {
            $walletBalance = ReferralSystem::getWalletBalance($userId);
            if ($walletBalance > 0 && $postDiscountSubtotal > 0) {
                $maxCapPercent = (float)Settings::get('max_wallet_usage_percent', 50);
                $maxAllowedUsage = round($postDiscountSubtotal * ($maxCapPercent / 100), 2);
                $walletDeduction = min($walletBalance, $maxAllowedUsage, $postDiscountSubtotal);
            }
        }

        $postWalletSubtotal = max(0, $postDiscountSubtotal - $walletDeduction);

        // Shipping and Tax
        $freeShippingThreshold = (float)Settings::get('free_shipping_threshold', 2000);
        $standardShippingFee = (float)Settings::get('standard_delivery_fee', 150);
        $taxRate = (float)Settings::get('tax_rate', 18);

        $shippingFee = ($postWalletSubtotal >= $freeShippingThreshold || $subtotal == 0) ? 0.00 : $standardShippingFee;
        $taxAmount = round($postWalletSubtotal * ($taxRate / 100), 2);
        $finalTotal = round($postWalletSubtotal + $shippingFee + $taxAmount, 2);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'referral_code' => $validReferralCode,
            'wallet_deduction' => $walletDeduction,
            'wallet_balance' => $walletBalance,
            'shipping_fee' => $shippingFee,
            'tax_amount' => $taxAmount,
            'total_amount' => $finalTotal,
            'items' => $reconciledItems
        ];
    }

    /**
     * Create Cash on Delivery (COD) Order immediately
     */
    public static function createCodOrder(array $customerData, array $cartItems, ?string $referralCode = null, bool $applyWallet = false, ?int $userId = null): array {
        $db = Database::getInstance();

        // Check stock availability first
        foreach ($cartItems as $item) {
            $prodId = self::resolveProductId($item);
            $qty = (int)($item['qty'] ?? 1);
            $pName = $item['name'] ?? ($prodId > 0 ? "Item #$prodId" : "Selected item");
            $stockCheck = Inventory::checkStock($prodId, $qty, $item['name'] ?? null);
            if (is_array($stockCheck)) {
                if (empty($stockCheck['available'])) {
                    $msg = !empty($stockCheck['message']) ? $stockCheck['message'] : "Product '{$pName}' is currently out of stock or insufficient quantity available.";
                    throw new Exception($msg);
                }
            } elseif (!$stockCheck) {
                throw new Exception("Product '{$pName}' is currently out of stock or insufficient quantity available.");
            }
        }

        // Calculate verified server totals
        $calc = self::calculateTotals($cartItems, $referralCode, $applyWallet, $userId);
        $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad((string)random_int(1000, 999999), 6, '0', STR_PAD_LEFT);

        $db->beginTransaction();
        try {
            $firstName = trim($customerData['first_name'] ?? '');
            $lastName = trim($customerData['last_name'] ?? '');
            $email = trim($customerData['email'] ?? '');
            $phone = trim($customerData['phone'] ?? '');
            $street = trim($customerData['address'] ?? '');
            $city = trim($customerData['city'] ?? '');
            $state = trim($customerData['state'] ?? '');
            $zip = trim($customerData['zip'] ?? '');
            $fullAddress = "{$street}, {$city}, {$state} - {$zip}";
            $notes = trim($customerData['notes'] ?? 'Order placed via Cash on Delivery');

            // Insert into orders table
            $stmt = $db->prepare("
                INSERT INTO orders (
                    order_number, transaction_id, user_id, first_name, last_name, email, phone,
                    shipping_address, billing_address, subtotal, discount_amount, referral_code,
                    wallet_deduction, shipping_fee, tax_amount, total_amount,
                    order_status, payment_status, payment_method, notes, created_at, updated_at
                ) VALUES (
                    ?, NULL, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    'Pending', 'Pending', 'COD', ?, NOW(), NOW()
                )
            ");

            $stmt->execute([
                $orderNumber,
                $userId,
                $firstName,
                $lastName,
                $email,
                $phone,
                $fullAddress,
                $fullAddress,
                $calc['subtotal'],
                $calc['discount_amount'],
                $calc['referral_code'],
                $calc['wallet_deduction'],
                $calc['shipping_fee'],
                $calc['tax_amount'],
                $calc['total_amount'],
                $notes
            ]);

            $orderId = (int)$db->lastInsertId();

            // Insert into order_items and atomically deduct stock
            $itemStmt = $db->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, price, quantity, subtotal, image_url, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            foreach ($calc['items'] as $item) {
                $itemProdId = self::resolveProductId($item);
                $pName = $item['name'] ?? 'Product';
                $itemImg = $item['image'] ?? ($item['img'] ?? '');

                $itemStmt->execute([
                    $orderId,
                    $itemProdId,
                    $pName,
                    $item['price'],
                    $item['qty'],
                    $item['subtotal'],
                    $itemImg
                ]);

                // Deduct stock atomically
                $deducted = Inventory::deductStock($itemProdId, $item['qty'], $orderId, 'order_placed', "COD Order $orderNumber", $pName);
                if (!$deducted) {
                    throw new Exception("Stock deduction failed for product '{$pName}'");
                }
            }

            // Debit wallet if used
            if ($calc['wallet_deduction'] > 0 && $userId) {
                ReferralSystem::debitWallet($userId, $calc['wallet_deduction'], "Deduction for COD Order $orderNumber", $orderId);
            }

            // Record and process referral reward
            if (!empty($calc['referral_code']) && $userId) {
                ReferralSystem::processReferralReward($userId, $calc['subtotal'], $orderId);
            }

            // Save address for user if not already saved
            if ($userId) {
                self::saveUserAddress($userId, $customerData);
            }

            $db->commit();

            // Send confirmation email
            Mailer::sendOrderConfirmation($email, [
                'order_number' => $orderNumber,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'total' => $calc['total_amount'],
                'total_amount' => $calc['total_amount'],
                'cart' => $calc['items'],
                'shipping_address' => $fullAddress,
                'payment_method' => 'Cash on Delivery',
                'temp_password' => $customerData['temp_password'] ?? null,
                'referral_code' => $customerData['referral_code'] ?? ($calc['referral_code'] ?? null)
            ]);

            return [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'amount' => $calc['total_amount'],
                'calc' => $calc
            ];

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Create a Pending UPI Payment Transaction Session
     */
    public static function createUpiSession(array $customerData, array $cartItems, ?string $referralCode = null, bool $applyWallet = false, ?int $userId = null): array {
        $db = Database::getInstance();

        // Check stock availability first
        foreach ($cartItems as $item) {
            $prodId = self::resolveProductId($item);
            $qty = (int)($item['qty'] ?? 1);
            $pName = $item['name'] ?? ($prodId > 0 ? "Item #$prodId" : "Selected item");
            $stockCheck = Inventory::checkStock($prodId, $qty, $item['name'] ?? null);
            if (is_array($stockCheck)) {
                if (empty($stockCheck['available'])) {
                    $msg = !empty($stockCheck['message']) ? $stockCheck['message'] : "Product '{$pName}' is currently out of stock or insufficient quantity available.";
                    throw new Exception($msg);
                }
            } elseif (!$stockCheck) {
                throw new Exception("Product '{$pName}' is currently out of stock or insufficient quantity available.");
            }
        }

        // Calculate verified server totals
        $calc = self::calculateTotals($cartItems, $referralCode, $applyWallet, $userId);
        $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad((string)random_int(1000, 999999), 6, '0', STR_PAD_LEFT);
        $transactionId = 'TXN-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));

        $provider = self::getProvider();
        $paymentData = [
            'order_number' => $orderNumber,
            'transaction_id' => $transactionId,
            'amount' => $calc['total_amount'],
            'currency' => 'INR'
        ];
        $providerResult = $provider->createPayment($paymentData);

        // Store checkout payload so that order can be confirmed automatically upon verified webhook
        $checkoutPayload = json_encode([
            'customer' => $customerData,
            'cart_items' => $cartItems,
            'calc' => $calc,
            'referral_code' => $referralCode,
            'apply_wallet' => $applyWallet,
            'user_id' => $userId
        ]);

        $upiId = $providerResult['upi_id'] ?? Settings::get('upi_id', 'kamshemp@upi');

        $stmt = $db->prepare("
            INSERT INTO payments (
                order_id, order_number, transaction_id, provider, amount, currency,
                upi_id, payment_method, status, checkout_payload, created_at, updated_at
            ) VALUES (
                NULL, ?, ?, ?, ?, 'INR',
                ?, 'UPI', 'PENDING', ?, NOW(), NOW()
            )
        ");

        $stmt->execute([
            $orderNumber,
            $transactionId,
            $provider->getIdentifier(),
            $calc['total_amount'],
            $upiId,
            $checkoutPayload
        ]);

        $paymentId = (int)$db->lastInsertId();

        return array_merge($providerResult, [
            'payment_id' => $paymentId,
            'order_number' => $orderNumber,
            'transaction_id' => $transactionId,
            'amount' => $calc['total_amount'],
            'calc' => $calc
        ]);
    }

    /**
     * Create a Pending Cashfree PG Order Session
     */
    public static function createCashfreeSession(array $customerData, array $cartItems, ?string $referralCode = null, bool $applyWallet = false, ?int $userId = null): array {
        $db = Database::getInstance();

        // Check stock availability
        foreach ($cartItems as $item) {
            $prodId = self::resolveProductId($item);
            $qty = (int)($item['qty'] ?? 1);
            $pName = $item['name'] ?? ($prodId > 0 ? "Item #$prodId" : "Selected item");
            $stockCheck = Inventory::checkStock($prodId, $qty, $item['name'] ?? null);
            if (is_array($stockCheck)) {
                if (empty($stockCheck['available'])) {
                    $msg = !empty($stockCheck['message']) ? $stockCheck['message'] : "Product '{$pName}' is currently out of stock or insufficient quantity available.";
                    throw new Exception($msg);
                }
            } elseif (!$stockCheck) {
                throw new Exception("Product '{$pName}' is currently out of stock or insufficient quantity available.");
            }
        }

        // Calculate verified server totals
        $calc = self::calculateTotals($cartItems, $referralCode, $applyWallet, $userId);
        $orderNumber = 'CF-' . date('Ymd') . '-' . str_pad((string)random_int(1000, 999999), 6, '0', STR_PAD_LEFT);
        $transactionId = 'TXN-CF-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));

        $provider = new CashfreeEngine();
        $paymentData = [
            'order_number' => $orderNumber,
            'transaction_id' => $transactionId,
            'amount' => $calc['total_amount'],
            'currency' => 'INR',
            'customer' => $customerData
        ];
        $providerResult = $provider->createPayment($paymentData);

        if (empty($providerResult['success'])) {
            throw new Exception($providerResult['error'] ?? 'Cashfree gateway session initiation failed.');
        }

        $checkoutPayload = json_encode([
            'customer' => $customerData,
            'cart_items' => $cartItems,
            'calc' => $calc,
            'referral_code' => $referralCode,
            'apply_wallet' => $applyWallet,
            'user_id' => $userId
        ]);

        $stmt = $db->prepare("
            INSERT INTO payments (
                order_id, order_number, transaction_id, provider, amount, currency,
                upi_id, payment_method, status, checkout_payload, created_at, updated_at
            ) VALUES (
                NULL, ?, ?, 'cashfree', ?, 'INR',
                NULL, 'Cashfree PG', 'PENDING', ?, NOW(), NOW()
            )
        ");

        $stmt->execute([
            $orderNumber,
            $transactionId,
            $calc['total_amount'],
            $checkoutPayload
        ]);

        $paymentId = (int)$db->lastInsertId();

        return array_merge($providerResult, [
            'payment_id' => $paymentId,
            'order_number' => $orderNumber,
            'transaction_id' => $transactionId,
            'amount' => $calc['total_amount'],
            'calc' => $calc
        ]);
    }

    /**
     * Check payment status by transaction ID
     */
    public static function getPaymentStatus(string $transactionId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM payments WHERE transaction_id = ? LIMIT 1");
        $stmt->execute([$transactionId]);
        $payment = $stmt->fetch();
        return $payment ?: null;
    }

    /**
     * Confirm a pending UPI Payment and create confirmed Order (IDEMPOTENT)
     */
    public static function confirmUpiPayment(string $transactionId, string $providerPaymentId, float $paidAmount, array $webhookPayload = []): array {
        $db = Database::getInstance();

        // 1. Fetch payment record
        $stmt = $db->prepare("SELECT * FROM payments WHERE transaction_id = ? FOR UPDATE");
        // We'll wrap in transaction for atomic lock
        $db->beginTransaction();

        try {
            $stmt->execute([$transactionId]);
            $payment = $stmt->fetch();

            if (!$payment) {
                $db->rollBack();
                return ['success' => false, 'error' => "Transaction '{$transactionId}' not found."];
            }

            // 2. IDEMPOTENCY CHECK: If already confirmed/successful, return existing order details
            if ($payment['status'] === 'SUCCESS' && !empty($payment['order_id'])) {
                $db->rollBack();
                return [
                    'success' => true,
                    'already_confirmed' => true,
                    'order_id' => (int)$payment['order_id'],
                    'order_number' => $payment['order_number'],
                    'transaction_id' => $payment['transaction_id'],
                    'amount' => (float)$payment['amount']
                ];
            }

            // 3. AMOUNT VERIFICATION: Server order amount must match provider paid amount
            $expectedAmount = (float)$payment['amount'];
            if (abs($expectedAmount - $paidAmount) > 0.01) {
                $reason = "Amount mismatch: expected ₹{$expectedAmount}, got ₹{$paidAmount}";
                $failStmt = $db->prepare("UPDATE payments SET status = 'FAILED', failure_reason = ?, updated_at = NOW() WHERE id = ?");
                $failStmt->execute([$reason, $payment['id']]);
                $db->commit();
                return ['success' => false, 'error' => $reason];
            }

            // 4. Decode checkout payload
            $payload = json_decode($payment['checkout_payload'], true);
            if (!$payload || !isset($payload['customer']) || !isset($payload['calc'])) {
                $db->rollBack();
                return ['success' => false, 'error' => "Corrupted checkout payload for transaction '{$transactionId}'."];
            }

            $customerData = $payload['customer'];
            $calc = $payload['calc'];
            $userId = !empty($payload['user_id']) ? (int)$payload['user_id'] : null;
            if (!$userId && !empty($customerData['email'])) {
                $acc = self::ensureCustomerAccount($customerData, $calc['referral_code'] ?? null);
                $userId = $acc['user_id'];
                if (!empty($acc['temp_password'])) {
                    $customerData['temp_password'] = $acc['temp_password'];
                    $customerData['referral_code'] = $acc['referral_code'];
                }
            }
            $orderNumber = $payment['order_number'];

            $firstName = trim($customerData['first_name'] ?? '');
            $lastName = trim($customerData['last_name'] ?? '');
            $email = trim($customerData['email'] ?? '');
            $phone = trim($customerData['phone'] ?? '');
            $street = trim($customerData['address'] ?? '');
            $city = trim($customerData['city'] ?? '');
            $state = trim($customerData['state'] ?? '');
            $zip = trim($customerData['zip'] ?? '');
            $fullAddress = "{$street}, {$city}, {$state} - {$zip}";
            $notes = trim($customerData['notes'] ?? 'Order placed via UPI / Scan & Pay');

            // 5. Create Confirmed Order in orders table
            $insOrder = $db->prepare("
                INSERT INTO orders (
                    order_number, transaction_id, user_id, first_name, last_name, email, phone,
                    shipping_address, billing_address, subtotal, discount_amount, referral_code,
                    wallet_deduction, shipping_fee, tax_amount, total_amount,
                    order_status, payment_status, payment_method, notes, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    'Confirmed', 'Paid', 'UPI', ?, NOW(), NOW()
                )
            ");

            $insOrder->execute([
                $orderNumber,
                $transactionId,
                $userId,
                $firstName,
                $lastName,
                $email,
                $phone,
                $fullAddress,
                $fullAddress,
                $calc['subtotal'],
                $calc['discount_amount'],
                $calc['referral_code'],
                $calc['wallet_deduction'],
                $calc['shipping_fee'],
                $calc['tax_amount'],
                $calc['total_amount'],
                $notes
            ]);

            $orderId = (int)$db->lastInsertId();

            // 6. Insert order items & atomically deduct inventory
            $itemStmt = $db->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, price, quantity, subtotal, image_url, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            foreach ($calc['items'] as $item) {
                $itemProdId = self::resolveProductId($item);
                $pName = $item['name'] ?? 'Product';
                $itemImg = $item['image'] ?? ($item['img'] ?? '');

                $itemStmt->execute([
                    $orderId,
                    $itemProdId,
                    $pName,
                    $item['price'],
                    $item['qty'],
                    $item['subtotal'],
                    $itemImg
                ]);

                // Deduct stock atomically
                Inventory::deductStock($itemProdId, $item['qty'], $orderId, 'order_placed', "UPI Order $orderNumber", $pName);
            }

            // 7. Debit wallet if used
            if ($calc['wallet_deduction'] > 0 && $userId) {
                ReferralSystem::debitWallet($userId, $calc['wallet_deduction'], "Deduction for UPI Order $orderNumber", $orderId);
            }

            // 8. Process referral reward if used
            if (!empty($calc['referral_code']) && $userId) {
                ReferralSystem::processReferralReward($userId, $calc['subtotal'], $orderId);
            }

            // 9. Save address for user if not already saved
            if ($userId) {
                self::saveUserAddress($userId, $customerData);
            }

            // 10. Update payments table to SUCCESS
            $upPay = $db->prepare("
                UPDATE payments SET
                    order_id = ?,
                    provider_payment_id = ?,
                    status = 'SUCCESS',
                    webhook_status = 'PROCESSED',
                    webhook_payload = ?,
                    paid_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upPay->execute([
                $orderId,
                $providerPaymentId,
                json_encode($webhookPayload),
                $payment['id']
            ]);

            $db->commit();

            // 11. Send order confirmation email
            Mailer::sendOrderConfirmation($email, [
                'order_number' => $orderNumber,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'total' => $calc['total_amount'],
                'total_amount' => $calc['total_amount'],
                'cart' => $calc['items'],
                'shipping_address' => $fullAddress,
                'payment_method' => $payment['payment_method'] ?? 'UPI / Online',
                'temp_password' => $customerData['temp_password'] ?? null,
                'referral_code' => $customerData['referral_code'] ?? ($calc['referral_code'] ?? null)
            ]);

            return [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'transaction_id' => $transactionId,
                'amount' => $calc['total_amount']
            ];

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => "Payment confirmation failed: " . $e->getMessage()];
        }
    }
}
