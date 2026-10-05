<?php
// includes/auth.php
// Full Authentication & Session Management for Admin & Customers

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {
    // --- ADMIN AUTHENTICATION ---

    public static function loginAdmin($email, $password) {
        $email = trim(strtolower($email));
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM `admins` WHERE `email` = ? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            if (!headers_sent()) {
                @session_regenerate_id(true);
            }
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = trim($admin['first_name'] . ' ' . $admin['last_name']);
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'] ?? 'admin';

            // Record last login
            $update = $db->prepare("UPDATE `admins` SET `last_login` = NOW() WHERE `id` = ?");
            $update->execute([$admin['id']]);

            // Log to audit_logs
            $log = $db->prepare("INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`) VALUES (?, 'LOGIN', 'admin', ?, 'Admin logged in', ?)");
            $log->execute([$admin['id'], $admin['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

            return ['success' => true, 'admin' => $admin];
        }

        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    public static function logoutAdmin() {
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_name']);
        unset($_SESSION['admin_email']);
        unset($_SESSION['admin_role']);
    }

    public static function isAdminLoggedIn() {
        return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    public static function requireAdmin($redirectUrl = 'login.php') {
        if (!self::isAdminLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'admin.php';
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    public static function getAdmin($refresh = false) {
        if (!self::isAdminLoggedIn()) {
            return null;
        }
        static $cachedAdmin = null;
        if ($cachedAdmin === null || $refresh) {
            $db = getDB();
            $stmt = $db->prepare("SELECT `id`, `first_name`, `last_name`, `email`, `role`, `last_login`, `created_at` FROM `admins` WHERE `id` = ?");
            $stmt->execute([(int)$_SESSION['admin_id']]);
            $cachedAdmin = $stmt->fetch() ?: null;
        }
        return $cachedAdmin;
    }

    public static function admin($refresh = false) {
        return self::getAdmin($refresh);
    }

    public static function getAdminId() {
        return !empty($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
    }

    public static function attemptAdmin($email, $password) {
        $res = self::loginAdmin($email, $password);
        return $res['success'];
    }

    // --- CUSTOMER AUTHENTICATION ---

    public static function loginUser($email, $password) {
        $email = trim(strtolower($email));
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM `users` WHERE `email` = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Account not found. Please register.'];
        }

        if ($user['status'] !== 'Active') {
            return ['success' => false, 'message' => 'Your account is suspended. Please contact support.'];
        }

        if (password_verify($password, $user['password_hash'])) {
            if (!headers_sent()) {
                @session_regenerate_id(true);
            }
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['user'] = $user;

            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'message' => 'Incorrect password.'];
    }

    public static function logoutUser() {
        unset($_SESSION['user_logged_in']);
        unset($_SESSION['user_id']);
        unset($_SESSION['user_email']);
        unset($_SESSION['user_name']);
        unset($_SESSION['user']);
        unset($_SESSION['apply_wallet']);
        unset($_SESSION['referral_code']);
    }

    public static function logout() {
        self::logoutUser();
        self::logoutAdmin();
    }

    public static function isUserLoggedIn() {
        return !empty($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
    }

    public static function isCustomerLoggedIn() {
        return self::isUserLoggedIn();
    }

    public static function getUserId() {
        return !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function loginCustomer($email, $password) {
        $res = self::loginUser($email, $password);
        return $res['success'];
    }

    public static function getUser($refresh = false) {
        if (!self::isUserLoggedIn()) {
            return null;
        }
        static $cachedUser = null;
        if ($cachedUser === null || $refresh) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM `users` WHERE `id` = ?");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $cachedUser = $stmt->fetch() ?: null;

            if ($cachedUser) {
                // Fetch saved addresses
                $addrStmt = $db->prepare("SELECT * FROM `user_addresses` WHERE `user_id` = ? ORDER BY `is_default` DESC, `id` ASC");
                $addrStmt->execute([$cachedUser['id']]);
                $cachedUser['addresses'] = $addrStmt->fetchAll();

                // Fetch referral statistics
                $refStmt = $db->prepare("SELECT COUNT(*) as ref_count FROM `referrals` WHERE `referrer_id` = ? AND `status` = 'Completed'");
                $refStmt->execute([$cachedUser['id']]);
                $refData = $refStmt->fetch();
                $cachedUser['referral_count'] = (int)($refData['ref_count'] ?? 0);

                // Update session
                $_SESSION['user'] = $cachedUser;
            }
        }
        return $cachedUser;
    }

    public static function user($refresh = false) {
        return self::getUser($refresh);
    }

    public static function attemptUser($email, $password) {
        $res = self::loginUser($email, $password);
        return $res['success'];
    }

    public static function registerCustomer(array $data) {
        $db = getDB();
        $email = strtolower(trim($data['email'] ?? ''));
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $password = $data['password'] ?? '';
        $refCodeInput = strtoupper(trim($data['referral_code'] ?? ''));

        if (empty($email) || empty($firstName) || empty($password)) {
            return ['success' => false, 'error' => 'First name, email, and password are required.'];
        }

        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            return ['success' => false, 'error' => 'An account with this email already exists.'];
        }

        require_once __DIR__ . '/referral.php';
        $userRefCode = ReferralSystem::generateCode($firstName);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Check if referral code was provided
        $referredBy = null;
        if (!empty($refCodeInput)) {
            $refStmt = $db->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
            $refStmt->execute([$refCodeInput]);
            $referrerRow = $refStmt->fetch();
            if ($referrerRow) {
                $referredBy = (int)$referrerRow['id'];
            }
        }

        $ins = $db->prepare("
            INSERT INTO users (first_name, last_name, email, phone, password_hash, referral_code, referred_by_user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $ins->execute([$firstName, $lastName, $email, $phone, $passwordHash, $userRefCode, $referredBy]);
        $newUserId = (int)$db->lastInsertId();

        // If referred by another user, record initial pending referral
        if ($referredBy) {
            $refRecord = $db->prepare("
                INSERT INTO referrals (referrer_id, referee_id, referral_code, status, created_at)
                VALUES (?, ?, ?, 'Pending', NOW())
            ");
            $refRecord->execute([$referredBy, $newUserId, $refCodeInput]);
        }

        return ['success' => true, 'user_id' => $newUserId, 'referral_code' => $userRefCode];
    }
}

// Global convenience wrappers
function is_admin_logged_in() { return Auth::isAdminLoggedIn(); }
function require_admin($url = 'login.php') { return Auth::requireAdmin($url); }
function get_logged_in_admin($refresh = false) { return Auth::getAdmin($refresh); }

function is_user_logged_in() { return Auth::isUserLoggedIn(); }
function get_logged_in_user($refresh = false) { return Auth::getUser($refresh); }
