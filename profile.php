<?php
// profile.php - Dynamic MySQL Powered User Profile & Authentication
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/referral.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';

Analytics::trackPage('profile', 'User Profile');

// Load Global Settings
$allSettings = Settings::getAll();
$storeName = Settings::get('store_name', 'KAMS HEMP');
$supportEmail = Settings::get('support_email', 'support@kamshemp.com');
$supportPhone = Settings::get('support_phone', '+91 98765 43210');
$referralDiscountPercent = (float)Settings::get('referral_discount_percent', 15);
$currencySymbol = Settings::getCurrencySymbol();
$settings = $allSettings;

$errorMsg = '';
$successMsg = '';
$activeTab = 'login';

// Check GET referral or tab
if (isset($_GET['ref'])) {
    $_SESSION['referral_code'] = strtoupper(trim($_GET['ref']));
    $activeTab = 'register';
}
if (isset($_GET['tab'])) {
    $activeTab = htmlspecialchars($_GET['tab']);
}

// Handle Logout
if (isset($_GET['logout'])) {
    Auth::logout();
    header("Location: profile.php");
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = Database::getInstance();

    if (!verify_csrf()) {
        $errorMsg = "Security token validation failed. Please refresh the page.";
    } else {
        if ($action === 'register') {
            $activeTab = 'register';
            $fname = trim($_POST['first_name'] ?? '');
            $lname = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $pass = $_POST['password'] ?? '';
            $cpass = $_POST['confirm_password'] ?? '';
            $over21 = isset($_POST['over_21']);

            // Phone Validation: 10 digits only, cannot start with 00
            if (!preg_match('/^(?!00)\d{10}$/', $phone)) {
                $errorMsg = "Phone number must be exactly 10 digits and cannot start with 00. Spaces and special characters are not allowed.";
            } 
            // Password Validation: Minimum 8 characters, only alphabets and numbers allowed
            elseif (!preg_match('/^[A-Za-z0-9]{8,}$/', $pass)) {
                $errorMsg = "Password must be at least 8 characters long and contain only letters and numbers.";
            } 
            elseif ($pass !== $cpass) {
                $errorMsg = "Passwords do not match.";
            } 
            elseif (!$over21) {
                $errorMsg = "You must be over 21 to create an account.";
            } 
            else {
                // Check if user already exists
                $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->fetch()) {
                    $errorMsg = "An account with this email already exists.";
                } else {
                    $otp = rand(100000, 999999);
                    $_SESSION['pending_user'] = [
                        'first_name' => $fname,
                        'last_name' => $lname,
                        'email' => $email,
                        'phone' => $phone,
                        'password' => password_hash($pass, PASSWORD_DEFAULT)
                    ];
                    $_SESSION['verification_otp'] = $otp;
                    
                    // Dispatch real verification OTP email
                    try {
                        Mailer::sendOtp($email, $fname, (string)$otp);
                    } catch (\Throwable $mEx) {
                        error_log("OTP Email dispatch error: " . $mEx->getMessage());
                    }
                    
                    $successMsg = "A verification OTP has been sent to your email ($email). Please check your inbox (and spam folder).";
                    $activeTab = 'otp';
                }
            }
        } elseif ($action === 'verify_otp') {
            $enteredOtp = trim($_POST['otp'] ?? '');
            
            if (isset($_SESSION['verification_otp']) && $enteredOtp == $_SESSION['verification_otp']) {
                $pending = $_SESSION['pending_user'] ?? null;
                if ($pending) {
                    try {
                        $newRefCode = ReferralSystem::generateCode($pending['first_name']);
                        
                        try {
                            $stmtIns = $db->prepare("
                                INSERT INTO users (first_name, last_name, email, phone, password_hash, referral_code, is_verified, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                            ");
                            $stmtIns->execute([
                                $pending['first_name'],
                                $pending['last_name'],
                                $pending['email'],
                                $pending['phone'],
                                $pending['password'],
                                $newRefCode
                            ]);
                        } catch (\PDOException $pe) {
                            // Fallback if schema has 'password' instead of 'password_hash'
                            if (strpos($pe->getMessage(), 'password_hash') !== false || strpos($pe->getMessage(), '1054') !== false) {
                                $stmtIns = $db->prepare("
                                    INSERT INTO users (first_name, last_name, email, phone, password, referral_code, is_verified, created_at, updated_at)
                                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                                ");
                                $stmtIns->execute([
                                    $pending['first_name'],
                                    $pending['last_name'],
                                    $pending['email'],
                                    $pending['phone'],
                                    $pending['password'],
                                    $newRefCode
                                ]);
                            } else {
                                throw $pe;
                            }
                        }

                        $newUserId = (int)$db->lastInsertId();
                        
                        // Clean up session
                        unset($_SESSION['verification_otp']);
                        unset($_SESSION['pending_user']);
                        
                        // Log the user in directly
                        $stmtGet = $db->prepare("SELECT * FROM users WHERE id = ?");
                        $stmtGet->execute([$newUserId]);
                        $_SESSION['user'] = $stmtGet->fetch();
                        
                        $successMsg = "Account verified and registered successfully! Welcome to " . htmlspecialchars($storeName) . ".";
                        header("Location: profile.php");
                        exit;
                    } catch (\Throwable $e) {
                        $errorMsg = "Account creation failed: " . $e->getMessage();
                        $activeTab = 'otp';
                    }
                } else {
                    $errorMsg = "Session expired. Please register again.";
                    $activeTab = 'register';
                }
            } else {
                $errorMsg = "Invalid OTP code. Please try again.";
                $activeTab = 'otp';
            }
        } elseif ($action === 'login') {
            $email = trim($_POST['email'] ?? '');
            $pass = $_POST['password'] ?? '';
            
            // Check Admin login
            $admRes = Auth::loginAdmin($email, $pass);
            if (!empty($admRes['success'])) {
                header("Location: admin.php");
                exit;
            }
            
            // Check Customer login
            if (Auth::loginCustomer($email, $pass)) {
                header("Location: profile.php");
                exit;
            } else {
                $stmtC = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmtC->execute([$email]);
                if ($stmtC->fetch()) {
                    $errorMsg = "Invalid password. Please check your credentials.";
                } else {
                    $errorMsg = "Email not found. Please create an account first.";
                }
                $activeTab = 'login';
            }
        } elseif ($action === 'forgot_password') {
            $email = trim($_POST['email'] ?? '');
            $stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            
            if ($u) {
                $_SESSION['reset_email'] = $email;
                $resetOtp = rand(100000, 999999);
                $_SESSION['reset_otp'] = $resetOtp;
                
                Mailer::send($email, "$storeName Password Reset Request", 
                    "Hello " . htmlspecialchars($u['first_name']) . ",\n\nA password reset was requested for your account.\nReturn to the website to create a new password.\n\nThank you,\n$storeName Team"
                );
                
                $successMsg = "Account found! Instructions sent to your email. Please create a new password.";
                $activeTab = 'reset';
            } else {
                $errorMsg = "Email not found. Please check and try again.";
                $activeTab = 'forgot';
            }
        } elseif ($action === 'reset_password') {
            $pass = $_POST['password'] ?? '';
            $cpass = $_POST['confirm_password'] ?? '';
            $resetEmail = $_SESSION['reset_email'] ?? '';

            if (empty($resetEmail)) {
                $errorMsg = "Session expired. Please try resetting your password again.";
                $activeTab = 'forgot';
            } elseif (!preg_match('/^[A-Za-z0-9]{8,}$/', $pass)) {
                $errorMsg = "Password must be at least 8 characters long and contain only letters and numbers.";
                $activeTab = 'reset';
            } elseif ($pass !== $cpass) {
                $errorMsg = "Passwords do not match.";
                $activeTab = 'reset';
            } else {
                $upd = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?");
                $upd->execute([password_hash($pass, PASSWORD_DEFAULT), $resetEmail]);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_otp']);
                
                $successMsg = "Password reset successfully! You can now log in.";
                $activeTab = 'login';
            }
        } elseif ($action === 'update_profile') {
            if (Auth::isCustomerLoggedIn()) {
                $userId = Auth::getUserId();
                $currPass = $_POST['current_password'] ?? '';
                $newPass = $_POST['new_password'] ?? '';
                $passUpdateAttempt = (!empty($currPass) || !empty($newPass));

                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $u = $stmt->fetch();

                if ($passUpdateAttempt) {
                    if (!password_verify($currPass, $u['password_hash'] ?? '')) {
                        $errorMsg = "Current password is incorrect.";
                    } elseif (!preg_match('/^[A-Za-z0-9]{8,}$/', $newPass)) {
                        $errorMsg = "New password must be at least 8 characters long and contain only letters and numbers.";
                    } else {
                        $updPass = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                        $updPass->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
                    }
                }

                if (empty($errorMsg)) {
                    $newFname = trim($_POST['first_name'] ?? $u['first_name']);
                    $newLname = trim($_POST['last_name'] ?? $u['last_name']);
                    $newPhone = trim($_POST['phone'] ?? $u['phone']);
                    
                    if (preg_match('/^(?!00)\d{10}$/', $newPhone)) {
                        $phoneToSet = $newPhone;
                    } else {
                        $phoneToSet = $u['phone'];
                    }

                    $updProfile = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                    $updProfile->execute([$newFname, $newLname, $phoneToSet, $userId]);

                    // Refresh user session
                    $stmt->execute([$userId]);
                    $_SESSION['user'] = $stmt->fetch();

                    if ($passUpdateAttempt) {
                        $successMsg = "Profile details and password updated successfully!";
                    } else {
                        $successMsg = "Profile details updated successfully!";
                    }
                }
            }
        } elseif ($action === 'add_address') {
            if (Auth::isCustomerLoggedIn()) {
                $userId = Auth::getUserId();
                $title = trim($_POST['addr_title'] ?? 'Home');
                $street = trim($_POST['addr_street'] ?? '');
                $city = trim($_POST['addr_city'] ?? '');
                $state = trim($_POST['addr_state'] ?? '');
                $zip = trim($_POST['addr_zip'] ?? '');

                if (!empty($street) && !empty($city)) {
                    $chk = $db->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
                    $chk->execute([$userId]);
                    $isDef = ($chk->fetchColumn() == 0) ? 1 : 0;

                    $ins = $db->prepare("
                        INSERT INTO user_addresses (user_id, title, name, phone, street, city, state, zip, is_default, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $ins->execute([
                        $userId,
                        $title,
                        ($_SESSION['user']['first_name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? ''),
                        $_SESSION['user']['phone'] ?? '',
                        $street,
                        $city,
                        $state,
                        $zip,
                        $isDef
                    ]);
                    $successMsg = "Address saved successfully!";
                }
            }
        } elseif ($action === 'delete_address') {
            if (Auth::isCustomerLoggedIn()) {
                $userId = Auth::getUserId();
                $addrId = (int)($_POST['address_id'] ?? 0);
                $del = $db->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
                $del->execute([$addrId, $userId]);
                $successMsg = "Address deleted successfully!";
            }
        }
    }
}

// Check Login State & Fetch User Details, Addresses, Orders & Referral Data
$isLoggedIn = Auth::isCustomerLoggedIn();
$currentUser = null;
$userReferralCode = '';
$userReferralCount = 0;
$userWalletRewards = 0;
$shareLink = '';
$userOrders = [];
$userAddresses = [];

if ($isLoggedIn) {
    $userId = Auth::getUserId();
    $db = Database::getInstance();

    // Fetch fresh user data from DB
    $stmtU = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmtU->execute([$userId]);
    $currentUser = $stmtU->fetch();

    if ($currentUser) {
        // Ensure referral code exists
        if (empty($currentUser['referral_code'])) {
            $baseName = preg_replace('/[^A-Za-z]/', '', $currentUser['first_name'] ?? 'USER');
            $newCode = ReferralSystem::generateUniqueCode($baseName);
            $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$newCode, $userId]);
            $currentUser['referral_code'] = $newCode;
        }

        $_SESSION['user'] = $currentUser;
        $userReferralCode = $currentUser['referral_code'] ?? '';
        $userReferralCount = (int)($currentUser['referral_count'] ?? 0);
        $userWalletRewards = (float)($currentUser['wallet_balance'] ?? 0);

        // Create absolute share link
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain = $_SERVER['HTTP_HOST'] ?? 'kamshemp.com';
        $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $path = ($path === '/' || $path === '.') ? '' : $path;
        $shareLink = $protocol . "://" . $domain . $path . "/profile.php?tab=register&ref=" . $userReferralCode;

        // Fetch user addresses
        $stmtAddr = $db->prepare("SELECT id, title, street, city, state, zip, is_default FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
        $stmtAddr->execute([$userId]);
        $userAddresses = $stmtAddr->fetchAll();
        $currentUser['addresses'] = $userAddresses;

        // Fetch user orders
        $stmtOrders = $db->prepare("SELECT * FROM orders WHERE user_id = ? OR email = ? ORDER BY id DESC");
        $stmtOrders->execute([$userId, $currentUser['email']]);
        $dbOrders = $stmtOrders->fetchAll();

        foreach ($dbOrders as $o) {
            $oId = (int)$o['id'];
            $stmtItems = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$oId]);
            $items = $stmtItems->fetchAll();

            $details = [];
            $names = [];
            foreach ($items as $it) {
                $pName = $it['product_name'] ?? 'Product';
                $pQty = (int)$it['quantity'];
                $details[] = [
                    'name' => $pName,
                    'qty' => $pQty,
                    'price' => '₹' . number_format((float)$it['price'], 2)
                ];
                $names[] = "$pName (x$pQty)";
            }

            $userOrders[] = [
                'id' => '#' . $o['order_number'],
                'date' => date('M d, Y', strtotime($o['created_at'])),
                'time' => date('h:i A', strtotime($o['created_at'])),
                'amount' => '₹' . number_format((float)$o['total_amount'], 2),
                'status' => $o['order_status'],
                'items' => !empty($names) ? implode(', ', $names) : 'Products',
                'product_details' => $details,
                'shipping_address' => ($o['shipping_address'] ?? 'Standard Address') . (!empty($o['shipping_city']) ? ', ' . $o['shipping_city'] : '') . (!empty($o['shipping_state']) ? ', ' . $o['shipping_state'] : '') . (!empty($o['shipping_zip']) ? ' ' . $o['shipping_zip'] : '')
            ];
        }
    }
}

// Helper to get initials for avatar
$initials = "U";
if ($currentUser) {
    $initials = strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    SEO::renderMeta([
        'title' => $isLoggedIn ? 'Customer Dashboard & Orders' : 'My Account & Registration',
        'description' => 'Manage your ' . htmlspecialchars($storeName) . ' profile, track orders, view reward balances, and configure delivery addresses.'
    ]);
    ?>
    <style>
        .profile-hero {
            padding: 44px 0 20px 0;
            background: radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.05) 0%, transparent 70%);
        }
        .profile-container {
            padding-bottom: 80px;
        }

        /* --- AUTH SECTION (When Logged Out) --- */
        .auth-wrapper {
            max-width: 500px;
            margin: 30px auto;
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }
        .auth-tabs-nav {
            display: flex;
            border-bottom: 1px solid var(--theme-border);
            background: rgba(255, 255, 255, 0.02);
        }
        .auth-tab-btn {
            flex: 1;
            padding: 16px 20px;
            background: transparent;
            border: none;
            color: var(--theme-text-secondary);
            font-family: var(--font-heading);
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            transition: var(--transition-fast);
            border-bottom: 2px solid transparent;
        }
        .auth-tab-btn.active {
            color: #ffffff;
            background: rgba(0, 255, 204, 0.04);
            border-bottom-color: var(--theme-primary);
        }
        .auth-body {
            padding: 36px 32px;
        }
        .auth-form-view {
            display: none;
        }
        .auth-form-view.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group-item {
            margin-bottom: 20px;
        }
        .form-group-item label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--theme-text-muted);
            margin-bottom: 7px;
        }
        .form-control-input {
            width: 100%;
            padding: 12px 14px;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            color: #ffffff;
            font-size: 14px;
            outline: none;
            transition: var(--transition-fast);
        }
        .form-control-input:focus {
            border-color: var(--theme-primary);
            box-shadow: 0 0 0 3px rgba(0, 255, 204, 0.12);
            background: rgba(255, 255, 255, 0.07);
        }
        .form-control-input[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* --- DASHBOARD SECTION (When Logged In) --- */
        .dash-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 32px;
            margin-top: 30px;
            align-items: flex-start;
        }
        .dash-sidebar {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            padding: 24px;
            box-shadow: var(--shadow-card);
            position: sticky;
            top: 100px;
        }
        .dash-user-badge {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--theme-border);
            margin-bottom: 18px;
        }
        .user-avatar-circle {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--theme-primary) 0%, #3b82f6 100%);
            color: #030806;
            font-family: var(--font-heading);
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px auto;
            box-shadow: 0 6px 20px rgba(0, 255, 204, 0.25);
        }
        .user-name-title {
            font-family: var(--font-heading);
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }
        .user-email-text {
            color: var(--theme-text-muted);
            font-size: 12.5px;
            word-break: break-all;
        }
        .dash-nav-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .dash-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: var(--radius-md);
            color: var(--theme-text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition-fast);
            cursor: pointer;
            border: 1px solid transparent;
        }
        .dash-nav-item svg {
            width: 18px;
            height: 18px;
            stroke-width: 2;
            stroke: currentColor;
            fill: none;
            flex-shrink: 0;
        }
        .dash-nav-item:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.04);
        }
        .dash-nav-item.active {
            color: #030806;
            background: var(--theme-primary);
            font-weight: 700;
        }
        .dash-nav-item.active svg {
            stroke: #030806;
        }
        .dash-nav-item.text-danger:hover {
            color: #f87171;
            background: rgba(239, 68, 68, 0.1);
        }

        /* --- DASHBOARD PANES --- */
        .dash-content-pane {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            padding: 32px;
            box-shadow: var(--shadow-card);
            display: none;
        }
        .dash-content-pane.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        .dash-pane-header {
            margin-bottom: 28px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--theme-border);
        }
        .dash-pane-title {
            font-family: var(--font-heading);
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .dash-pane-desc {
            color: var(--theme-text-secondary);
            font-size: 13.5px;
        }

        /* Order Cards */
        .order-history-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            margin-bottom: 16px;
            overflow: hidden;
            transition: var(--transition-normal);
        }
        .order-history-card:hover {
            border-color: rgba(0, 255, 204, 0.3);
        }
        .order-card-summary {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            gap: 16px;
        }
        .order-meta-title {
            font-family: var(--font-heading);
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }
        .order-meta-info {
            font-size: 12.5px;
            color: var(--theme-text-muted);
        }
        .order-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-delivered {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .status-processing {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .order-dropdown-panel {
            display: none;
            padding: 20px 24px;
            background: rgba(0, 0, 0, 0.25);
            border-top: 1px solid var(--theme-border);
        }
        .order-product-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 13.5px;
        }

        /* Saved Address Cards */
        .address-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }
        .address-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 20px;
            position: relative;
        }
        .address-box h4 {
            font-family: var(--font-heading);
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .address-box p {
            font-size: 13px;
            color: var(--theme-text-secondary);
            line-height: 1.5;
            margin-bottom: 4px;
        }
        .btn-delete-address {
            position: absolute;
            top: 16px;
            right: 16px;
            background: transparent;
            border: none;
            color: var(--theme-text-muted);
            cursor: pointer;
            transition: var(--transition-fast);
        }
        .btn-delete-address:hover {
            color: #f87171;
        }

        /* Referral Box */
        .referral-stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }
        .referral-stat-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-lg);
            padding: 20px;
            text-align: center;
        }
        .ref-stat-number {
            font-family: var(--font-heading);
            font-size: 32px;
            font-weight: 800;
            color: var(--theme-primary);
            margin-bottom: 4px;
        }
        .ref-code-clipboard {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            padding: 6px 6px 6px 14px;
            margin-bottom: 18px;
        }
        .ref-code-value {
            flex: 1;
            font-family: monospace;
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 1px;
        }

        /* Order Tracking Timeline */
        .tracking-timeline {
            position: relative;
            padding-left: 28px;
            border-left: 2px solid var(--theme-border);
            margin-top: 24px;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 24px;
        }
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -35px;
            top: 2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #111;
            border: 2px solid var(--theme-text-muted);
        }
        .timeline-item.completed::before {
            background: #10b981;
            border-color: #10b981;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
        }
        .timeline-item.active::before {
            background: var(--theme-primary);
            border-color: var(--theme-primary);
            box-shadow: 0 0 10px rgba(0, 255, 204, 0.4);
        }

        @media (max-width: 900px) {
            .dash-layout {
                grid-template-columns: 1fr;
            }
            .dash-sidebar {
                position: static;
            }
        }
        @media (max-width: 600px) {
            .form-row-2 {
                grid-template-columns: 1fr;
            }
            .referral-stat-grid {
                grid-template-columns: 1fr;
            }
            .auth-body, .dash-content-pane {
                padding: 24px 18px;
            }
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <section class="profile-hero">
            <div class="theme-container">
                <span class="theme-badge">Vedic Member Portal</span>
                <h1 style="font-family:var(--font-heading); font-size:32px; font-weight:800; color:#fff; margin-top:8px; margin-bottom:4px;">
                    <?= $isLoggedIn ? 'Welcome Back, ' . htmlspecialchars($currentUser['first_name'] ?? 'Member') : 'Account Authentication' ?>
                </h1>
                <p style="color:var(--theme-text-secondary); font-size:14.5px;">
                    <?= $isLoggedIn ? 'Manage your formulations, verified orders, and herbal reward tokens.' : 'Log in or create a verified account to view your past orders and access reward benefits.' ?>
                </p>
            </div>
        </section>

        <section class="profile-container">
            <div class="theme-container">
                
                <?php if(!empty($errorMsg)): ?>
                    <div style="background:rgba(239, 68, 68, 0.12); border:1px solid rgba(239, 68, 68, 0.35); color:#fca5a5; padding:14px 18px; border-radius:var(--radius-md); margin-top:20px; font-size:14px;">
                        <?= htmlspecialchars($errorMsg) ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($successMsg)): ?>
                    <div style="background:rgba(16, 185, 129, 0.12); border:1px solid rgba(16, 185, 129, 0.35); color:#6ee7b7; padding:14px 18px; border-radius:var(--radius-md); margin-top:20px; font-size:14px;">
                        <?= htmlspecialchars($successMsg) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$isLoggedIn): ?>
                    <!-- LOGGED OUT: AUTHENTICATION FLOW -->
                    <div class="auth-wrapper">
                        <div class="auth-tabs-nav">
                            <button type="button" class="auth-tab-btn <?= ($activeTab === 'login' || $activeTab === 'forgot' || $activeTab === 'reset') ? 'active' : '' ?>" onclick="switchAuthTab('login')">
                                Sign In
                            </button>
                            <button type="button" class="auth-tab-btn <?= ($activeTab === 'register' || $activeTab === 'otp') ? 'active' : '' ?>" onclick="switchAuthTab('register')">
                                Create Account
                            </button>
                        </div>

                        <div class="auth-body">
                            <!-- 1. LOGIN FORM -->
                            <form id="auth-login-form" class="auth-form-view <?= $activeTab === 'login' ? 'active' : '' ?>" action="profile.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="login">

                                <div class="form-group-item">
                                    <label for="login-email">Email Address</label>
                                    <input type="email" id="login-email" name="email" class="form-control-input" placeholder="you@domain.com" required autocomplete="email">
                                </div>

                                <div class="form-group-item">
                                    <label for="login-password">Password</label>
                                    <input type="password" id="login-password" name="password" class="form-control-input" placeholder="••••••••" required autocomplete="current-password">
                                </div>

                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; font-size:13px;">
                                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:var(--theme-text-secondary); user-select:none;">
                                        <input type="checkbox" name="remember" style="accent-color:var(--theme-primary);">
                                        <span>Remember Me</span>
                                    </label>
                                    <a href="#" style="color:var(--theme-text-secondary); text-decoration:none;" onclick="switchAuthTab('forgot'); return false;">Forgot Password?</a>
                                </div>

                                <button type="submit" class="btn-primary" style="width:100%; padding:14px; text-transform:uppercase; font-size:13.5px; font-weight:700;">
                                    Sign In to Account
                                </button>
                            </form>

                            <!-- 2. REGISTRATION FORM -->
                            <form id="auth-register-form" class="auth-form-view <?= $activeTab === 'register' ? 'active' : '' ?>" action="profile.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="register">

                                <div class="form-row-2">
                                    <div class="form-group-item">
                                        <label for="reg-first-name">First Name</label>
                                        <input type="text" id="reg-first-name" name="first_name" class="form-control-input" placeholder="First Name" required>
                                    </div>
                                    <div class="form-group-item">
                                        <label for="reg-last-name">Last Name</label>
                                        <input type="text" id="reg-last-name" name="last_name" class="form-control-input" placeholder="Last Name" required>
                                    </div>
                                </div>

                                <div class="form-row-2">
                                    <div class="form-group-item">
                                        <label for="reg-email">Email Address</label>
                                        <input type="email" id="reg-email" name="email" class="form-control-input" placeholder="you@domain.com" required>
                                    </div>
                                    <div class="form-group-item">
                                        <label for="reg-phone">Phone Number</label>
                                        <input type="tel" id="reg-phone" name="phone" class="form-control-input" placeholder="10-digit mobile" pattern="^(?!00)\d{10}$" title="10 digits exactly, cannot start with 00" required>
                                    </div>
                                </div>

                                <div class="form-row-2">
                                    <div class="form-group-item">
                                        <label for="reg-pass">Password</label>
                                        <input type="password" id="reg-pass" name="password" class="form-control-input" placeholder="Min 8 chars" pattern="^[A-Za-z0-9]{8,}$" title="At least 8 characters, letters and numbers only" required>
                                    </div>
                                    <div class="form-group-item">
                                        <label for="reg-cpass">Confirm Password</label>
                                        <input type="password" id="reg-cpass" name="confirm_password" class="form-control-input" placeholder="Re-enter password" required>
                                    </div>
                                </div>

                                <div style="margin-bottom:12px; font-size:13px; color:var(--theme-text-secondary);">
                                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                        <input type="checkbox" name="over_21" value="1" required style="accent-color:var(--theme-primary);">
                                        <span>I confirm that I am <strong>21 years of age or older</strong>.</span>
                                    </label>
                                </div>

                                <div style="margin-bottom:22px; font-size:13px; color:var(--theme-text-secondary);">
                                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                        <input type="checkbox" required style="accent-color:var(--theme-primary);">
                                        <span>I agree to the <a href="terms.php" style="color:var(--theme-primary);">Terms of Service</a> & <a href="privacy-policy.php" style="color:var(--theme-primary);">Privacy Policy</a>.</span>
                                    </label>
                                </div>

                                <button type="submit" class="btn-primary" style="width:100%; padding:14px; text-transform:uppercase; font-size:13.5px; font-weight:700;">
                                    Create Secure Account
                                </button>
                            </form>

                            <!-- 3. OTP VERIFICATION FORM -->
                            <form id="auth-otp-form" class="auth-form-view <?= $activeTab === 'otp' ? 'active' : '' ?>" action="profile.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="verify_otp">

                                <div style="margin-bottom:20px; font-size:14px; color:var(--theme-text-secondary); line-height:1.5;">
                                    Please enter the 6-digit verification code sent to your registered email address.
                                </div>

                                <div class="form-group-item">
                                    <label for="otp-input">6-Digit Verification Code</label>
                                    <input type="text" id="otp-input" name="otp" class="form-control-input" placeholder="e.g. 123456" pattern="\d{6}" title="Must be exactly 6 digits" required style="letter-spacing:4px; font-size:18px; text-align:center;">
                                </div>

                                <button type="submit" class="btn-primary" style="width:100%; padding:14px; text-transform:uppercase; font-size:13.5px; font-weight:700;">
                                    Verify & Activate Account
                                </button>

                                <div style="text-align:center; margin-top:20px;">
                                    <a href="#" style="color:var(--theme-primary); text-decoration:none; font-size:13px;" onclick="switchAuthTab('register'); return false;">
                                        ← Back to Registration
                                    </a>
                                </div>
                            </form>

                            <!-- 4. FORGOT PASSWORD FORM -->
                            <form id="auth-forgot-form" class="auth-form-view <?= $activeTab === 'forgot' ? 'active' : '' ?>" action="profile.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="forgot_password">

                                <div style="margin-bottom:20px; font-size:14px; color:var(--theme-text-secondary); line-height:1.5;">
                                    Enter your registered email address and we will assist you in securely resetting your password.
                                </div>

                                <div class="form-group-item">
                                    <label for="forgot-email-input">Email Address</label>
                                    <input type="email" id="forgot-email-input" name="email" class="form-control-input" placeholder="you@domain.com" required>
                                </div>

                                <button type="submit" class="btn-primary" style="width:100%; padding:14px; text-transform:uppercase; font-size:13.5px; font-weight:700;">
                                    Find Account
                                </button>

                                <div style="text-align:center; margin-top:20px;">
                                    <a href="#" style="color:var(--theme-primary); text-decoration:none; font-size:13px;" onclick="switchAuthTab('login'); return false;">
                                        ← Return to Sign In
                                    </a>
                                </div>
                            </form>

                            <!-- 5. RESET PASSWORD FORM -->
                            <form id="auth-reset-form" class="auth-form-view <?= $activeTab === 'reset' ? 'active' : '' ?>" action="profile.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_password">

                                <div style="margin-bottom:20px; font-size:14px; color:var(--theme-text-secondary); line-height:1.5;">
                                    Create a new secure password for <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? 'your account') ?></strong>.
                                </div>

                                <div class="form-group-item">
                                    <label for="reset-pass">New Password</label>
                                    <input type="password" id="reset-pass" name="password" class="form-control-input" placeholder="Min 8 chars" pattern="^[A-Za-z0-9]{8,}$" title="At least 8 characters, letters and numbers only" required>
                                </div>

                                <div class="form-group-item">
                                    <label for="reset-cpass">Confirm New Password</label>
                                    <input type="password" id="reset-cpass" name="confirm_password" class="form-control-input" placeholder="Re-enter new password" required>
                                </div>

                                <button type="submit" class="btn-primary" style="width:100%; padding:14px; text-transform:uppercase; font-size:13.5px; font-weight:700;">
                                    Save New Password
                                </button>

                                <div style="text-align:center; margin-top:20px;">
                                    <a href="#" style="color:var(--theme-primary); text-decoration:none; font-size:13px;" onclick="switchAuthTab('login'); return false;">
                                        Cancel Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- LOGGED IN: CUSTOMER DASHBOARD -->
                    <div class="dash-layout">
                        
                        <!-- Sidebar Navigation -->
                        <aside class="dash-sidebar">
                            <div class="dash-user-badge">
                                <div class="user-avatar-circle"><?= htmlspecialchars($initials) ?></div>
                                <h3 class="user-name-title"><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h3>
                                <p class="user-email-text"><?= htmlspecialchars($currentUser['email']) ?></p>
                                
                                <div style="margin-top:12px; display:inline-block; font-size:11.5px; color:var(--theme-primary); background:rgba(0,255,204,0.08); padding:4px 12px; border-radius:var(--radius-full); border:1px solid rgba(0,255,204,0.2);">
                                    Rewards Balance: ₹<?= number_format($userWalletRewards, 2) ?>
                                </div>
                            </div>

                            <nav class="dash-nav-list">
                                <a href="#account" class="dash-nav-item active" onclick="switchDashboardPane('account', this); return false;">
                                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    Account Details
                                </a>
                                <a href="#orders" class="dash-nav-item" onclick="switchDashboardPane('orders', this); return false;">
                                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    Order History (<?= count($userOrders) ?>)
                                </a>
                                <a href="#tracking" class="dash-nav-item" onclick="switchDashboardPane('tracking', this); return false;">
                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="10" r="3"></circle><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"></path></svg>
                                    Track Consignment
                                </a>
                                <a href="#addresses" class="dash-nav-item" onclick="switchDashboardPane('addresses', this); return false;">
                                    <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    Saved Addresses
                                </a>
                                <a href="#referrals" class="dash-nav-item" onclick="switchDashboardPane('referrals', this); return false;">
                                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                                    Refer & Earn
                                </a>
                                <a href="profile.php?logout=1" class="dash-nav-item text-danger" style="margin-top:10px; border-top:1px solid var(--theme-border); padding-top:16px;">
                                    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                    Sign Out
                                </a>
                            </nav>
                        </aside>

                        <!-- Content Area Panes -->
                        <div class="dash-panes-wrapper">

                            <!-- 1. ACCOUNT DETAILS PANE -->
                            <div id="pane-account" class="dash-content-pane active">
                                <div class="dash-pane-header">
                                    <h2 class="dash-pane-title">Personal Information</h2>
                                    <p class="dash-pane-desc">Manage your identity, communication credentials, and security password.</p>
                                </div>

                                <form action="profile.php" method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_profile">

                                    <div class="form-row-2">
                                        <div class="form-group-item">
                                            <label for="acc-fname">First Name</label>
                                            <input type="text" id="acc-fname" name="first_name" class="form-control-input" value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group-item">
                                            <label for="acc-lname">Last Name</label>
                                            <input type="text" id="acc-lname" name="last_name" class="form-control-input" value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>" required>
                                        </div>
                                    </div>

                                    <div class="form-row-2">
                                        <div class="form-group-item">
                                            <label for="acc-email">Email Address (Registered)</label>
                                            <input type="email" id="acc-email" class="form-control-input" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" readonly>
                                        </div>
                                        <div class="form-group-item">
                                            <label for="acc-phone">Mobile Phone (10 digits)</label>
                                            <input type="tel" id="acc-phone" name="phone" class="form-control-input" pattern="^(?!00)\d{10}$" title="10 digits exactly, cannot start with 00" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div style="margin:28px 0 18px 0; padding-top:18px; border-top:1px solid var(--theme-border);">
                                        <h4 style="font-family:var(--font-heading); font-size:15px; color:#fff; margin-bottom:12px;">Security & Password</h4>
                                        <p style="font-size:13px; color:var(--theme-text-muted); margin-bottom:18px;">Leave blank if you do not want to alter your existing account password.</p>
                                    </div>

                                    <div class="form-row-2">
                                        <div class="form-group-item">
                                            <label for="acc-curr-pass">Current Password</label>
                                            <input type="password" id="acc-curr-pass" name="current_password" class="form-control-input" placeholder="••••••••">
                                        </div>
                                        <div class="form-group-item">
                                            <label for="acc-new-pass">New Password (Min 8 chars)</label>
                                            <input type="password" id="acc-new-pass" name="new_password" class="form-control-input" placeholder="Min 8 characters" pattern="^[A-Za-z0-9]{8,}$" title="At least 8 characters, letters and numbers only">
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-primary" style="padding:12px 28px; text-transform:uppercase; font-size:13px; font-weight:700;">
                                        Save Changes
                                    </button>
                                </form>
                            </div>

                            <!-- 2. ORDER HISTORY PANE -->
                            <div id="pane-orders" class="dash-content-pane">
                                <div class="dash-pane-header">
                                    <h2 class="dash-pane-title">Order History</h2>
                                    <p class="dash-pane-desc">Review your verified purchases, formulation contents, and dispatch statuses.</p>
                                </div>

                                <?php if(empty($userOrders)): ?>
                                    <div style="text-align:center; padding:50px 20px; background:rgba(255,255,255,0.02); border:1px dashed var(--theme-border); border-radius:var(--radius-lg);">
                                        <div style="font-size:36px; margin-bottom:12px;">🌿</div>
                                        <h4 style="font-family:var(--font-heading); font-size:18px; color:#fff; margin-bottom:6px;">No Purchases Found Yet</h4>
                                        <p style="color:var(--theme-text-secondary); font-size:14px; max-width:400px; margin:0 auto 20px auto;">You haven't placed any wellness formulation orders under this account.</p>
                                        <a href="cbd-products.php" class="btn-primary" style="display:inline-block; padding:10px 24px; font-size:13px;">Explore Catalog</a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($userOrders as $idx => $order): ?>
                                        <div class="order-history-card">
                                            <div class="order-card-summary" onclick="toggleOrderDropdown('order-box-<?= $idx ?>')">
                                                <div>
                                                    <div class="order-meta-title"><?= htmlspecialchars($order['id']) ?></div>
                                                    <div class="order-meta-info"><?= htmlspecialchars($order['date']) ?> &nbsp;•&nbsp; <?= htmlspecialchars($order['items']) ?></div>
                                                </div>
                                                <div style="display:flex; align-items:center; gap:20px; text-align:right;">
                                                    <div>
                                                        <div style="font-family:var(--font-heading); font-size:16px; font-weight:700; color:#fff;"><?= htmlspecialchars($order['amount']) ?></div>
                                                        <span class="order-status-badge <?= strtolower($order['status']) === 'delivered' ? 'status-delivered' : 'status-processing' ?>">
                                                            <?= htmlspecialchars($order['status']) ?>
                                                        </span>
                                                    </div>
                                                    <svg id="arrow-order-<?= $idx ?>" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="transition:transform 0.3s;">
                                                        <polyline points="6 9 12 15 18 9"></polyline>
                                                    </svg>
                                                </div>
                                            </div>

                                            <div id="order-box-<?= $idx ?>" class="order-dropdown-panel">
                                                <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--theme-text-muted); margin-bottom:10px;">
                                                    Items in Consignment
                                                </div>
                                                
                                                <?php if(!empty($order['product_details'])): ?>
                                                    <?php foreach($order['product_details'] as $p): ?>
                                                        <div class="order-product-line">
                                                            <div>
                                                                <strong style="color:#fff;"><?= htmlspecialchars($p['name']) ?></strong>
                                                                <span style="color:var(--theme-text-muted); font-size:12px; margin-left:8px;">× <?= htmlspecialchars($p['qty']) ?></span>
                                                            </div>
                                                            <div style="color:var(--theme-primary); font-weight:600;"><?= htmlspecialchars($p['price']) ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>

                                                <div style="margin-top:18px; padding-top:14px; border-top:1px solid rgba(255,255,255,0.06); font-size:13px;">
                                                    <strong style="color:var(--theme-text-muted); text-transform:uppercase; font-size:11px; letter-spacing:1px; display:block; margin-bottom:4px;">Delivery Destination:</strong>
                                                    <div style="color:var(--theme-text-secondary);"><?= htmlspecialchars($order['shipping_address']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- 3. ORDER TRACKING PANE -->
                            <div id="pane-tracking" class="dash-content-pane">
                                <div class="dash-pane-header">
                                    <h2 class="dash-pane-title">Real-Time Consignment Tracking</h2>
                                    <p class="dash-pane-desc">Track Pan-India express shipment dispatched from our Himalayan processing facility.</p>
                                </div>

                                <div style="display:flex; gap:12px; margin-bottom:28px;">
                                    <input type="text" id="track-input-id" class="form-control-input" placeholder="Enter Order Number or AWB ID (e.g. ORD-100293)" style="flex:1;">
                                    <button type="button" class="btn-primary" onclick="lookupTracking()" style="padding:0 24px; white-space:nowrap; text-transform:uppercase; font-size:13px; font-weight:700;">
                                        Track
                                    </button>
                                </div>

                                <div id="track-result-container" style="display:none; background:rgba(255,255,255,0.02); border:1px solid var(--theme-border); border-radius:var(--radius-lg); padding:24px;">
                                    <!-- Rendered dynamically -->
                                </div>
                            </div>

                            <!-- 4. SAVED ADDRESSES PANE -->
                            <div id="pane-addresses" class="dash-content-pane">
                                <div class="dash-pane-header">
                                    <h2 class="dash-pane-title">Saved Delivery Addresses</h2>
                                    <p class="dash-pane-desc">Store multiple shipping destinations for 1-click checkout execution.</p>
                                </div>

                                <?php if(empty($userAddresses)): ?>
                                    <div style="text-align:center; padding:30px 20px; background:rgba(255,255,255,0.02); border:1px dashed var(--theme-border); border-radius:var(--radius-lg); margin-bottom:30px;">
                                        <p style="color:var(--theme-text-secondary); font-size:13.5px; margin:0;">No saved delivery addresses on record. Add your primary residence below.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="address-grid">
                                        <?php foreach($userAddresses as $addr): ?>
                                            <div class="address-box">
                                                <h4>
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--theme-primary)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                                    <?= htmlspecialchars($addr['title']) ?>
                                                    <?php if(!empty($addr['is_default'])): ?>
                                                        <span style="font-size:10px; background:rgba(0,255,204,0.1); color:var(--theme-primary); padding:2px 6px; border-radius:4px; font-weight:700;">DEFAULT</span>
                                                    <?php endif; ?>
                                                </h4>
                                                <p><?= htmlspecialchars($addr['street']) ?></p>
                                                <p><?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?> - <?= htmlspecialchars($addr['zip']) ?></p>

                                                <form method="POST" action="profile.php" onsubmit="return confirm('Remove this address?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_address">
                                                    <input type="hidden" name="address_id" value="<?= (int)$addr['id'] ?>">
                                                    <button type="submit" class="btn-delete-address" title="Delete Address">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div style="margin-top:28px; padding-top:20px; border-top:1px solid var(--theme-border);">
                                    <h4 style="font-family:var(--font-heading); font-size:16px; color:#fff; margin-bottom:16px;">Add New Destination</h4>
                                    
                                    <form action="profile.php" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="add_address">

                                        <div class="form-group-item">
                                            <label for="addr-title">Address Label</label>
                                            <input type="text" id="addr-title" name="addr_title" class="form-control-input" placeholder="e.g. Home Residence / Clinic / Corporate" required>
                                        </div>

                                        <div class="form-group-item">
                                            <label for="addr-street">Street Address & Landmark</label>
                                            <input type="text" id="addr-street" name="addr_street" class="form-control-input" placeholder="Flat / Building, Road, Area" required>
                                        </div>

                                        <div class="form-row-2">
                                            <div class="form-group-item">
                                                <label for="addr-city">City</label>
                                                <input type="text" id="addr-city" name="addr_city" class="form-control-input" placeholder="City" required>
                                            </div>
                                            <div class="form-group-item">
                                                <label for="addr-state">State</label>
                                                <input type="text" id="addr-state" name="addr_state" class="form-control-input" placeholder="State" required>
                                            </div>
                                        </div>

                                        <div class="form-group-item" style="max-width:240px;">
                                            <label for="addr-zip">Postal PIN Code (6 Digits)</label>
                                            <input type="text" id="addr-zip" name="addr_zip" class="form-control-input" pattern="[0-9]{6}" title="6 digit PIN code" placeholder="e.g. 110001" required>
                                        </div>

                                        <button type="submit" class="btn-primary" style="padding:12px 28px; text-transform:uppercase; font-size:13px; font-weight:700;">
                                            Save Address
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- 5. REFER & EARN PANE -->
                            <div id="pane-referrals" class="dash-content-pane">
                                <div class="dash-pane-header">
                                    <h2 class="dash-pane-title">Referral Network & Rewards</h2>
                                    <p class="dash-pane-desc">Empower loved ones with Ayurvedic healing and accumulate lifetime wallet rewards.</p>
                                </div>

                                <div class="referral-stat-grid">
                                    <div class="referral-stat-card">
                                        <div class="ref-stat-number"><?= $userReferralCount ?></div>
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:var(--theme-text-muted);">Friends Successfully Referred</div>
                                    </div>
                                    <div class="referral-stat-card">
                                        <div class="ref-stat-number">₹<?= number_format($userWalletRewards, 2) ?></div>
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:var(--theme-text-muted);">Available Wallet Balance</div>
                                    </div>
                                </div>

                                <div style="background:rgba(0, 255, 204, 0.03); border:1px solid rgba(0, 255, 204, 0.2); border-radius:var(--radius-lg); padding:24px; margin-bottom:24px;">
                                    <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--theme-primary); margin-bottom:8px;">Your Unique Referral Code</div>
                                    <div class="ref-code-clipboard">
                                        <span class="ref-code-value" id="ref-code-display"><?= htmlspecialchars($userReferralCode) ?></span>
                                        <button type="button" class="btn-primary" onclick="copyText('ref-code-display', this)" style="padding:8px 16px; font-size:12px; text-transform:uppercase;">
                                            Copy Code
                                        </button>
                                    </div>

                                    <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--theme-primary); margin-bottom:8px;">Direct Shareable Link</div>
                                    <div class="ref-code-clipboard">
                                        <span class="ref-code-value" id="ref-link-display" style="font-size:13px; font-family:var(--font-body); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($shareLink) ?></span>
                                        <button type="button" class="btn-primary" onclick="copyText('ref-link-display', this)" style="padding:8px 16px; font-size:12px; text-transform:uppercase;">
                                            Copy Link
                                        </button>
                                    </div>

                                    <p style="font-size:13px; color:var(--theme-text-secondary); margin-top:14px; line-height:1.6;">
                                        💡 Friends receive a <strong><?= $referralDiscountPercent ?>% instant discount</strong> on their first herbal formulation purchase. You earn <strong><?= $referralDiscountPercent ?>% wallet cashback</strong> immediately when their order delivers.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
        // Auth Tab Switcher (Logged Out)
        function switchAuthTab(tab) {
            document.querySelectorAll('.auth-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.auth-form-view').forEach(f => f.classList.remove('active'));

            if (tab === 'login') {
                const btn = document.querySelectorAll('.auth-tab-btn')[0];
                if (btn) btn.classList.add('active');
                const form = document.getElementById('auth-login-form');
                if (form) form.classList.add('active');
            } else if (tab === 'register') {
                const btn = document.querySelectorAll('.auth-tab-btn')[1];
                if (btn) btn.classList.add('active');
                const form = document.getElementById('auth-register-form');
                if (form) form.classList.add('active');
            } else if (tab === 'otp') {
                const btn = document.querySelectorAll('.auth-tab-btn')[1];
                if (btn) btn.classList.add('active');
                const form = document.getElementById('auth-otp-form');
                if (form) form.classList.add('active');
            } else if (tab === 'forgot') {
                const btn = document.querySelectorAll('.auth-tab-btn')[0];
                if (btn) btn.classList.add('active');
                const form = document.getElementById('auth-forgot-form');
                if (form) form.classList.add('active');
            } else if (tab === 'reset') {
                const btn = document.querySelectorAll('.auth-tab-btn')[0];
                if (btn) btn.classList.add('active');
                const form = document.getElementById('auth-reset-form');
                if (form) form.classList.add('active');
            }
        }

        // Dashboard Pane Switcher (Logged In)
        function switchDashboardPane(paneName, linkEl) {
            document.querySelectorAll('.dash-content-pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.dash-nav-item').forEach(l => l.classList.remove('active'));

            const targetPane = document.getElementById('pane-' + paneName);
            if (targetPane) {
                targetPane.classList.add('active');
            }

            if (linkEl) {
                linkEl.classList.add('active');
            } else {
                document.querySelectorAll('.dash-nav-item').forEach(l => {
                    if (l.getAttribute('href') === '#' + paneName) {
                        l.classList.add('active');
                    }
                });
            }
        }

        // Toggle Expandable Order Dropdown
        function toggleOrderDropdown(boxId) {
            const el = document.getElementById(boxId);
            const idx = boxId.replace('order-box-', '');
            const arrow = document.getElementById('arrow-order-' + idx);
            if (!el) return;

            if (el.style.display === 'block') {
                el.style.display = 'none';
                if (arrow) arrow.style.transform = 'rotate(0deg)';
            } else {
                el.style.display = 'block';
                if (arrow) arrow.style.transform = 'rotate(180deg)';
            }
        }

        // Interactive Tracking Search
        function lookupTracking() {
            const input = document.getElementById('track-input-id');
            const resultBox = document.getElementById('track-result-container');
            const val = input ? input.value.trim() : '';

            if (!val) {
                alert('Please provide a valid Order ID or AWB Tracking Number.');
                return;
            }

            resultBox.style.display = 'block';
            resultBox.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--theme-border); padding-bottom:14px; margin-bottom:20px;">
                    <div>
                        <h4 style="font-family:var(--font-heading); font-size:16px; color:#fff; margin:0 0 4px 0;">Consignment ${val}</h4>
                        <span style="font-size:12px; color:var(--theme-primary);">Carrier: Bluedart Express Air (PAN-India)</span>
                    </div>
                    <span style="font-size:11.5px; background:rgba(0,255,204,0.1); color:var(--theme-primary); border:1px solid rgba(0,255,204,0.3); padding:4px 10px; border-radius:20px; font-weight:700;">IN TRANSIT</span>
                </div>

                <div class="tracking-timeline">
                    <div class="timeline-item completed">
                        <h5 style="color:#fff; font-size:14px; margin:0 0 4px 0;">Ayurvedic Formulations Picked & Packed</h5>
                        <p style="color:var(--theme-text-muted); font-size:12px; margin:0;">Warehouse Facility, Dehradun • Lab Verification Passed</p>
                    </div>
                    <div class="timeline-item completed">
                        <h5 style="color:#fff; font-size:14px; margin:0 0 4px 0;">Handed Over to Express Air Cargo</h5>
                        <p style="color:var(--theme-text-muted); font-size:12px; margin:0;">Dispatched via Air Courier • Temperature Controlled Packaging</p>
                    </div>
                    <div class="timeline-item active">
                        <h5 style="color:#fff; font-size:14px; margin:0 0 4px 0;">Out for Delivery to Destination Hub</h5>
                        <p style="color:var(--theme-text-muted); font-size:12px; margin:0;">Estimated arrival within 24-48 business hours with secret OTP delivery</p>
                    </div>
                    <div class="timeline-item">
                        <h5 style="color:#fff; font-size:14px; margin:0 0 4px 0;">Delivered into Customer Hands</h5>
                        <p style="color:var(--theme-text-muted); font-size:12px; margin:0;">Pending final drop-off verification</p>
                    </div>
                </div>
            `;
        }

        // Copy Text Helper
        function copyText(elementId, btn) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const text = el.innerText.trim();

            navigator.clipboard.writeText(text).then(() => {
                const originalText = btn.innerText;
                btn.innerText = 'Copied!';
                btn.style.background = '#10b981';
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.style.background = '';
                }, 2000);
            }).catch(err => {
                console.error('Copy failed: ', err);
            });
        }

        // Handle URL parameters on load (e.g. profile.php?tab=orders)
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const tabParam = params.get('tab');
            if (tabParam) {
                <?php if ($isLoggedIn): ?>
                    if (['account', 'orders', 'tracking', 'addresses', 'referrals'].includes(tabParam)) {
                        switchDashboardPane(tabParam, null);
                    }
                <?php else: ?>
                    if (['login', 'register', 'otp', 'forgot', 'reset'].includes(tabParam)) {
                        switchAuthTab(tabParam);
                    }
                <?php endif; ?>
            }
        });
    </script>
</body>
</html>