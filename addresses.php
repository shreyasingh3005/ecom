<?php
// addresses.php - Dynamic MySQL User Addresses
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('addresses', 'Saved Addresses');

$isLoggedIn = Auth::isCustomerLoggedIn();
$db = Database::getInstance();
$currentUser = null;
$userId = null;

if ($isLoggedIn) {
    $userId = Auth::getUserId();
    $currentUser = Auth::getUser();
} else {
    // If not logged in, fetch default customer user or redirect
    $stmt = $db->query("SELECT * FROM users ORDER BY id ASC LIMIT 1");
    $currentUser = $stmt->fetch();
    if ($currentUser) {
        $userId = (int)$currentUser['id'];
    }
}

$email = $currentUser['email'] ?? '';
$initials = strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1));

$successMsg = '';
$errorMsg = '';

// Handle Address Form Submissions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errorMsg = "Security token validation failed. Please refresh the page.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_address' && $userId) {
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $street = trim($_POST['street'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $zip = trim($_POST['zip'] ?? '');
            $country = trim($_POST['country'] ?? 'India');

            // If this is the first address, make it default automatically
            $chk = $db->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
            $chk->execute([$userId]);
            if ($chk->fetchColumn() == 0) {
                $is_default = 1;
            }

            // If setting as default, remove default flag from all other addresses
            if ($is_default) {
                $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
            }

            $stmt = $db->prepare("
                INSERT INTO user_addresses (user_id, title, name, phone, street, city, state, zip, country, is_default, created_at)
                VALUES (?, 'Address', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $name, $phone, $street, $city, $state, $zip, $country, $is_default]);
            $successMsg = "Address added successfully.";

        } elseif ($action === 'edit_address' && $userId) {
            $addrId = (int)($_POST['address_id'] ?? 0);
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $street = trim($_POST['street'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $zip = trim($_POST['zip'] ?? '');
            $country = trim($_POST['country'] ?? 'India');

            if ($is_default) {
                $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
            }

            $stmt = $db->prepare("
                UPDATE user_addresses 
                SET name = ?, phone = ?, street = ?, city = ?, state = ?, zip = ?, country = ?, is_default = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$name, $phone, $street, $city, $state, $zip, $country, $is_default, $addrId, $userId]);
            $successMsg = "Address updated successfully.";

        } elseif ($action === 'delete_address' && $userId) {
            $addrId = (int)($_POST['address_id'] ?? 0);
            
            // Check if deleted address was default
            $chk = $db->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ?");
            $chk->execute([$addrId, $userId]);
            $wasDefault = (int)$chk->fetchColumn() === 1;

            $del = $db->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
            $del->execute([$addrId, $userId]);

            // If was default, set the latest remaining as default
            if ($wasDefault) {
                $db->prepare("UPDATE user_addresses SET is_default = 1 WHERE user_id = ? ORDER BY id DESC LIMIT 1")->execute([$userId]);
            }

            $successMsg = "Address deleted successfully.";
        }
    }
}

