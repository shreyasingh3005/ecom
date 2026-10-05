<?php
// login.php
// Unified Secure Login for Admin & Customers with Database Integration

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/seo.php';

// Handle explicit logout request on login.php
if (isset($_GET['logout']) || isset($_GET['switch'])) {
    Auth::logout();
    $_SESSION = [];
    session_destroy();
    session_start();
    header("Location: login.php");
    exit;
}

// Redirect if already logged in as customer
if (Auth::isUserLoggedIn()) {
    $redirectUrl = $_SESSION['redirect_after_login'] ?? ($_GET['redirect'] ?? 'cbd.php');
    unset($_SESSION['redirect_after_login']);
    header("Location: " . $redirectUrl);
    exit;
}

// If admin is logged in but wants to access a customer storefront page
if (Auth::isAdminLoggedIn()) {
    if (!empty($_GET['redirect'])) {
        header("Location: " . $_GET['redirect']);
        exit;
    }
}

$error = '';
$infoMsg = '';
if (!empty($_SESSION['logout_notice'])) {
    $infoMsg = $_SESSION['logout_notice'];
    unset($_SESSION['logout_notice']);
} elseif (isset($_GET['logged_out'])) {
    $infoMsg = "You have been safely logged out.";
}
$storeName = Settings::get('store_name', 'KAMS HEMP');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf()) {
        $error = "Session expired or invalid security token. Please try again.";
    } else {
        $login_id = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login_id) || empty($password)) {
            $error = "Please enter both your email/username and password.";
        } else {
            // 1. Check Admin credentials first
            $adminAuth = Auth::loginAdmin($login_id, $password);
            if ($adminAuth['success']) {
                $redirectUrl = $_SESSION['redirect_after_login'] ?? ($_GET['redirect'] ?? 'admin.php');
                unset($_SESSION['redirect_after_login']);
                header("Location: " . $redirectUrl);
                exit;
            }

            // 2. Check Customer credentials second
            $userAuth = Auth::loginUser($login_id, $password);
            if ($userAuth['success']) {
                $redirectUrl = $_SESSION['redirect_after_login'] ?? ($_GET['redirect'] ?? 'cbd.php');
                unset($_SESSION['redirect_after_login']);
                header("Location: " . $redirectUrl);
                exit;
            }

            $error = "Invalid credentials. Please verify your email/username and password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    SEO::renderMeta([
        'title' => 'Sign In to Your Account',
        'description' => 'Secure portal login for ' . htmlspecialchars($storeName) . ' customers and authorized personnel. Access your order history, tracking, and rewards.'
    ]);
    ?>
    <style>
        .auth-page-section {
            min-height: calc(100vh - 380px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            background: radial-gradient(circle at 50% 30%, rgba(0, 255, 204, 0.04) 0%, transparent 70%);
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            padding: 44px 36px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .auth-card-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .auth-card-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--theme-primary);
            background: rgba(0, 255, 204, 0.08);
            border: 1px solid rgba(0, 255, 204, 0.2);
            padding: 4px 14px;
            border-radius: var(--radius-full);
            margin-bottom: 14px;
        }
        .auth-card-title {
            font-family: var(--font-heading);
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
        }
        .auth-card-subtitle {
            color: var(--theme-text-secondary);
            font-size: 13.5px;
            line-height: 1.5;
        }
        .auth-alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            margin-bottom: 22px;
        }
        .auth-alert-info {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #6ee7b7;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            margin-bottom: 22px;
        }
        .auth-form-group {
            margin-bottom: 20px;
        }
        .auth-form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--theme-text-muted);
            margin-bottom: 8px;
        }
        .auth-input {
            width: 100%;
            padding: 13px 16px;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            color: #ffffff;
            font-size: 14.5px;
            outline: none;
            transition: var(--transition-fast);
        }
        .auth-input:focus {
            border-color: var(--theme-primary);
            box-shadow: 0 0 0 3px rgba(0, 255, 204, 0.12);
            background: rgba(255, 255, 255, 0.07);
        }
        .auth-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: -6px;
            margin-bottom: 22px;
            font-size: 13px;
        }
        .auth-options a {
            color: var(--theme-text-secondary);
            text-decoration: none;
            transition: var(--transition-fast);
        }
        .auth-options a:hover {
            color: var(--theme-primary);
        }
        .auth-submit-btn {
            width: 100%;
            padding: 14px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
        }
        .auth-footer-links {
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid var(--theme-border);
            text-align: center;
            font-size: 13.5px;
            color: var(--theme-text-secondary);
        }
        .auth-footer-links a {
            color: var(--theme-primary);
            text-decoration: none;
            font-weight: 600;
        }
        .auth-footer-links a:hover {
            text-decoration: underline;
        }
        .admin-session-notice {
            background: rgba(139, 92, 246, 0.12);
            border: 1px solid rgba(139, 92, 246, 0.35);
            color: #c4b5fd;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 12.5px;
            margin-bottom: 20px;
        }
        .admin-session-notice a {
            color: var(--theme-primary);
            font-weight: 600;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <section class="auth-page-section">
            <div class="auth-card">
                
                <div class="auth-card-header">
                    <span class="auth-card-badge">Vedic Wellness Portal</span>
                    <h1 class="auth-card-title">Welcome Back</h1>
                    <p class="auth-card-subtitle">Sign in to your <?= htmlspecialchars($storeName) ?> account</p>
                </div>

                <?php if(!empty($infoMsg)): ?>
                    <div class="auth-alert-info">
                        <?= htmlspecialchars($infoMsg) ?>
                    </div>
                <?php endif; ?>

                <?php if(Auth::isAdminLoggedIn()): ?>
                    <div class="admin-session-notice">
                        ⚡ Active <strong>Admin Session</strong> detected. 
                        <br>
                        <a href="admin.php">Go to Admin Dashboard</a> &nbsp;•&nbsp; <a href="login.php?logout=1" style="color:#fca5a5;">Log Out</a>
                    </div>
                <?php endif; ?>

                <?php if($error): ?>
                    <div class="auth-alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <?= csrf_field() ?>
                    
                    <div class="auth-form-group">
                        <label for="username">Email Address or Username</label>
                        <input type="text" id="username" name="username" class="auth-input" placeholder="you@domain.com" required autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="auth-form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="auth-input" placeholder="••••••••" required autocomplete="current-password">
                    </div>

                    <div class="auth-options">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:var(--theme-text-secondary); user-select:none;">
                            <input type="checkbox" name="remember" style="accent-color:var(--theme-primary);">
                            <span>Remember Me</span>
                        </label>
                        <a href="profile.php?tab=forgot">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn-primary auth-submit-btn">Sign In to Account</button>
                </form>

                <div class="auth-footer-links">
                    Don't have an account yet? 
                    <a href="profile.php?tab=register">Create Account</a>
                </div>

                <div style="text-align:center; margin-top:16px;">
                    <a href="cbd.php" style="color:var(--theme-text-muted); font-size:12.5px; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--theme-text-muted)'">
                        ← Return to Storefront
                    </a>
                </div>

            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>