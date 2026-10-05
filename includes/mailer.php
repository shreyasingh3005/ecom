<?php
// includes/mailer.php
// Robust Reusable SMTP Mailer Service with Full Logging

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';

class Mailer {
    public static function getInstance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    public static function send($to, $subject, $htmlMessage, $altText = '') {
        $to = trim($to);
        $subject = trim($subject);
        $smtpHost = env('SMTP_HOST', '');
        $smtpPort = (int)env('SMTP_PORT', 587);
        $smtpUser = env('SMTP_USERNAME', '');
        $smtpPass = env('SMTP_PASSWORD', '');
        $smtpEnc  = strtolower(env('SMTP_ENCRYPTION', 'tls'));
        $fromEmail = env('MAIL_FROM', 'noreply@kamshemp.com');
        $fromName  = env('MAIL_FROM_NAME', 'KAMS HEMP');

        $sent = false;
        $errorMsg = null;

        // If credentials are provided, attempt socket SMTP delivery
        if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
            try {
                $sent = self::sendViaSocketSMTP($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpEnc, $fromEmail, $fromName, $to, $subject, $htmlMessage);
            } catch (Exception $e) {
                $errorMsg = "SMTP Socket Error: " . $e->getMessage();
                // Fallback to native mail()
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
                $sent = @mail($to, $subject, $htmlMessage, $headers);
            }
        } else {
            // Local development or unconfigured SMTP: use mail()
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
            $sent = @mail($to, $subject, $htmlMessage, $headers);
            if (!$sent) {
                $errorMsg = "PHP mail() returned false (SMTP credentials unconfigured).";
            }
        }

