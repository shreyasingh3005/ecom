<?php
// cbd-contact.php - Contact Us with Database Integration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/analytics.php';

Analytics::trackPage('Contact Us | KAMS HEMP');

$storeName = Settings::get('store_name', 'KAMS HEMP');
$supportEmail = Settings::get('support_email', 'support@kamshemp.com');
$supportPhone = Settings::get('support_phone', '+91 98765 43210');
$whatsappNumber = Settings::get('whatsapp_number', '+919876543210');

$successMsg = '';
$errorMsg = '';

// Handle Contact Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_contact') {
    if (!verify_csrf()) {
        $errorMsg = "Security token validation failed. Please refresh the page.";
    } else {
        $contactName = trim($_POST['name'] ?? '');
        $contactEmail = trim($_POST['email'] ?? '');
        $contactPhone = trim($_POST['phone'] ?? '');
        $contactSubject = trim($_POST['subject'] ?? 'Product & Dosage Inquiry');
        $contactMessage = trim($_POST['message'] ?? '');

        if (empty($contactName) || empty($contactEmail) || empty($contactMessage)) {
            $errorMsg = "Please fill in all required fields (Name, Email, Message).";
        } else {
            try {
                // Save to database
                $db = Database::getInstance();
                $stmt = $db->prepare("INSERT INTO `contact_submissions` (`name`, `email`, `phone`, `subject`, `message`, `status`) VALUES (?, ?, ?, ?, ?, 'New')");
                $stmt->execute([$contactName, $contactEmail, $contactPhone, $contactSubject, $contactMessage]);

                // Send email notification to support
                $emailBody = "New message received via KAMS HEMP Contact Form:\n\n" .
                             "Name: {$contactName}\n" .
                             "Email: {$contactEmail}\n" .
                             "Phone: {$contactPhone}\n" .
                             "Subject: {$contactSubject}\n\n" .
                             "Message:\n{$contactMessage}\n";

                Mailer::send($supportEmail, "Contact Form: " . $contactSubject, $emailBody);

                $successMsg = "Thank you, {$contactName}! Your message has been received. Our Ayurvedic team will respond within 24 hours.";
            } catch (Exception $e) {
                $errorMsg = "Failed to send message: " . $e->getMessage();
            }
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
        'title' => 'Contact Us & Ayurvedic Consultation Desk',
        'description' => 'Connect with our certified Ayurvedic Vaidyas, request formulation guidance, or get assistance with your existing order.'
    ]);
    ?>
    <style>
        .contact-hero {
            padding: 60px 0 40px 0;
            text-align: center;
            background: radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.08) 0%, transparent 70%);
        }
        .contact-hero h1 {
            font-family: var(--font-heading);
            font-size: 38px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
        }
        .contact-hero p {
            color: var(--theme-text-secondary);
            font-size: 16px;
            max-width: 620px;
            margin: 0 auto;
        }
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 40px;
            padding: 40px 0 80px 0;
        }
        .contact-card {
            background: var(--theme-surface-card);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-xl);
            padding: 36px;
            box-shadow: var(--shadow-card);
        }
        .contact-info-list {
            display: flex;
            flex-direction: column;
            gap: 24px;
            margin-top: 28px;
        }
        .contact-info-row {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .contact-icon-box {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: rgba(0, 255, 204, 0.1);
            border: 1px solid var(--theme-primary);
            color: var(--theme-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .contact-form-group {
            margin-bottom: 20px;
        }
        .contact-label {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 8px;
        }
        .contact-input-field {
            width: 100%;
            height: 46px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--theme-border);
            border-radius: var(--radius-md);
            padding: 0 16px;
            color: #ffffff;
            font-size: 14.5px;
            outline: none;
            transition: all 0.2s ease;
        }
        .contact-input-field:focus {
            border-color: var(--theme-primary);
            background: rgba(255, 255, 255, 0.07);
            box-shadow: 0 0 12px var(--theme-primary-glow);
        }
        textarea.contact-input-field {
            height: 130px;
            padding: 14px 16px;
            resize: vertical;
        }
        .alert-box-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid #22c55e;
            color: #4ade80;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-box-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #f87171;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
        }
        @media (max-width: 900px) {
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 32px;
            }
            .contact-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="site-content">
        <!-- Hero Section -->
        <section class="contact-hero">
            <div class="theme-container">
                <h1>Consult with Our Ayurvedic Team</h1>
                <p>Have questions about Vijaya leaf legality, custom dosages, doctor prescriptions, or tracking your delivery? We are here to guide your wellness path.</p>
            </div>
        </section>

        <!-- Form and Contact Details Grid -->
        <section>
            <div class="theme-container">
                <div class="contact-grid">
                    <!-- Left: Details Card -->
                    <div class="contact-card">
                        <h2 style="font-family:var(--font-heading); font-size:24px; color:#fff; margin-bottom:10px;">Direct Communication</h2>
                        <p style="color:var(--theme-text-secondary); font-size:14px; line-height:1.6;">Our client support desk and certified Vaidya panel operate Monday through Saturday from 10:00 AM to 7:00 PM IST.</p>

                        <div class="contact-info-list">
                            <div class="contact-info-row">
                                <div class="contact-icon-box">
                                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                </div>
                                <div>
                                    <h4 style="color:#fff; font-size:15px; margin-bottom:2px;">Phone Support</h4>
                                    <p style="color:var(--theme-gold); font-size:14px; font-weight:600;"><a href="tel:<?= htmlspecialchars($supportPhone) ?>"><?= htmlspecialchars($supportPhone) ?></a></p>
                                </div>
                            </div>

                            <div class="contact-info-row">
                                <div class="contact-icon-box">
                                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                </div>
                                <div>
                                    <h4 style="color:#fff; font-size:15px; margin-bottom:2px;">Official Email</h4>
                                    <p style="color:var(--theme-gold); font-size:14px; font-weight:600;"><a href="mailto:<?= htmlspecialchars($supportEmail) ?>"><?= htmlspecialchars($supportEmail) ?></a></p>
                                </div>
                            </div>

                            <div class="contact-info-row">
                                <div class="contact-icon-box" style="background:#25d366; color:#000; border-color:#25d366;">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771z"></path></svg>
                                </div>
                                <div>
                                    <h4 style="color:#fff; font-size:15px; margin-bottom:2px;">WhatsApp Support Desk</h4>
                                    <p style="color:#25d366; font-size:13.5px; font-weight:700;"><a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsappNumber) ?>" target="_blank">Chat with our Clinical Desk →</a></p>
                                </div>
                            </div>

                            <div class="contact-info-row">
                                <div class="contact-icon-box">
                                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                </div>
                                <div>
                                    <h4 style="color:#fff; font-size:15px; margin-bottom:2px;">Licensed Dispensary & Hub</h4>
                                    <p style="color:var(--theme-text-secondary); font-size:13.5px; line-height:1.5;">KAMS Industrial Hemp India Pvt Ltd.<br>Connaught Place, New Delhi 110001, India</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Interactive Submission Form -->
                    <div class="contact-card">
                        <h2 style="font-family:var(--font-heading); font-size:24px; color:#fff; margin-bottom:8px;">Send an Inquiry</h2>
                        <p style="color:var(--theme-text-secondary); font-size:14px; margin-bottom:24px;">Fill in your details and an Ayurvedic consultant will get back to you.</p>

                        <?php if (!empty($successMsg)): ?>
                            <div class="alert-box-success"><?= htmlspecialchars($successMsg) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($errorMsg)): ?>
                            <div class="alert-box-error"><?= htmlspecialchars($errorMsg) ?></div>
                        <?php endif; ?>

                        <form action="cbd-contact.php" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="submit_contact">

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="contact-form-group">
                                    <label class="contact-label" for="contact_name">Full Name *</label>
                                    <input type="text" id="contact_name" name="name" class="contact-input-field" placeholder="Aarav Sharma" required>
                                </div>
                                <div class="contact-form-group">
                                    <label class="contact-label" for="contact_phone">Phone / WhatsApp</label>
                                    <input type="text" id="contact_phone" name="phone" class="contact-input-field" placeholder="+91 98765 43210">
                                </div>
                            </div>

                            <div class="contact-form-group">
                                <label class="contact-label" for="contact_email">Email Address *</label>
                                <input type="email" id="contact_email" name="email" class="contact-input-field" placeholder="aarav@example.com" required>
                            </div>

                            <div class="contact-form-group">
                                <label class="contact-label" for="contact_subject">Subject</label>
                                <input type="text" id="contact_subject" name="subject" class="contact-input-field" placeholder="Dosage consultation / Order assistance">
                            </div>

                            <div class="contact-form-group">
                                <label class="contact-label" for="contact_message">Your Message *</label>
                                <textarea id="contact_message" name="message" class="contact-input-field" placeholder="Describe your requirement, symptoms, or inquiry in detail..." required></textarea>
                            </div>

                            <button type="submit" class="btn-primary" style="width:100%; height:48px; font-size:15px;">
                                Submit Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>