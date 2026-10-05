<?php
// includes/b2b_service.php
// Full B2B Wholesale Portal Service: Inquiries, Catalog Management & Admin Lead Tracking

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/mailer.php';

class B2BService {

    /**
     * Ensure the b2b_inquiries table exists in database
     */
    public static function initTable(): void {
        static $initialized = false;
        if ($initialized) return;

        try {
            $db = Database::getInstance();
            $db->exec("
                CREATE TABLE IF NOT EXISTS `b2b_inquiries` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `company_name` VARCHAR(150) NOT NULL,
                    `contact_person` VARCHAR(100) NOT NULL,
                    `email` VARCHAR(191) NOT NULL,
                    `phone` VARCHAR(25) NOT NULL,
                    `gst_number` VARCHAR(50) NULL,
                    `city` VARCHAR(100) NOT NULL,
                    `state` VARCHAR(100) NOT NULL,
                    `business_type` VARCHAR(100) NOT NULL,
                    `estimated_monthly_volume` VARCHAR(100) NULL,
                    `attachment_path` VARCHAR(255) NULL,
                    `message` TEXT NULL,
                    `status` ENUM('New', 'Contacted', 'In Discussion', 'Approved', 'Rejected') DEFAULT 'New',
                    `admin_notes` TEXT NULL,
                    `ip_address` VARCHAR(45) NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (`status`),
                    INDEX (`created_at`),
                    INDEX (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $initialized = true;
        } catch (\Throwable $e) {
            error_log("Failed to initialize b2b_inquiries table: " . $e->getMessage());
        }
    }

    /**
     * Create a new B2B Wholesale Inquiry / Application
     */
    public static function createInquiry(array $data, ?array $file = null): array {
        self::initTable();
        $db = Database::getInstance();

        $company = trim($data['company_name'] ?? '');
        $contact = trim($data['contact_person'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $phone = trim($data['phone'] ?? '');
        $gst = strtoupper(trim($data['gst_number'] ?? ''));
        $city = trim($data['city'] ?? '');
        $state = trim($data['state'] ?? '');
        $businessType = trim($data['business_type'] ?? 'Retailer');
        $estVolume = trim($data['estimated_monthly_volume'] ?? '');
        $message = trim($data['message'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Validation
        if (empty($company) || empty($contact) || empty($email) || empty($phone) || empty($city) || empty($state)) {
            return ['success' => false, 'error' => 'Please fill in all mandatory business fields.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please provide a valid business email address.'];
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) < 10) {
            return ['success' => false, 'error' => 'Please enter a valid 10-digit mobile number.'];
        }

        // GST Validation if provided
        if (!empty($gst)) {
            // Optional basic GST format check (15 chars)
            if (strlen($gst) !== 15 && strlen($gst) < 10) {
                return ['success' => false, 'error' => 'GSTIN must be a valid 15-character number. Leave blank if not yet registered.'];
            }
        }

        // Handle File Attachment (Visiting Card / GST Cert / Drug License)
        $attachmentPath = null;
        if (!empty($file['name']) && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedExts)) {
                return ['success' => false, 'error' => 'Attachment must be a PDF, JPG, PNG, or WEBP document.'];
            }

            // Max 10MB
            if ($file['size'] > 10 * 1024 * 1024) {
                return ['success' => false, 'error' => 'File size exceeds 10MB limit.'];
            }

            $uploadDir = __DIR__ . '/../uploads/b2b/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $newFileName = 'b2b_doc_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $attachmentPath = 'uploads/b2b/' . $newFileName;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO `b2b_inquiries` (
                    `company_name`, `contact_person`, `email`, `phone`, `gst_number`,
                    `city`, `state`, `business_type`, `estimated_monthly_volume`,
                    `attachment_path`, `message`, `status`, `ip_address`, `created_at`
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, 'New', ?, NOW()
                )
            ");
            $stmt->execute([
                $company, $contact, $email, $phone, $gst,
                $city, $state, $businessType, $estVolume,
                $attachmentPath, $message, $ip
            ]);

            $inquiryId = (int)$db->lastInsertId();

            // Dispatch notification email to store admin
            try {
                $storeName = Settings::get('store_name', 'KAMS HEMP');
                $adminEmail = Settings::get('support_email', env('MAIL_FROM', 'support@kamshemp.com'));
                $subject = "⚡ New B2B Wholesale Lead #{$inquiryId} - {$company}";

                $emailHtml = "
                <div style='font-family: Arial, sans-serif; background: #0c0c0c; color: #fff; padding: 25px; border-radius: 10px; max-width: 600px; margin: auto;'>
                    <h2 style='color: #00ffcc; margin-top: 0;'>New B2B Wholesale Application Received</h2>
                    <p style='color: #cbd5e1;'>A new wholesale distributor / retailer application has been submitted on {$storeName}.</p>
                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0; color: #eee; font-size: 14px;'>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #222; color: #94a3b8;'>Company Name:</td><td style='padding: 8px; border-bottom: 1px solid #222; font-weight: bold;'>{$company}</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #222; color: #94a3b8;'>Contact Person:</td><td style='padding: 8px; border-bottom: 1px solid #222;'>{$contact}</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #222; color: #94a3b8;'>Email:</td><td style='padding: 8px; border-bottom: 1px solid #222;'><a href='mailto:{$email}' style='color: #60a5fa;'>{$email}</a></td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #222; color: #94a3b8;'>Phone / WhatsApp:</td><td style='padding: 8px; border-bottom: 1px solid #222;'>{$phone}</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #222; color: #94a3b8;'>GSTIN:</td><td style='padding: 8px; border-bottom: 1px solid #222;'>" . ($gst ?: 'Not Provided') . "</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #222; color: #94a3b8;'>Business Type:</td><td style='padding: 8px; border-bottom: 1px solid #222;'>{$businessType}</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #222; color: #94a3b8;'>Location:</td><td style='padding: 8px; border-bottom: 1px solid #222;'>{$city}, {$state}</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #222; color: #94a3b8;'>Est. Monthly Volume:</td><td style='padding: 8px; border-bottom: 1px solid #222;'>{$estVolume}</td></tr>
                        " . (!empty($attachmentPath) ? "<tr><td style='padding: 8px; border-bottom: 1px solid #222; color: #94a3b8;'>Uploaded Document:</td><td style='padding: 8px; border-bottom: 1px solid #222;'><a href='" . getBaseUrl() . "/{$attachmentPath}' style='color: #4ade80;'>View Document</a></td></tr>" : "") . "
                    </table>
                    " . (!empty($message) ? "<p style='background: #181824; padding: 12px; border-radius: 8px; color: #e2e8f0; font-size: 13px;'><strong>Message/Requirement:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>" : "") . "
                    <p style='color: #888; font-size: 12px; margin-top: 20px;'>Log in to the Admin Panel to view and update this lead.</p>
                </div>";

                Mailer::send($adminEmail, $subject, $emailHtml);
            } catch (\Throwable $mEx) {
                error_log("B2B Admin Alert Email Error: " . $mEx->getMessage());
            }

            return [
                'success' => true,
                'inquiry_id' => $inquiryId,
                'message' => 'Your wholesale inquiry has been submitted successfully! Our B2B onboarding team will connect with you within 24 hours.'
            ];

        } catch (\Throwable $e) {
            error_log("B2B Inquiry insertion failed: " . $e->getMessage());
            return ['success' => false, 'error' => 'Unable to submit your application right now. Please try again or WhatsApp our B2B desk directly.'];
        }
    }

    /**
     * Get B2B Inquiries with filters, search, and pagination
     */
    public static function getInquiries(array $filters = []): array {
        self::initTable();
        $db = Database::getInstance();

        $sql = "SELECT * FROM `b2b_inquiries` WHERE 1=1";
        $params = [];

        if (!empty($filters['status']) && $filters['status'] !== 'All') {
            $sql .= " AND `status` = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['business_type']) && $filters['business_type'] !== 'All') {
            $sql .= " AND `business_type` = ?";
            $params[] = $filters['business_type'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $sql .= " AND (`company_name` LIKE ? OR `contact_person` LIKE ? OR `email` LIKE ? OR `phone` LIKE ? OR `city` LIKE ? OR `gst_number` LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY `id` DESC";

        if (!empty($filters['limit'])) {
            $limit = (int)$filters['limit'];
            $offset = (int)($filters['offset'] ?? 0);
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single inquiry by ID
     */
    public static function getInquiryById(int $id): ?array {
        self::initTable();
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM `b2b_inquiries` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update inquiry status and admin internal notes
     */
    public static function updateInquiry(int $id, string $status, ?string $adminNotes = null): bool {
        self::initTable();
        $db = Database::getInstance();
        $allowedStatuses = ['New', 'Contacted', 'In Discussion', 'Approved', 'Rejected'];
        if (!in_array($status, $allowedStatuses)) {
            $status = 'New';
        }

        $stmt = $db->prepare("
            UPDATE `b2b_inquiries`
            SET `status` = ?, `admin_notes` = COALESCE(?, `admin_notes`), `updated_at` = NOW()
            WHERE `id` = ?
        ");
        return $stmt->execute([$status, $adminNotes, $id]);
    }

    /**
     * Delete an inquiry
     */
    public static function deleteInquiry(int $id): bool {
        self::initTable();
        $db = Database::getInstance();

        // Optionally remove file
        $inq = self::getInquiryById($id);
        if ($inq && !empty($inq['attachment_path'])) {
            $fullPath = __DIR__ . '/../' . $inq['attachment_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $stmt = $db->prepare("DELETE FROM `b2b_inquiries` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get lead counts for stats cards & badges
     */
    public static function getInquiryStats(): array {
        self::initTable();
        $db = Database::getInstance();

        $stats = [
            'total' => 0,
            'new' => 0,
            'contacted' => 0,
            'in_discussion' => 0,
            'approved' => 0,
            'rejected' => 0
        ];

        try {
            $stmt = $db->query("SELECT `status`, COUNT(*) as cnt FROM `b2b_inquiries` GROUP BY `status`");
            while ($row = $stmt->fetch()) {
                $cnt = (int)$row['cnt'];
                $stats['total'] += $cnt;
                $st = strtolower(str_replace(' ', '_', $row['status']));
                if (isset($stats[$st])) {
                    $stats[$st] = $cnt;
                }
            }
        } catch (\Throwable $e) {
            error_log("Failed to fetch B2B stats: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get catalog information for B2B portal download
     */
    public static function getCatalogInfo(): array {
        $pdfPath = Settings::get('b2b_catalog_pdf', '');
        $title = Settings::get('b2b_catalog_title', 'KAMS HEMP B2B Wholesale Catalog 2026');
        $size = Settings::get('b2b_catalog_size', '');
        $updated = Settings::get('b2b_catalog_updated', '');

        $hasFile = false;
        if (!empty($pdfPath)) {
            $absolutePath = __DIR__ . '/../' . ltrim($pdfPath, '/');
            if (file_exists($absolutePath)) {
                $hasFile = true;
                if (empty($size)) {
                    $bytes = filesize($absolutePath);
                    $size = round($bytes / (1024 * 1024), 2) . ' MB';
                }
                if (empty($updated)) {
                    $updated = date('M d, Y', filemtime($absolutePath));
                }
            }
        }

        return [
            'file_path' => $pdfPath,
            'exists' => $hasFile,
            'title' => $title,
            'size' => $size ?: '4.5 MB',
            'updated' => $updated ?: date('M Y')
        ];
    }

    /**
     * Handle Admin upload of B2B Wholesale Catalog (PDF / Image)
     */
    public static function uploadCatalog(array $file, string $title = ''): array {
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No valid file uploaded.'];
        }

        $allowedExts = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            return ['success' => false, 'error' => 'File must be a PDF document or high-res image (PNG/JPG/WEBP).'];
        }

        // Max 50MB
        if ($file['size'] > 50 * 1024 * 1024) {
            return ['success' => false, 'error' => 'File size exceeds 50MB limit.'];
        }

        $uploadDir = __DIR__ . '/../uploads/b2b/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $fileName = 'kams_b2b_catalog_' . date('Ymd_His') . '.' . $ext;
        $dest = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $relPath = 'uploads/b2b/' . $fileName;
            $fileSizeBytes = filesize($dest);
            $sizeStr = $fileSizeBytes >= 1048576 
                ? round($fileSizeBytes / 1048576, 2) . ' MB' 
                : round($fileSizeBytes / 1024, 1) . ' KB';
            $dateStr = date('M d, Y');

            Settings::set('b2b_catalog_pdf', $relPath);
            Settings::set('b2b_catalog_size', $sizeStr);
            Settings::set('b2b_catalog_updated', $dateStr);

            if (!empty($title)) {
                Settings::set('b2b_catalog_title', trim($title));
            }

            return [
                'success' => true,
                'file_path' => $relPath,
                'size' => $sizeStr,
                'updated' => $dateStr
            ];
        }

        return ['success' => false, 'error' => 'Failed to save uploaded file. Check server folder permissions.'];
    }
}