// Get the updated addresses for display
$savedAddresses = [];
if ($userId) {
    $stmt = $db->prepare("
        SELECT id, is_default, title, name, phone, street, city, state, zip, country
        FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC
    ");
    $stmt->execute([$userId]);
    $savedAddresses = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Addresses | KAMS HEMP</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
        }

        /* 1. Clean Background */
        .fullscreen-bg, .bg-overlay {
            display: none !important;
        }

        /* 3. Seamless Loop Notification Bar */
        .notification-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 35px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 0, 255, 0.3);
            display: flex;
            align-items: center;
            overflow: hidden;
            z-index: 102; 
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .notification-track {
            display: flex;
            width: max-content;
            animation: seamless-marquee 35s linear infinite;
        }

        .notification-track:hover {
            animation-play-state: paused;
        }

        .marquee-content {
            display: flex;
            gap: 50px;
            padding-right: 50px;
        }

        .marquee-content span {
            text-shadow: 0 0 8px rgba(255, 0, 255, 0.6);
            white-space: nowrap; 
        }

        @keyframes seamless-marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); } 
        }

        /* 4. The Header Banner */
        .header-banner {
            position: fixed;
            top: 35px; 
            left: 0;
            width: 100%;
            height: 80px;
            background: rgba(10, 10, 10, 0.4); 
            backdrop-filter: blur(12px); 
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 0 50px;
            box-sizing: border-box;
            z-index: 100;
        }

        .header-nav {
            display: flex;
            gap: 30px;
            height: 100%; 
            align-items: center;
        }

        .header-nav > a, .nav-item-has-mega > a {
            color: #ccc;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .header-nav > a:hover, .nav-item-has-mega > a:hover {
            color: #fff;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
        }

        /* 4.1 Mega Menu */
        .nav-item-has-mega {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .mega-menu {
            position: absolute;
            top: 80px; 
            left: 0;
            width: 50vw; 
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 2px solid rgba(255, 0, 255, 0.6);
            border-radius: 0 0 15px 15px;
            padding: 35px 40px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(15px);
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 20px 40px rgba(0,0,0,0.8);
            cursor: default;
        }

        .mega-menu::before {
            content: '';
            position: absolute;
            top: -20px;
            left: 0;
            width: 100%;
            height: 20px;
            background: transparent;
        }

        .nav-item-has-mega:hover .mega-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .mega-column {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .mega-column h3 {
            color: #fff;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-nav .mega-menu a {
            color: #aaa;
            font-size: 13px;
            font-weight: 500;
            text-transform: capitalize;
            letter-spacing: 0.5px;
            padding: 8px 0;
            transition: all 0.3s ease;
            text-shadow: none;
            display: inline-block;
            width: fit-content;
        }

        .header-nav .mega-menu a:hover {
            color: #fff;
            transform: translateX(8px);
            text-shadow: 0 0 8px rgba(255, 0, 255, 0.4);
        }

        .header-logo {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-img {
            height: 45px; 
            width: auto;
            filter: drop-shadow(0 0 10px rgba(255, 0, 255, 0.6));
        }

        /* 5. Header Icons */
        .header-icons {
            margin-left: auto; 
            display: flex;
            gap: 25px;
            align-items: center;
        }

        .search-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 25px;
            padding: 10px 15px 10px 38px;
            color: #fff;
            font-size: 13px;
            font-family: inherit;
            width: 220px;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            outline: none;
            backdrop-filter: blur(5px);
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
            opacity: 1; 
        }

        .search-input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 0, 255, 0.5);
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.3);
            width: 280px;
        }

        .search-icon-inside {
            position: absolute;
            left: 12px;
            width: 16px;
            height: 16px;
            fill: none;
            stroke: rgba(255, 255, 255, 0.6);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            pointer-events: none; 
            transition: stroke 0.3s ease;
        }

        .search-input:focus + .search-icon-inside {
            stroke: rgba(255, 0, 255, 0.8);
        }

        .header-icons > a {
            color: #ccc;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-icons > a:hover {
            color: #fff;
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.8));
        }

        .header-icons svg:not(.search-icon-inside) {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* 6. Main Content Wrapper */
        .content-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 145px; 
            padding-bottom: 60px;
            box-sizing: border-box;
        }

        /* Alerts */
        .alert-msg {
            width: 100%;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            box-sizing: border-box;
            border: 1px solid transparent;
        }
        .alert-error {
            background: rgba(255, 0, 0, 0.1);
            border-color: rgba(255, 0, 0, 0.4);
            color: #ffb3b3;
        }
        .alert-success {
            background: rgba(0, 255, 0, 0.1);
            border-color: rgba(0, 255, 0, 0.4);
            color: #b3ffb3;
        }

        /* 7. DASHBOARD & SAVED ADDRESSES STYLES */
        .dashboard-container {
            width: 95%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 40px;
            margin-top: 40px;
        }

        .dashboard-sidebar {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px 0;
            height: fit-content;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
        }

        .user-summary {
            text-align: center;
            padding: 0 30px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.4), rgba(138, 43, 226, 0.4));
            border: 2px solid rgba(255, 255, 255, 0.2);
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            color: #fff;
            box-shadow: 0 0 20px rgba(255, 0, 255, 0.2);
        }

        .user-summary h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .user-summary p {
            margin: 0;
            font-size: 13px;
            color: #aaa;
        }

        .dashboard-nav {
            display: flex;
            flex-direction: column;
        }

        .dashboard-nav a {
            padding: 15px 30px;
            color: #ccc;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 1px;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-nav a svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .dashboard-nav a:hover, .dashboard-nav a.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
            border-left-color: rgba(255, 0, 255, 0.6);
        }

        .dashboard-content {
            background: rgba(15, 15, 15, 0.55);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
        }

        .dashboard-header {
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-header-text h2 {
            margin: 0 0 5px 0;
            font-size: 24px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #fff;
        }

        .dashboard-header-text p {
            margin: 0;
            color: #aaa;
            font-size: 14px;
        }

        .btn-solid {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.6) 0%, rgba(138, 43, 226, 0.6) 100%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-solid:hover {
            background: linear-gradient(135deg, rgba(255, 0, 255, 0.8) 0%, rgba(138, 43, 226, 0.8) 100%);
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.4);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
        }

        .btn-outline:hover {
            border-color: rgba(255, 0, 255, 0.6);
            color: #fff;
            background: rgba(255, 0, 255, 0.1);
        }

        /* Address Grid & Cards */
        .address-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .address-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: relative;
        }

        .address-card.default-card {
            border-color: rgba(229, 195, 120, 0.5); /* Gold outline for default */
            background: rgba(229, 195, 120, 0.05);
        }

        .address-card:hover {
            border-color: rgba(255, 0, 255, 0.4);
            box-shadow: 0 5px 20px rgba(0,0,0,0.4);
            transform: translateY(-2px);
        }

        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
        }

        .address-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 15px;
            color: #fff;
            letter-spacing: 1px;
        }

        .address-label svg {
            width: 18px;
            height: 18px;
            stroke: #e5c378;
            fill: none;
            stroke-width: 2;
        }

        .default-badge {
            background: rgba(229, 195, 120, 0.2);
            color: #e5c378;
            border: 1px solid #e5c378;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .address-details {
            flex-grow: 1;
            margin-bottom: 20px;
        }

        .address-details p {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #ccc;
            line-height: 1.5;
        }

        .address-details p strong {
            color: #fff;
            font-weight: 500;
        }

        .address-actions {
            display: flex;
            gap: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 15px;
        }

        .add-address-card {
            border: 2px dashed rgba(255, 255, 255, 0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            min-height: 250px;
        }

        .add-address-card:hover {
            border-color: rgba(255, 0, 255, 0.6);
            background: rgba(255, 0, 255, 0.05);
        }

        .add-address-card svg {
            width: 40px;
            height: 40px;
            stroke: #fff;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .add-address-card:hover svg {
            stroke: rgba(255, 0, 255, 0.8);
            transform: scale(1.1);
        }

        .add-address-card span {
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 1px;
            color: #fff;
        }

        /* 8. MODAL STYLES */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: rgba(15, 15, 15, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .close-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
        }

        .input-group {
            margin-bottom: 15px;
            width: 100%;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            color: #ccc;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            padding: 10px 12px;
            color: #fff;
            font-size: 14px;
            font-family: inherit;
            box-sizing: border-box;
            transition: all 0.3s ease;
            outline: none;
        }
        
        select.form-input option {
            background: #151515;
            color: #fff;
        }

        .form-input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 0, 255, 0.5);
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.2);
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            margin-bottom: 10px;
            font-size: 13px;
            color: #ccc;
        }

        .checkbox-group input {
            appearance: none;
            width: 16px;
            height: 16px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 3px;
            background: rgba(0,0,0,0.2);
            cursor: pointer;
            position: relative;
        }

        .checkbox-group input:checked {
            background: rgba(255, 0, 255, 0.6);
            border-color: rgba(255, 0, 255, 0.8);
        }

        .checkbox-group input:checked::after {
            content: '✔';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 10px;
            color: white;
        }

        @media (max-width: 900px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }

        /* 10. Footer Styles */
        .site-footer {
            width: 100%;
            background: rgba(10, 10, 10, 0.7);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #ccc;
            padding: 60px 0 20px;
            margin-top: auto; 
            position: relative;
            z-index: 10;
        }

        .footer-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
            text-align: left;
        }

        .footer-column h3 {
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 20px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .footer-column p {
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 15px;
            color: rgba(255, 255, 255, 0.6);
            text-shadow: none;
        }

        .footer-logo-text {
            color: #fff;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-decoration: none;
            text-shadow: 0 0 10px rgba(255, 0, 255, 0.6);
            display: inline-block;
            margin-bottom: 15px;
        }

        .footer-column nav {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-column nav a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
            width: fit-content;
        }

        .footer-column nav a:hover {
            color: #fff;
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.8);
            transform: translateX(5px);
        }

        .footer-subscribe {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 5px;
            margin-top: 10px;
        }

        .footer-subscribe input {
            background: transparent;
            border: none;
            color: #fff;
            padding: 8px 15px;
            font-size: 13px;
            outline: none;
            width: 100%;
        }

        .footer-subscribe input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .footer-subscribe button {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 15px;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .footer-subscribe button:hover {
            background: rgba(255, 0, 255, 0.6);
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.4);
        }

        .footer-subscribe button svg {
            width: 14px;
            height: 14px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
        }

        .footer-bottom {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-bottom p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            margin: 0;
            text-shadow: none;
        }

        .social-icons {
            display: flex;
            gap: 15px;
        }

        .social-icons a {
            color: rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            color: #fff;
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.8));
            transform: translateY(-2px);
        }

        .social-icons svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">

    <div class="content-wrapper">

        <div class="dashboard-container">
            
            <!-- Sidebar -->
            <aside class="dashboard-sidebar">
                <div class="user-summary">
                    <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
                    <h3><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h3>
                    <p><?= htmlspecialchars($currentUser['email']) ?></p>
                </div>
                <nav class="dashboard-nav">
                    <a href="profile.php">
                        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        Account Details
                    </a>
                    <a href="orders.php">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        Order History
                    </a>
                    <a href="addresses.php" class="active">
                        <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        Saved Addresses
                    </a>
                    <a href="profile.php?logout=1" style="color: #ff4d4d;">
                        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        Log Out
                    </a>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="dashboard-content">
                <div class="dashboard-header">
                    <div class="dashboard-header-text">
                        <h2>Saved Addresses</h2>
                        <p>Manage your shipping and billing addresses for faster checkout.</p>
                    </div>
                </div>

                <?php if(!empty($errorMsg)): ?>
                    <div class="alert-msg alert-error"><?= htmlspecialchars($errorMsg) ?></div>
                <?php endif; ?>
                
                <?php if(!empty($successMsg)): ?>
                    <div class="alert-msg alert-success"><?= htmlspecialchars($successMsg) ?></div>
                <?php endif; ?>

                <div class="address-grid">
                    <?php foreach ($savedAddresses as $address): ?>
                        <div class="address-card <?= $address['is_default'] ? 'default-card' : '' ?>">
                            <div class="address-header">
                                <div class="address-label">
                                    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                    Address
                                </div>
                                <?php if ($address['is_default']): ?>
                                    <span class="default-badge">Default</span>
                                <?php endif; ?>
                            </div>
                            <div class="address-details">
                                <p><strong><?= htmlspecialchars($address['name']) ?></strong></p>
                                <p><?= htmlspecialchars($address['street']) ?></p>
                                <p><?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?> <?= htmlspecialchars($address['zip']) ?></p>
                                <p><?= htmlspecialchars($address['country']) ?></p>
                                <p>Phone: <?= htmlspecialchars($address['phone']) ?></p>
                            </div>
                            <div class="address-actions">
                                <a href="#" class="btn-outline" onclick="openModal('edit', <?= htmlspecialchars(json_encode($address)) ?>); return false;">Edit</a>
                                <a href="#" class="btn-outline" style="color: #ffb3b3; border-color: rgba(255, 0, 0, 0.3);" onclick="deleteAddress('<?= $address['id'] ?>'); return false;">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Add New Address Card -->
                    <div class="address-card add-address-card" onclick="openModal('add'); return false;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Add New Address</span>
                    </div>

                </div>
            </main>

        </div>

    </div>

    <!-- Address Modal Form -->
    <div id="addressModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Address</h3>
                <button type="button" class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="addressForm" method="POST" action="addresses.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="formAction" value="add_address">
                <input type="hidden" name="address_id" id="addressId" value="">
                
                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="addrName" class="form-input" required>
                </div>

                <div class="input-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" id="addrPhone" class="form-input" pattern="^(?!00)\d{10}$" title="10 digits exactly, no spaces or special characters, cannot start with 00" required>
                </div>

                <div class="input-group">
                    <label>Street Address</label>
                    <input type="text" name="street" id="addrStreet" class="form-input" required>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label>City</label>
                        <input type="text" name="city" id="addrCity" class="form-input" required>
                    </div>
                    <div class="input-group">
                        <label>State</label>
                        <select name="state" id="addrState" class="form-input" required>
                            <option value="" disabled selected>Select State</option>
                            <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                            <option value="Andhra Pradesh">Andhra Pradesh</option>
                            <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                            <option value="Assam">Assam</option>
                            <option value="Bihar">Bihar</option>
                            <option value="Chandigarh">Chandigarh</option>
                            <option value="Chhattisgarh">Chhattisgarh</option>
                            <option value="Dadra and Nagar Haveli">Dadra and Nagar Haveli</option>
                            <option value="Daman and Diu">Daman and Diu</option>
                            <option value="Delhi">Delhi</option>
                            <option value="Goa">Goa</option>
                            <option value="Gujarat">Gujarat</option>
                            <option value="Haryana">Haryana</option>
                            <option value="Himachal Pradesh">Himachal Pradesh</option>
                            <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                            <option value="Jharkhand">Jharkhand</option>
                            <option value="Karnataka">Karnataka</option>
                            <option value="Kerala">Kerala</option>
                            <option value="Ladakh">Ladakh</option>
                            <option value="Lakshadweep">Lakshadweep</option>
                            <option value="Madhya Pradesh">Madhya Pradesh</option>
                            <option value="Maharashtra">Maharashtra</option>
                            <option value="Manipur">Manipur</option>
                            <option value="Meghalaya">Meghalaya</option>
                            <option value="Mizoram">Mizoram</option>
                            <option value="Nagaland">Nagaland</option>
                            <option value="Odisha">Odisha</option>
                            <option value="Puducherry">Puducherry</option>
                            <option value="Punjab">Punjab</option>
                            <option value="Rajasthan">Rajasthan</option>
                            <option value="Sikkim">Sikkim</option>
                            <option value="Tamil Nadu">Tamil Nadu</option>
                            <option value="Telangana">Telangana</option>
                            <option value="Tripura">Tripura</option>
                            <option value="Uttar Pradesh">Uttar Pradesh</option>
                            <option value="Uttarakhand">Uttarakhand</option>
                            <option value="West Bengal">West Bengal</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label>Zip Code</label>
                        <input type="text" name="zip" id="addrZip" class="form-input" pattern="\d{6}" title="Exactly 6 digits required" required>
                    </div>
                    <div class="input-group">
                        <label>Country</label>
                        <select name="country" id="addrCountry" class="form-input" required>
                            <option value="India" selected>India</option>
                            <option value="Nepal">Nepal</option>
                            <option value="Bhutan">Bhutan</option>
                            <option value="Sri Lanka">Sri Lanka</option>
                        </select>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="is_default" id="addrDefault" value="1">
                    <label for="addrDefault" style="margin:0; text-transform:none; font-size:13px;">Set as default address</label>
                </div>

                <button type="submit" class="btn-solid" style="width: 100%; margin-top: 15px; text-align:center;">Save Address</button>
            </form>
        </div>
    </div>

    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
        function openModal(mode, data = null) {
            const modal = document.getElementById('addressModal');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('formAction');
            const form = document.getElementById('addressForm');
            
            form.reset();
            
            if (mode === 'add') {
                title.innerText = 'Add New Address';
                action.value = 'add_address';
                document.getElementById('addressId').value = '';
            } else if (mode === 'edit' && data) {
                title.innerText = 'Edit Address';
                action.value = 'edit_address';
                document.getElementById('addressId').value = data.id;
                
                document.getElementById('addrName').value = data.name;
                document.getElementById('addrPhone').value = data.phone;
                document.getElementById('addrStreet').value = data.street;
                document.getElementById('addrCity').value = data.city;
                document.getElementById('addrState').value = data.state;
                document.getElementById('addrZip').value = data.zip;
                document.getElementById('addrCountry').value = data.country;
                document.getElementById('addrDefault').checked = data.is_default;
            }
            
            modal.style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('addressModal').style.display = 'none';
        }

        function deleteAddress(id) {
            if (confirm("Are you sure you want to delete this address?")) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'addresses.php';
                form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="delete_address"><input type="hidden" name="address_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addressModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Advanced Search Typist
        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById('auto-search');
            
            const phrases = [
                "Search 'Vijaya Extract'...", 
                "Search 'Sleep Drops'...", 
                "Search 'Pain Relief'...", 
                "Search 'Full Spectrum'..."
            ];
            
            let phraseIndex = 0;
            let charIndex = 0;
            let isDeleting = false;

            function typeEffect() {
                const currentPhrase = phrases[phraseIndex];
                
                if (isDeleting) {
                    searchInput.placeholder = currentPhrase.substring(0, charIndex - 1);
                    charIndex--;
                } else {
                    searchInput.placeholder = currentPhrase.substring(0, charIndex + 1);
                    charIndex++;
                }

                let typeSpeed = isDeleting ? 40 : 80;

                if (!isDeleting && charIndex === currentPhrase.length) {
                    typeSpeed = 2000; 
                    isDeleting = true;
                } 
                else if (isDeleting && charIndex === 0) {
                    isDeleting = false;
                    phraseIndex = (phraseIndex + 1) % phrases.length;
                    typeSpeed = 500; 
                }

                setTimeout(typeEffect, typeSpeed);
            }

            setTimeout(typeEffect, 1000);
        });
    </script>
    <script src="assets/js/responsive.js"></script>
</body>
</html>