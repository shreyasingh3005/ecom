<?php
// includes/settings.php
// Dynamic Database-Driven Settings Service

require_once __DIR__ . '/db.php';

class Settings {
    private static $cache = null;

    public static function load() {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                $db = getDB();
                $stmt = $db->query("SELECT `setting_key`, `setting_value` FROM `settings`");
                while ($row = $stmt->fetch()) {
                    self::$cache[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Exception $e) {
                error_log("Failed to load settings: " . $e->getMessage());
            }
        }
        return self::$cache;
    }

    public static function get($key, $default = null) {
        if (self::$cache === null) {
            self::load();
        }
        return self::$cache[$key] ?? $default;
    }

    public static function set($key, $value) {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)");
            $stmt->execute([$key, (string)$value]);
            if (self::$cache !== null) {
                self::$cache[$key] = (string)$value;
            }
            return true;
        } catch (Exception $e) {
            error_log("Failed to save setting [{$key}]: " . $e->getMessage());
            return false;
        }
    }

    public static function setBatch(array $items) {
        $db = getDB();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)");
            foreach ($items as $k => $v) {
                $stmt->execute([$k, (string)$v]);
                if (self::$cache !== null) {
                    self::$cache[$k] = (string)$v;
                }
            }
            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Failed to batch save settings: " . $e->getMessage());
            return false;
        }
    }

    public static function all() {
        if (self::$cache === null) {
            self::load();
        }
        return self::$cache;
    }

    public static function getAll() {
        return self::all();
    }

    public static function getCurrencySymbol() {
        $curr = self::get('currency', 'INR');
        switch (strtoupper($curr)) {
            case 'USD': return '$';
            case 'EUR': return '€';
            case 'GBP': return '£';
            case 'JPY': return '¥';
            case 'CAD': return 'CA$';
            case 'AUD': return 'AU$';
            default: return '₹';
        }
    }
}

// Convenience global helper
if (!function_exists('get_setting')) {
    function get_setting($key, $default = null) {
        return Settings::get($key, $default);
    }
}
if (!function_exists('update_setting')) {
    function update_setting($key, $value) {
        return Settings::set($key, $value);
    }
}
