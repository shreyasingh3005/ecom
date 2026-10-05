<?php
// includes/referral.php
// Referral, Commission, and Wallet System Service

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';

class ReferralSystem {
    /**
     * Generate unique referral code for a new user
     */
    public static function generateCode($firstName) {
        $db = getDB();
        $base = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $firstName ?: 'USER'), 0, 4));
        if (strlen($base) < 4) {
            $base = str_pad($base, 4, 'X');
        }

        do {
            $code = $base . rand(1000, 9999);
            $stmt = $db->prepare("SELECT `id` FROM `users` WHERE `referral_code` = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetch());

        return $code;
    }

    /**
     * Alias for generateCode to maintain backwards compatibility
     */
    public static function generateUniqueCode($firstName) {
        return self::generateCode($firstName);
    }

    /**
     * Validate referral code for checkout or cart
     */
    public static function validateCode($code, $currentUserId = null, $currentUserEmail = null) {
        $code = strtoupper(trim((string)$code));
        if (empty($code)) {
            return ['valid' => false, 'message' => 'Please enter a referral code.'];
        }

        $db = getDB();

        // 1. Check if referral code exists
        $stmt = $db->prepare("SELECT * FROM `users` WHERE `referral_code` = ? AND `status` = 'Active' LIMIT 1");
        $stmt->execute([$code]);
        $referrer = $stmt->fetch();

        if (!$referrer) {
            return ['valid' => false, 'message' => 'Invalid referral code.'];
        }

        // 2. Prevent Self-Referral
        if ($currentUserId && (int)$referrer['id'] === (int)$currentUserId) {
            return ['valid' => false, 'message' => 'You cannot use your own referral code.'];
        }
        if ($currentUserEmail && strtolower(trim($referrer['email'])) === strtolower(trim($currentUserEmail))) {
            return ['valid' => false, 'message' => 'You cannot use your own referral code.'];
        }

        // 3. Prevent Multiple Referral Discounts for the same customer
        if ($currentUserId) {
            $checkUsed = $db->prepare("SELECT `has_used_referral` FROM `users` WHERE `id` = ?");
            $checkUsed->execute([(int)$currentUserId]);
            $userRow = $checkUsed->fetch();
            if ($userRow && !empty($userRow['has_used_referral'])) {
                return ['valid' => false, 'message' => 'You have already used a referral discount on your account.'];
            }
        } elseif ($currentUserEmail) {
            $checkUsed = $db->prepare("SELECT `has_used_referral` FROM `users` WHERE `email` = ?");
            $checkUsed->execute([trim(strtolower($currentUserEmail))]);
            $userRow = $checkUsed->fetch();
            if ($userRow && !empty($userRow['has_used_referral'])) {
                return ['valid' => false, 'message' => 'This account has already redeemed a referral discount.'];
            }
        }

        $discountPercent = (float)get_setting('referral_discount_percent', 10);
        return [
            'valid' => true,
            'referrer' => $referrer,
            'discount_percent' => $discountPercent,
            'message' => "Referral code applied! You get {$discountPercent}% off your order."
        ];
    }

    public static function validateReferralCode($code, $currentUserId = null, $currentUserEmail = null) {
        return self::validateCode($code, $currentUserId, $currentUserEmail);
    }

    public static function getWalletBalance($userId) {
        $db = getDB();
        $stmt = $db->prepare("SELECT `wallet_balance` FROM `users` WHERE `id` = ?");
        $stmt->execute([(int)$userId]);
        return (float)($stmt->fetchColumn() ?: 0.00);
    }

    /**
     * Calculate referee discount
     */
    public static function calculateDiscount($subtotal, $discountPercent) {
        $subtotal = max(0, (float)$subtotal);
        $percent = max(0, (float)$discountPercent);
        return round($subtotal * ($percent / 100), 2);
    }

    /**
     * Calculate allowable wallet deduction based on configured rule (e.g. max 50%)
     */
    public static function calculateAllowedWalletUsage($payableAmount, $walletBalance) {
        $payableAmount = max(0, (float)$payableAmount);
        $walletBalance = max(0, (float)$walletBalance);
        if ($walletBalance <= 0 || $payableAmount <= 0) {
            return 0.00;
        }

        $maxUsagePercent = (float)get_setting('max_wallet_usage_percent', 50);
        $maxAllowed = round($payableAmount * ($maxUsagePercent / 100), 2);

        // Cannot exceed available wallet balance, and cannot exceed payable amount
        return min($walletBalance, $maxAllowed, $payableAmount);
    }

    /**
     * Credit wallet balance and record ledger transaction
     */
    public static function creditWallet($userId, $amount, $source = 'system', $referenceId = null, $description = null) {
        $amount = (float)$amount;
        if ($amount <= 0) return false;

        // Flexible argument handling if description passed as 3rd parameter
        if ($referenceId === null && $description === null) {
            $description = $source;
            $source = 'system';
            $referenceId = 'SYS-' . time();
        } elseif ($description === null) {
            $description = $referenceId;
            $referenceId = 'SYS-' . time();
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT `wallet_balance` FROM `users` WHERE `id` = ? FOR UPDATE");
        $stmt->execute([(int)$userId]);
        $row = $stmt->fetch();
        if (!$row) return false;

        $currentBalance = (float)$row['wallet_balance'];
        $newBalance = $currentBalance + $amount;

        $update = $db->prepare("UPDATE `users` SET `wallet_balance` = ? WHERE `id` = ?");
        $update->execute([$newBalance, (int)$userId]);

        $tx = $db->prepare("INSERT INTO `wallet_transactions` 
            (`user_id`, `type`, `amount`, `balance_after`, `reference_id`, `source`, `description`, `status`)
            VALUES (?, 'Credit', ?, ?, ?, ?, ?, 'Completed')");
        $tx->execute([(int)$userId, $amount, $newBalance, (string)$referenceId, (string)$source, (string)$description]);

        return $newBalance;
    }

    /**
     * Debit wallet balance and record ledger transaction
     */
    public static function debitWallet($userId, $amount, $referenceId, $description) {
        $amount = (float)$amount;
        if ($amount <= 0) return false;

        $db = getDB();
        $stmt = $db->prepare("SELECT `wallet_balance` FROM `users` WHERE `id` = ? FOR UPDATE");
        $stmt->execute([(int)$userId]);
        $row = $stmt->fetch();
        if (!$row) return false;

        $currentBalance = (float)$row['wallet_balance'];
        if ($currentBalance < $amount) {
            return false; // Insufficient wallet balance
        }

        $newBalance = max(0, $currentBalance - $amount);

        $update = $db->prepare("UPDATE `users` SET `wallet_balance` = ? WHERE `id` = ?");
        $update->execute([$newBalance, (int)$userId]);

        $tx = $db->prepare("INSERT INTO `wallet_transactions` 
            (`user_id`, `type`, `amount`, `balance_after`, `reference_id`, `source`, `description`, `status`)
            VALUES (?, 'Debit', ?, ?, ?, 'order_payment', ?, 'Completed')");
        $tx->execute([(int)$userId, $amount, $newBalance, (string)$referenceId, (string)$description]);

        return $newBalance;
    }

    /**
     * Process referral reward for the referrer upon order placement
     */
    public static function processReferralReward($arg1, $arg2, $arg3, $arg4 = null) {
        $db = getDB();
        if ($arg4 === null) {
            // 3 arguments passed: refereeId, orderSubtotal, orderId
            $refereeId = (int)$arg1;
            $orderSubtotal = (float)$arg2;
            $orderId = (int)$arg3;
            $stmt = $db->prepare("SELECT referred_by_user_id FROM users WHERE id = ?");
            $stmt->execute([$refereeId]);
            $referrerId = (int)$stmt->fetchColumn();
            if (!$referrerId) return 0;
        } else {
            $referrerId = (int)$arg1;
            $refereeId = $arg2 ? (int)$arg2 : null;
            $orderId = (int)$arg3;
            $orderSubtotal = (float)$arg4;
        }

        $rewardPercent = (float)get_setting('referrer_reward_percent', 10);
        $rewardAmount = round((float)$orderSubtotal * ($rewardPercent / 100), 2);

        if ($rewardAmount <= 0) return 0;

        // Check if referral row already exists for this referee & referrer
        $checkRef = $db->prepare("SELECT id FROM referrals WHERE referrer_id = ? AND referee_id = ? AND status = 'Pending' LIMIT 1");
        $checkRef->execute([(int)$referrerId, (int)$refereeId]);
        $existingRefId = $checkRef->fetchColumn();

        if ($existingRefId) {
            $upRef = $db->prepare("UPDATE referrals SET order_id = ?, reward_amount = ?, status = 'Completed', completed_at = NOW() WHERE id = ?");
            $upRef->execute([(int)$orderId, $rewardAmount, (int)$existingRefId]);
        } else {
            $refStmt = $db->prepare("INSERT INTO `referrals` 
                (`referrer_id`, `referee_id`, `referral_code`, `referee_discount_percent`, `referrer_reward_percent`, `order_id`, `reward_amount`, `status`, `completed_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Completed', NOW())");
            
            $refCode = $db->query("SELECT `referral_code` FROM `users` WHERE `id` = " . (int)$referrerId)->fetchColumn() ?: 'REF';
            $refStmt->execute([
                (int)$referrerId,
                $refereeId ? (int)$refereeId : null,
                $refCode,
                (float)get_setting('referral_discount_percent', 10),
                $rewardPercent,
                (int)$orderId,
                $rewardAmount
            ]);
        }

        // Credit referrer's wallet
        self::creditWallet(
            $referrerId,
            $rewardAmount,
            'referral_reward',
            "ORD-{$orderId}",
            "Referral cashback reward from order #{$orderId}"
        );

        // Mark referee as having used referral
        if ($refereeId) {
            $db->prepare("UPDATE `users` SET `has_used_referral` = 1 WHERE `id` = ?")->execute([(int)$refereeId]);
        }

        return $rewardAmount;
    }

    /**
     * Get user referral dashboard data
     */
    public static function getUserReferralStats($userId) {
        $db = getDB();
        $userStmt = $db->prepare("SELECT `referral_code`, `wallet_balance` FROM `users` WHERE `id` = ?");
        $userStmt->execute([(int)$userId]);
        $user = $userStmt->fetch();

        if (!$user) return null;

        $countStmt = $db->prepare("SELECT 
            COUNT(*) as total_referrals,
            SUM(CASE WHEN `status` = 'Completed' THEN 1 ELSE 0 END) as successful_referrals,
            COALESCE(SUM(`reward_amount`), 0) as total_earnings
            FROM `referrals` WHERE `referrer_id` = ?");
        $countStmt->execute([(int)$userId]);
        $stats = $countStmt->fetch();

        // Recent referral history
        $histStmt = $db->prepare("SELECT r.*, u.first_name, u.email 
                                  FROM `referrals` r 
                                  LEFT JOIN `users` u ON r.referee_id = u.id 
                                  WHERE r.referrer_id = ? 
                                  ORDER BY r.created_at DESC LIMIT 10");
        $histStmt->execute([(int)$userId]);
        $history = $histStmt->fetchAll();

        return [
            'referral_code' => $user['referral_code'],
            'wallet_balance' => (float)$user['wallet_balance'],
            'total_referrals' => (int)$stats['total_referrals'],
            'successful_referrals' => (int)$stats['successful_referrals'],
            'total_earnings' => (float)$stats['total_earnings'],
            'history' => $history
        ];
    }
}
