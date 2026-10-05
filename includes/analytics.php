<?php
// includes/analytics.php
// Traffic & Product Performance Analytics Service

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class Analytics {
    public static function logVisit($url = null, $title = null) {
        self::trackPage($title, $url);
    }

    public static function logProductView($productId) {
        self::trackProduct($productId, 'view');
    }

    public static function getTrafficMetrics() {
        try {
            $db = getDB();
            $todayViews = (int)$db->query("SELECT COUNT(*) FROM `traffic` WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            $activeNow = (int)$db->query("SELECT COUNT(DISTINCT `ip_address`) FROM `traffic` WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->fetchColumn();
            return [
                'today_page_views' => $todayViews,
                'active_now' => max(1, $activeNow)
            ];
        } catch (Exception $e) {
            return ['today_page_views' => 0, 'active_now' => 1];
        }
    }

    /**
     * Record a page visit in the traffic table
     */
    public static function trackPage($pageTitle = null, $customUrl = null) {
        try {
            $db = getDB();
            $pageUrl = $customUrl ?: ($_SERVER['REQUEST_URI'] ?? '/');
            $ip = get_client_ip();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

            // Detect Device Category
            $device = 'desktop';
            if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', $userAgent)) {
                $device = 'tablet';
            } elseif (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $userAgent)) {
                $device = 'mobile';
            }

            // Detect Traffic Source
            $source = 'Direct';
            if ($referrer) {
                $refHost = parse_url($referrer, PHP_URL_HOST);
                $currHost = $_SERVER['HTTP_HOST'] ?? '';
                if ($refHost && stripos($refHost, $currHost) === false) {
                    if (preg_match('/(google|bing|yahoo|duckduckgo)/i', $refHost)) {
                        $source = 'Organic Search';
                    } elseif (preg_match('/(facebook|instagram|twitter|t\.co|linkedin|pinterest|whatsapp)/i', $refHost)) {
                        $source = 'Social Media';
                    } else {
                        $source = 'Referral';
                    }
                }
            }

            $stmt = $db->prepare("INSERT INTO `traffic` (`page_url`, `page_title`, `ip_address`, `user_agent`, `device_category`, `source`, `referrer_url`, `user_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$pageUrl, $pageTitle, $ip, substr($userAgent, 0, 255), $device, $source, $referrer ? substr($referrer, 0, 255) : null, $userId]);
        } catch (Exception $e) {
            // Silently ignore analytics error to never break user experience
        }
    }

    /**
     * Track product action (view, click, add_to_cart, order)
     */
    public static function trackProduct($productId, $actionType) {
        try {
            $db = getDB();
            $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $ip = get_client_ip();

            $stmt = $db->prepare("INSERT INTO `product_analytics` (`product_id`, `action_type`, `user_id`, `ip_address`) VALUES (?, ?, ?, ?)");
            $stmt->execute([(int)$productId, $actionType, $userId, $ip]);
        } catch (Exception $e) {
            // Silently ignore
        }
    }

    /**
     * Get aggregate traffic statistics
     */
    public static function getTrafficSummary($days = 7) {
        $db = getDB();
        $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)";

        $totalViews = (int)$db->query("SELECT COUNT(*) FROM `traffic` WHERE {$dateCondition}")->fetchColumn();
        $uniqueVisitors = (int)$db->query("SELECT COUNT(DISTINCT `ip_address`) FROM `traffic` WHERE {$dateCondition}")->fetchColumn();

        // Sources breakdown
        $srcStmt = $db->query("SELECT `source`, COUNT(*) as count FROM `traffic` WHERE {$dateCondition} GROUP BY `source` ORDER BY count DESC");
        $sources = $srcStmt->fetchAll();

        // Device breakdown
        $devStmt = $db->query("SELECT `device_category`, COUNT(*) as count FROM `traffic` WHERE {$dateCondition} GROUP BY `device_category`");
        $devices = $devStmt->fetchAll();

        // Daily trend
        $trendStmt = $db->query("SELECT DATE(created_at) as visit_date, COUNT(*) as views FROM `traffic` WHERE {$dateCondition} GROUP BY DATE(created_at) ORDER BY visit_date ASC");
        $dailyTrend = $trendStmt->fetchAll();

        return [
            'total_views' => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'sources' => $sources,
            'devices' => $devices,
            'daily_trend' => $dailyTrend
        ];
    }

    /**
     * Get product performance summary
     */
    public static function getProductPerformance($limit = 10) {
        $db = getDB();
        $sql = "SELECT p.id, p.name, p.price, p.stock,
                       COUNT(CASE WHEN pa.action_type = 'view' THEN 1 END) as views_count,
                       COUNT(CASE WHEN pa.action_type = 'add_to_cart' THEN 1 END) as cart_count,
                       COUNT(CASE WHEN pa.action_type = 'order' THEN 1 END) as orders_count,
                       COALESCE(SUM(CASE WHEN pa.action_type = 'order' THEN p.price END), 0) as revenue
                FROM `products` p
                LEFT JOIN `product_analytics` pa ON p.id = pa.product_id
                WHERE p.status != 'Inactive'
                GROUP BY p.id
                ORDER BY views_count DESC, orders_count DESC
                LIMIT " . (int)$limit;
        return $db->query($sql)->fetchAll();
    }
}
