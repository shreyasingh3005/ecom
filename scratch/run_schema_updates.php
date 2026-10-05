<?php
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $db = Database::getInstance();
    
    // 1. Create testimonials table
    $db->exec("CREATE TABLE IF NOT EXISTS `testimonials` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `customer_name` VARCHAR(100) NOT NULL,
        `rating` INT NOT NULL DEFAULT 5,
        `review` TEXT NOT NULL,
        `product_name` VARCHAR(255) NULL,
        `location` VARCHAR(100) NULL,
        `is_verified` TINYINT(1) DEFAULT 1,
        `status` ENUM('Published', 'Pending', 'Archived') DEFAULT 'Published',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Create faqs table
    $db->exec("CREATE TABLE IF NOT EXISTS `faqs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category` VARCHAR(50) NOT NULL DEFAULT 'General',
        `question` VARCHAR(255) NOT NULL,
        `answer` TEXT NOT NULL,
        `sort_order` INT DEFAULT 0,
        `status` ENUM('Published', 'Draft') DEFAULT 'Published',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`category`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Create blogs table
    $db->exec("CREATE TABLE IF NOT EXISTS `blogs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NOT NULL UNIQUE,
        `excerpt` TEXT NULL,
        `content` LONGTEXT NOT NULL,
        `featured_image` VARCHAR(255) NULL,
        `category` VARCHAR(100) DEFAULT 'Ayurvedic Science',
        `tags` VARCHAR(255) NULL,
        `author` VARCHAR(100) DEFAULT 'Dr. KAMS Ayurvedic Board',
        `seo_title` VARCHAR(255) NULL,
        `seo_description` TEXT NULL,
        `status` ENUM('Published', 'Draft') DEFAULT 'Published',
        `view_count` INT DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`slug`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Create banners table
    $db->exec("CREATE TABLE IF NOT EXISTS `banners` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `subtitle` TEXT NULL,
        `badge` VARCHAR(100) NULL,
        `cta_text` VARCHAR(100) DEFAULT 'Shop Now',
        `cta_link` VARCHAR(255) DEFAULT 'cbd-products.php',
        `image_url` VARCHAR(255) NULL,
        `sort_order` INT DEFAULT 0,
        `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. Create contact_submissions table
    $db->exec("CREATE TABLE IF NOT EXISTS `contact_submissions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(191) NOT NULL,
        `phone` VARCHAR(25) NULL,
        `subject` VARCHAR(150) NULL,
        `message` TEXT NOT NULL,
        `status` ENUM('New', 'Read', 'Replied') DEFAULT 'New',
        `admin_notes` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`status`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 6. Seed Testimonials if empty
    $checkT = $db->query("SELECT COUNT(*) FROM `testimonials`")->fetchColumn();
    if ($checkT == 0) {
        $testimonials = [
            ['Aarav Sharma', 5, 'The 1500mg Vijaya Extract has significantly improved my sleep quality and lower back stiffness within just 10 days. Exceptional Ayurvedic formulation with genuine COA lab certification.', 'Premium Vijaya Extract 1500mg', 'Mumbai', 1],
            ['Dr. Priya Nair', 5, 'As an Ayurvedic practitioner, I recommend KAMS Hemp extracts to patients seeking holistic, plant-based chronic pain management. Pure, authentic, and compliant with AYUSH guidelines.', 'Full Spectrum Pain Relief Balm', 'Bangalore', 1],
            ['Vikramaditya Roy', 5, 'Delivery was prompt across Delhi NCR. The Deep Sleep Drops helped me taper off synthetic sleeping aids naturally without any morning grogginess. 10/10 recommend!', 'Deep Sleep Restorative Drops', 'New Delhi', 1],
            ['Meera Patel', 5, 'Incredible recovery support after intense marathon training sessions. The Athletic Recovery Tincture is now a staple in my daily post-workout wellness regimen.', 'Athletic Recovery Tincture', 'Ahmedabad', 1]
        ];
        $st = $db->prepare("INSERT INTO `testimonials` (`customer_name`, `rating`, `review`, `product_name`, `location`, `is_verified`, `status`) VALUES (?, ?, ?, ?, ?, ?, 'Published')");
        foreach ($testimonials as $t) {
            $st->execute($t);
        }
        echo "Seeded testimonials.\n";
    }

    // 7. Seed FAQs if empty
    $checkF = $db->query("SELECT COUNT(*) FROM `faqs`")->fetchColumn();
    if ($checkF == 0) {
        $faqs = [
            ['Orders', 'How do I place an order on KAMS Hemp?', 'Select your desired Ayurvedic Vijaya extract or wellness product, add it to cart, and proceed to our secure checkout. You can choose Cash on Delivery (COD) or Instant UPI QR Payment.', 1],
            ['Orders', 'Can I track my order in real-time?', 'Yes! Once dispatched, you will receive a tracking link via SMS/email. You can also track your shipment anytime on our Track Order page using your Order ID or phone number.', 2],
            ['Payment', 'What payment methods are supported?', 'We accept all major UPI apps (Google Pay, PhonePe, Paytm, CRED), Net Banking, Debit/Credit Cards, and Cash on Delivery (COD).', 3],
            ['Payment', 'Is UPI QR code payment safe?', 'Absolutely. Our automated UPI system generates a dynamic merchant QR code secured with bank-grade encryption and instant payment verification.', 4],
            ['Shipping', 'What are your delivery timelines across India?', 'Metro cities typically receive delivery within 24 to 48 hours. Tier-2 and Tier-3 cities receive orders within 3 to 5 business days via express air cargo.', 5],
            ['Shipping', 'Is shipping free?', 'Yes, orders above ₹3,999 qualify for Free Express Shipping across all pin codes in India. A nominal fee of ₹250 applies for orders below that threshold.', 6],
            ['Returns', 'What is your return & refund policy?', 'Due to the medicinal nature of AYUSH cannabis extracts, opened items cannot be returned. If an item arrives damaged or incorrect, contact us within 48 hours for an instant replacement or full refund.', 7],
            ['Products', 'Is Vijaya Extract legal in India?', 'Yes, 100% legal. Vijaya (Cannabis sativa) is an authorized Ayurvedic classical formulation licensed under the Ministry of AYUSH and compliant with the NDPS Act and Drugs & Cosmetics Act.', 8],
            ['Products', 'Do I need a doctor prescription?', 'Certain high-potency Vijaya leaf extracts require a medical consultation. Our panel of certified Ayurvedic Vaidyas provides complimentary doctor consultations directly through our platform.', 9],
            ['Account', 'How does the Referral & Wallet Cashback work?', 'Share your unique referral code with friends. They receive an instant 10% discount on their first order, and you earn 10% recurring cash reward credited directly to your digital wallet!', 10]
        ];
        $sf = $db->prepare("INSERT INTO `faqs` (`category`, `question`, `answer`, `sort_order`, `status`) VALUES (?, ?, ?, ?, 'Published')");
        foreach ($faqs as $f) {
            $sf->execute($f);
        }
        echo "Seeded faqs.\n";
    }

    // 8. Seed Blogs if empty
    $checkB = $db->query("SELECT COUNT(*) FROM `blogs`")->fetchColumn();
    if ($checkB == 0) {
        $blogs = [
            [
                'The Ancient Science of Vijaya: Ayurvedic Wisdom for Modern Stress',
                'ancient-science-of-vijaya-ayurvedic-wisdom',
                'Discover how ancient Vedic texts documented Cannabis sativa (Vijaya) for balancing the Vata and Pitta doshas and restoring natural homeostasis.',
                '<h2>Vedic Origins of Vijaya</h2><p>In ancient Ayurvedic compendiums including the <em>Atharva Veda</em>, Vijaya is celebrated as one of the five sacred sacred plants that release human anxiety. Recognized for its dual properties of <em>Deepana</em> (digestive stimulation) and <em>Nidrajana</em> (restorative sleep induction), Vijaya extracts work holistically with the body’s endogenous cannabinoid receptors.</p><h3>How Endocannabinoids Regulate Modern Stress</h3><p>Modern lifestyle factors like continuous screen exposure and chronic cortisol elevation disrupt sleep-wake cycles. Full-spectrum Vijaya formulations preserve the botanical matrix—cannabinoids, terpenes, and flavonoids—providing an entourage effect that gently supports neuro-calm without sedation.</p>',
                'uploads/banners/hero_banner_main.jpg',
                'Ayurvedic Science',
                'Ayurveda, Stress Relief, Full Spectrum, Wellness',
                'Dr. Ramanand Shastri (BAMS)',
                'The Ancient Science of Vijaya | KAMS HEMP',
                'Explore the Ayurvedic roots of Vijaya extract and how full spectrum plant botanicals help alleviate anxiety and stress.'
            ],
            [
                'Full Spectrum vs CBD Isolate: Understanding the Entourage Effect',
                'full-spectrum-vs-cbd-isolate-entourage-effect',
                'A complete clinical breakdown of why whole-plant Vijaya extract provides superior therapeutic efficacy compared to isolated cannabinoids.',
                '<h2>The Whole-Plant Philosophy</h2><p>Ayurvedic formulations have always emphasized the power of the whole herb rather than extracting a single isolated molecule. Modern phytocannabinoid research confirms this time-tested approach under the name <strong>The Entourage Effect</strong>.</p><h3>Why Full-Spectrum Formulations Stand Out</h3><p>When CBD is accompanied by minor cannabinoids (CBG, CBC, CBN) and therapeutic aromatic terpenes (like Myrcene and Caryophyllene), the clinical synergy enhances bioavailability and receptor affinity, allowing lower required doses with amplified efficacy.</p>',
                'uploads/products/1789149061_pri_Hebe_Drift_Mango__Full_Spectrum_Vijaya_Gummies_50_mg.webp',
                'Clinical Research',
                'CBD Guide, Entourage Effect, Lab Certified, Cannabinoids',
                'Clinical Research Board',
                'Full Spectrum vs CBD Isolate: The Entourage Effect Explained',
                'Understand the difference between full spectrum Vijaya extract and CBD isolate for optimal relief.'
            ],
            [
                'Optimizing Your Sleep Architecture with Plant-Based Botanicals',
                'optimizing-sleep-architecture-plant-botanicals',
                'How restorative drops formulated with Vijaya extract and adaptogenic herbs restore deep REM cycles without next-day lethargy.',
                '<h2>Restoring Circadian Homeostasis</h2><p>Quality sleep is the primary pillar of mental clarity and physical vitality. Unlike pharmaceutical hypnotics that interfere with natural REM cycles, standardized Vijaya drops assist the brain in transitioning naturally through all restorative sleep stages.</p><h3>Dosage and Administration Tips</h3><p>Take 0.5ml sublingually 30 minutes before bedtime. Hold under the tongue for 60 seconds before swallowing to ensure maximum sublingual absorption through the mucosal membrane.</p>',
                'uploads/products/1789195787_pri_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50mg.webp',
                'Sleep & Recovery',
                'Deep Sleep, Insomnia, Natural Remedies, Drops',
                'Wellness Advisory Team',
                'Optimizing Your Sleep Architecture Naturally | KAMS HEMP',
                'Discover how Ayurvedic Vijaya restorative drops help achieve restorative deep sleep naturally.'
            ]
        ];
        $sb = $db->prepare("INSERT INTO `blogs` (`title`, `slug`, `excerpt`, `content`, `featured_image`, `category`, `tags`, `author`, `seo_title`, `seo_description`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Published')");
        foreach ($blogs as $b) {
            $sb->execute($b);
        }
        echo "Seeded blogs.\n";
    }

    echo "[SUCCESS] Schema and initial seed data applied perfectly!\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