        // Log to email_logs
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO `email_logs` (`recipient`, `subject`, `status`, `error_message`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$to, $subject, $sent ? 'sent' : 'failed', $sent ? null : $errorMsg]);
        } catch (Exception $e) {
            // Silently ignore log errors
        }

        return $sent;
    }

    private static function sendViaSocketSMTP($host, $port, $user, $pass, $encryption, $fromEmail, $fromName, $to, $subject, $htmlBody) {
        $timeout = 15;
        $socket = fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            throw new Exception("Could not connect to {$host}:{$port} - {$errstr} ({$errno})");
        }

        self::readResponse($socket);

        // EHLO
        self::sendCommand($socket, "EHLO " . gethostname());

        // STARTTLS if needed
        if ($encryption === 'tls') {
            self::sendCommand($socket, "STARTTLS");
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Failed to establish TLS encryption with SMTP server.");
            }
            self::sendCommand($socket, "EHLO " . gethostname());
        }

        // AUTH LOGIN
        self::sendCommand($socket, "AUTH LOGIN");
        self::sendCommand($socket, base64_encode($user));
        self::sendCommand($socket, base64_encode($pass));

        // Envelope
        self::sendCommand($socket, "MAIL FROM: <{$fromEmail}>");
        self::sendCommand($socket, "RCPT TO: <{$to}>");

        // DATA
        self::sendCommand($socket, "DATA");

        $boundary = md5(time());
        $message  = "MIME-Version: 1.0\r\n";
        $message .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
        $message .= "To: <{$to}>\r\n";
        $message .= "Date: " . date('r') . "\r\n";
        $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $htmlBody . "\r\n.\r\n";

        fwrite($socket, $message);
        self::readResponse($socket);

        // QUIT
        self::sendCommand($socket, "QUIT");
        fclose($socket);

        return true;
    }

    private static function sendCommand($socket, $command) {
        fwrite($socket, $command . "\r\n");
        return self::readResponse($socket);
    }

    private static function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if ($code >= 400) {
            throw new Exception("SMTP Error ({$code}): {$response}");
        }
        return $response;
    }

    // --- TEMPLATED HELPERS ---

    public static function sendOtp($toEmail, $name, $otp) {
        $subject = "Your Verification Code - " . env('APP_NAME', 'KAMS HEMP');
        $body = "<div style='font-family: Arial, sans-serif; background: #111; color: #fff; padding: 30px; border-radius: 10px;'>
            <h2 style='color: #e5c378;'>Welcome to " . htmlspecialchars(env('APP_NAME', 'KAMS HEMP')) . "</h2>
            <p>Hello " . htmlspecialchars($name) . ",</p>
            <p>Your verification code is:</p>
            <div style='background: #222; border: 1px dashed #ff00ff; color: #ff00ff; font-size: 32px; font-weight: bold; letter-spacing: 5px; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                {$otp}
            </div>
            <p>Please enter this code on the website to complete your verification.</p>
            <p style='color: #888; font-size: 12px;'>If you did not request this, please ignore this email.</p>
        </div>";
        return self::send($toEmail, $subject, $body);
    }

    public static function sendOrderConfirmation($toEmail, $order, $items = []) {
        $subject = "Order Confirmed: #{$order['order_number']} - " . env('APP_NAME', 'KAMS HEMP');
        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= "<tr>
                <td style='padding: 8px; border-bottom: 1px solid #333;'>{$item['product_name']} (x{$item['quantity']})</td>
                <td style='padding: 8px; border-bottom: 1px solid #333; text-align: right;'>₹" . number_format($item['subtotal'], 2) . "</td>
            </tr>";
        }

        $accountInfoHtml = '';
        if (!empty($order['temp_password'])) {
            $refCodeHtml = !empty($order['referral_code']) ? "<p style='margin: 4px 0;'><strong>Your Referral Code:</strong> <span style='color: #e5c378; font-weight: bold;'>" . htmlspecialchars($order['referral_code']) . "</span></p>" : '';
            $accountInfoHtml = "
            <div style='background: #111827; border: 1px solid #3b82f6; border-radius: 8px; padding: 16px; margin: 20px 0;'>
                <h3 style='color: #60a5fa; margin-top: 0; font-size: 15px;'>🎉 Customer Account Created!</h3>
                <p style='color: #cbd5e1; font-size: 13px; margin: 4px 0;'>An account has been automatically created for you so you can track your shipment and earn referral rewards.</p>
                <p style='margin: 4px 0; font-size: 13px;'><strong>Login Email:</strong> " . htmlspecialchars($toEmail) . "</p>
                <p style='margin: 4px 0; font-size: 13px;'><strong>Temporary Password:</strong> <code style='background: #1f2937; color: #00ffcc; padding: 2px 6px; border-radius: 4px;'>" . htmlspecialchars($order['temp_password']) . "</code></p>
                {$refCodeHtml}
                <p style='color: #94a3b8; font-size: 12px; margin-top: 8px;'>You can log in and update your password anytime under your Account Profile.</p>
            </div>";
        }

        $body = "<div style='font-family: Arial, sans-serif; background: #0c0c0c; color: #fff; padding: 30px; border-radius: 10px; max-width: 600px; margin: auto;'>
            <h2 style='color: #e5c378; margin-top: 0;'>Thank You for Your Order!</h2>
            <p>Hi " . htmlspecialchars($order['first_name']) . ", your order <strong>#{$order['order_number']}</strong> has been received and is currently being processed.</p>
            {$accountInfoHtml}
            <table style='width: 100%; border-collapse: collapse; margin: 20px 0; color: #ccc;'>
                {$itemsHtml}
                <tr>
                    <td style='padding: 10px; font-weight: bold;'>Total Amount:</td>
                    <td style='padding: 10px; font-weight: bold; text-align: right; color: #b3ffb3;'>₹" . number_format($order['total_amount'], 2) . "</td>
                </tr>
            </table>
            <p style='color: #aaa; font-size: 13px;'>Shipping Address: " . htmlspecialchars($order['shipping_address']) . "</p>
            <p style='color: #888; font-size: 11px; border-top: 1px solid #222; padding-top: 15px;'>Kams Industrial Hemp India Pvt Ltd. Ministry of AYUSH Certified.</p>
        </div>";
        return self::send($toEmail, $subject, $body);
    }
}
