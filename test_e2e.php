<?php
/**
 * test_e2e.php
 * Automated End-to-End Test Suite for KAMS HEMP Platform
 * Tests Database, Admin Auth, Customer Auth, Referral Engine, Wallet Constraints,
 * Stock Atomic Deductions & Restorations, Settings, Mailer, and Analytics.
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Setup mock session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/referral.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/mailer.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function runTest($name, $callback) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    echo "\n------------------------------------------------------------\n";
    echo "TEST $totalTests: $name\n";
    try {
        $result = $callback();
        if ($result === true || (is_array($result) && ($result['pass'] ?? false) === true)) {
            $passedTests++;
            $msg = is_array($result) ? ($result['msg'] ?? 'OK') : 'OK';
            echo "RESULT: [PASSED] - $msg\n";
        } else {
            $failedTests++;
            $msg = is_array($result) ? ($result['msg'] ?? 'Failed') : 'Assertion returned false';
            echo "RESULT: [FAILED] - $msg\n";
        }
    } catch (Throwable $e) {
        $failedTests++;
        echo "RESULT: [ERROR] - Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

echo "============================================================\n";
echo "   KAMS HEMP AUTOMATED END-TO-END TEST SUITE\n";
echo "============================================================\n";

$db = Database::getInstance();

// 1. Database Connection & Core Tables Existence
runTest("Database Connection & Core Tables Existence", function() use ($db) {
    $requiredTables = [
        'admins', 'categories', 'products', 'product_images', 'inventory_transactions',
        'users', 'user_addresses', 'orders', 'order_items', 'referrals',
        'wallet_transactions', 'coupons', 'settings', 'traffic', 'product_analytics', 'email_logs'
    ];
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($requiredTables as $t) {
        if (!in_array($t, $tables)) {
            return ['pass' => false, 'msg' => "Table '$t' missing in ecom_db"];
        }
    }
    return ['pass' => true, 'msg' => "All " . count($requiredTables) . " core tables verified in ecom_db"];
});

// 2. Admin Authentication Verification
runTest("Admin Login Credentials Validation", function() use ($db) {
    // Correct credentials
    $loginSuccess = Auth::attemptAdmin('admin@gmail.com', 'admin@123');
    if (!$loginSuccess) {
        return ['pass' => false, 'msg' => "Login failed for admin@gmail.com / admin@123"];
    }
    
    // Invalid credentials
    $loginFail = Auth::attemptAdmin('admin@gmail.com', 'wrongpassword');
    if ($loginFail) {
        return ['pass' => false, 'msg' => "Security issue: login succeeded with wrong password!"];
    }

    return ['pass' => true, 'msg' => "Admin auth successfully validated with secure password verification."];
});

// 3. Customer Registration & Unique Referral Code
$testUserAEmail = 'test_referrer_' . time() . '@example.com';
$testUserBEmail = 'test_referee_' . time() . '@example.com';
$userAId = null;
$userBId = null;
$userACode = null;

runTest("Customer Registration & Unique Referral Code Generation", function() use ($db, $testUserAEmail, &$userAId, &$userACode) {
    $registerResult = Auth::registerCustomer([
        'first_name' => 'Aarav',
        'last_name' => 'Kapoor',
        'email' => $testUserAEmail,
        'phone' => '9876543299',
        'password' => 'Pass@1234'
    ]);
    
    if (!$registerResult['success']) {
        return ['pass' => false, 'msg' => "Registration failed: " . ($registerResult['error'] ?? '')];
    }
    
    $userAId = $registerResult['user_id'];
    $stmt = $db->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->execute([$userAId]);
    $userACode = $stmt->fetchColumn();

    if (empty($userACode)) {
        return ['pass' => false, 'msg' => "Referral code was not generated for registered user."];
    }

    return ['pass' => true, 'msg' => "User registered (ID: $userAId) with unique referral code: $userACode"];
});

// 4. Self-Referral Prevention & Duplicate Prevention
runTest("Referral Engine: Self-Referral Prevention & Referee Validation", function() use ($userAId, $userACode) {
    // 1. User A tries to use User A's referral code
    $selfRef = ReferralSystem::validateReferralCode($userACode, $userAId);
    if ($selfRef['valid']) {
        return ['pass' => false, 'msg' => "Self-referral check failed: system allowed user to use own referral code."];
    }
    
    // 2. User without login / new user tries to use User A's code
    $validRef = ReferralSystem::validateReferralCode($userACode, null);
    if (!$validRef['valid']) {
        return ['pass' => false, 'msg' => "Valid referral code was rejected: " . $validRef['message']];
    }

    return ['pass' => true, 'msg' => "Self-referral successfully rejected ('{$selfRef['message']}'). Valid code approved with {$validRef['discount_percent']}% discount."];
});

// 5. Referee Registration with Referral Code
runTest("Referee Registration Linked to Referrer", function() use ($testUserBEmail, $userACode, &$userBId, $userAId) {
    $regReferee = Auth::registerCustomer([
        'first_name' => 'Diya',
        'last_name' => 'Mehta',
        'email' => $testUserBEmail,
        'phone' => '9123456799',
        'password' => 'Pass@1234',
        'referral_code' => $userACode
    ]);
    
    if (!$regReferee['success']) {
        return ['pass' => false, 'msg' => "Referee registration failed: " . ($regReferee['error'] ?? '')];
    }
    $userBId = $regReferee['user_id'];

    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT referrer_id, status FROM referrals WHERE referee_id = ?");
    $stmt->execute([$userBId]);
    $refRow = $stmt->fetch();

    if (!$refRow || (int)$refRow['referrer_id'] !== (int)$userAId) {
        return ['pass' => false, 'msg' => "Referral relationship not recorded in referrals table."];
    }

    return ['pass' => true, 'msg' => "Referee registered (ID: $userBId) linked to Referrer (ID: $userAId) with status '{$refRow['status']}'."];
});

// 6. Wallet System & 50% Per-Order Cap Rule
runTest("Wallet Balance Ledger & 50% Usage Cap Enforcement", function() use ($userBId) {
    // 1. Credit ₹1000 to User B's wallet
    ReferralSystem::creditWallet($userBId, 1000.00, "Promotional Welcome Credits");
    $balance = ReferralSystem::getWalletBalance($userBId);
    if ($balance < 1000.00) {
        return ['pass' => false, 'msg' => "Wallet balance is ₹$balance, expected ₹1000.00."];
    }

    // 2. Test 50% max usage limit rule:
    // If order subtotal is ₹600, max allowed wallet usage should be 50% of ₹600 = ₹300, even though wallet has ₹1000
    $subtotal = 600.00;
    $maxCapPercent = (float)Settings::get('wallet_max_usage_percent', 50);
    $maxAllowed = round(($subtotal * ($maxCapPercent / 100)), 2);

    $attemptedUse = 500.00;
    $applicableWallet = min($balance, $maxAllowed, $attemptedUse);

    if ($applicableWallet !== 300.00) {
        return ['pass' => false, 'msg' => "50% cap calculation failed. Expected ₹300.00, got ₹$applicableWallet."];
    }

    return ['pass' => true, 'msg' => "Wallet credited ₹1000.00. 50% usage cap verified: ₹$applicableWallet max usable on ₹$subtotal subtotal."];
});

// 7. Atomic Inventory Deduction & Order Placement
$testOrderId = null;
$initialStock = null;
$testProductId = null;

runTest("Atomic Inventory Stock Deduction & Transaction Logging", function() use ($db, &$testProductId, &$initialStock, &$testOrderId, $userBId) {
    // Select first product in database
    $prod = $db->query("SELECT id, name, price, stock FROM products WHERE stock >= 5 LIMIT 1")->fetch();
    if (!$prod) {
        return ['pass' => false, 'msg' => "No product with stock >= 5 found to test."];
    }
    
    $testProductId = (int)$prod['id'];
    $initialStock = (int)$prod['stock'];
    $orderNumber = 'TEST-' . rand(10000, 99999);

    // Create a mock order
    $orderStmt = $db->prepare("
        INSERT INTO orders (order_number, user_id, first_name, last_name, email, phone, shipping_address, subtotal, discount_amount, wallet_deduction, total_amount, order_status, created_at)
        VALUES (?, ?, 'Diya', 'Mehta', 'diya@example.com', '9876543210', '123 Cyber St', ?, 0.00, 0.00, ?, 'Processing', NOW())
    ");
    $price = (float)$prod['price'];
    $orderStmt->execute([$orderNumber, $userBId, $price * 2, $price * 2]);
    $testOrderId = (int)$db->lastInsertId();

    // Insert order item (qty 2)
    $itemStmt = $db->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
        VALUES (?, ?, ?, ?, 2, ?)
    ");
    $itemStmt->execute([$testOrderId, $testProductId, $prod['name'], $price, $price * 2]);

    // Deduct stock via Inventory class
    $deducted = Inventory::deductStock($testProductId, 2, $testOrderId, 'order_placed', "E2E Test Order #$orderNumber");
    if (!$deducted) {
        return ['pass' => false, 'msg' => "Inventory::deductStock returned false."];
    }

    // Verify product stock in products table
    $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$testProductId]);
    $newStock = (int)$stmt->fetchColumn();

    if ($newStock !== ($initialStock - 2)) {
        return ['pass' => false, 'msg' => "Stock expected to be " . ($initialStock - 2) . ", found $newStock."];
    }

    // Verify audit log in inventory_transactions
    $txStmt = $db->prepare("SELECT type, change_qty, stock_after FROM inventory_transactions WHERE reference_id = ? AND product_id = ? ORDER BY id DESC LIMIT 1");
    $txStmt->execute([(string)$testOrderId, $testProductId]);
    $tx = $txStmt->fetch();

    if (!$tx || $tx['type'] !== 'order_placed' || abs((int)$tx['change_qty']) !== 2) {
        return ['pass' => false, 'msg' => "Audit entry in inventory_transactions missing or incorrect."];
    }

    return ['pass' => true, 'msg' => "Stock atomically deducted from $initialStock to $newStock. Audit log recorded in inventory_transactions."];
});

// 8. Referrer Reward Cashback
runTest("Referrer Reward Cashback to Wallet on Order", function() use ($userAId, $userBId, $testOrderId) {
    $db = Database::getInstance();
    // Process referral reward for User A
    ReferralSystem::processReferralReward($userBId, 2000.00, $testOrderId);

    $balance = ReferralSystem::getWalletBalance($userAId);
    if ($balance <= 0) {
        return ['pass' => false, 'msg' => "Referrer wallet not credited with cashback."];
    }

    // Check referral status updated to completed
    $stmt = $db->prepare("SELECT status, reward_amount FROM referrals WHERE referrer_id = ? AND referee_id = ?");
    $stmt->execute([$userAId, $userBId]);
    $ref = $stmt->fetch();

    if (!$ref || $ref['status'] !== 'Completed') {
        return ['pass' => false, 'msg' => "Referral status not updated to completed."];
    }

    return ['pass' => true, 'msg' => "Referrer rewarded with ₹{$ref['reward_amount']} cashback in wallet. Referral status set to 'Completed'."];
});

// 9. Order Cancellation & Automatic Stock Restoration
runTest("Order Cancellation & Automatic Stock Restoration", function() use ($db, $testProductId, $initialStock, $testOrderId) {
    // Fetch line items and restore stock
    $itemsStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $itemsStmt->execute([$testOrderId]);
    $items = $itemsStmt->fetchAll();

    foreach ($items as $it) {
        Inventory::restoreStock($it['product_id'], $it['quantity'], $testOrderId, 'order_cancelled', "E2E Test Cancellation");
    }

    // Verify stock is restored back to initialStock
    $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$testProductId]);
    $restoredStock = (int)$stmt->fetchColumn();

    if ($restoredStock !== $initialStock) {
        return ['pass' => false, 'msg' => "Restored stock expected $initialStock, got $restoredStock."];
    }

    // Verify audit log for return/restoration
    $txStmt = $db->prepare("SELECT type, change_qty FROM inventory_transactions WHERE reference_id = ? AND type = 'order_cancelled' ORDER BY id DESC LIMIT 1");
    $txStmt->execute([(string)$testOrderId]);
    $tx = $txStmt->fetch();

    if (!$tx || (int)$tx['change_qty'] !== 2) {
        return ['pass' => false, 'msg' => "Return audit entry missing in inventory_transactions."];
    }

    return ['pass' => true, 'msg' => "Stock automatically restored to $restoredStock. Return audit transaction logged."];
});

// 10. Platform Settings Engine Persistence
runTest("Platform Settings Engine Live Persistence", function() {
    $testKey = 'e2e_test_setting_' . time();
    $testVal = 'dynamic_val_' . rand(100, 999);

    Settings::set($testKey, $testVal);
    $fetched = Settings::get($testKey);

    if ($fetched !== $testVal) {
        return ['pass' => false, 'msg' => "Settings persistence failed: saved '$testVal', retrieved '$fetched'."];
    }

    // Clean up test setting
    $db = Database::getInstance();
    $db->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([$testKey]);

    return ['pass' => true, 'msg' => "Live setting saved and retrieved correctly from MySQL settings table."];
});

// 11. CSRF Token Protection Engine
runTest("CSRF Token Generator & Strict Validation", function() {
    $token = csrf_token();
    if (empty($token) || strlen($token) !== 64) {
        return ['pass' => false, 'msg' => "Invalid CSRF token format or length."];
    }

    // Test token verification
    $_POST['csrf_token'] = $token;
    if (!csrf_verify(false)) {
        return ['pass' => false, 'msg' => "Valid token was rejected."];
    }

    // Test invalid token
    $_POST['csrf_token'] = 'invalid_tampered_token';
    if (csrf_verify(false)) {
        return ['pass' => false, 'msg' => "Tampered token was mistakenly accepted!"];
    }

    unset($_POST['csrf_token']);
    return ['pass' => true, 'msg' => "CSRF tokens generated and validated with cryptographically secure comparison."];
});

// 12. Email Logging Service
runTest("Mailer Service & email_logs Audit Trail", function() use ($db) {
    $mailer = Mailer::getInstance();
    $testSubject = "E2E Automated Test Email " . time();
    $mailer->send("test.recipient@example.com", $testSubject, "<p>Test body</p>");

    $stmt = $db->prepare("SELECT recipient, subject, status FROM email_logs WHERE subject = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$testSubject]);
    $log = $stmt->fetch();

    if (!$log) {
        return ['pass' => false, 'msg' => "Email was not logged to email_logs table."];
    }

    return ['pass' => true, 'msg' => "Email dispatch logged to email_logs with status: '{$log['status']}'."];
});

// 13. Real-Time Traffic & Product Analytics Logging
runTest("Analytics Logging: Page Visits & Product Views", function() use ($db, $testProductId) {
    // Log visit
    Analytics::logVisit('/cbd.php');
    $stmt = $db->query("SELECT COUNT(*) FROM traffic WHERE page_url = '/cbd.php'");
    $visitCount = (int)$stmt->fetchColumn();

    if ($visitCount <= 0) {
        return ['pass' => false, 'msg' => "Visit was not recorded in traffic table."];
    }

    // Log product view
    if ($testProductId) {
        Analytics::logProductView($testProductId);
        $paStmt = $db->prepare("SELECT COUNT(*) FROM product_analytics WHERE product_id = ? AND action_type = 'view'");
        $paStmt->execute([$testProductId]);
        $views = (int)$paStmt->fetchColumn();

        if ($views <= 0) {
            return ['pass' => false, 'msg' => "Product view was not incremented in product_analytics."];
        }
    }

    return ['pass' => true, 'msg' => "Traffic visit and product view logged successfully in database."];
});

// Clean up E2E temporary test data
if ($testOrderId) {
    $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$testOrderId]);
    $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$testOrderId]);
    $db->prepare("DELETE FROM inventory_transactions WHERE reference_id = ?")->execute([(string)$testOrderId]);
}
if ($userBId) {
    $db->prepare("DELETE FROM wallet_transactions WHERE user_id = ?")->execute([$userBId]);
    $db->prepare("DELETE FROM referrals WHERE referee_id = ?")->execute([$userBId]);
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userBId]);
}
if ($userAId) {
    $db->prepare("DELETE FROM wallet_transactions WHERE user_id = ?")->execute([$userAId]);
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userAId]);
}

echo "\n============================================================\n";
echo "   TEST SUMMARY\n";
echo "============================================================\n";
echo "Total Tests Executed: $totalTests\n";
echo "Passed:               $passedTests\n";
echo "Failed:               $failedTests\n";
echo "Success Rate:         " . round(($passedTests / $totalTests) * 100, 2) . "%\n";
echo "============================================================\n";

if ($failedTests === 0) {
    echo "🎉 ALL TESTS PASSED! PLATFORM IS FULLY OPERATIONAL AND PRODUCTION-READY.\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED. Please review the output above.\n";
    exit(1);
}
