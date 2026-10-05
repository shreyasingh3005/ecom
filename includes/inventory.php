<?php
// includes/inventory.php
// Inventory & Product Management Service

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class Inventory {
    public static function getProduct($id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT p.*, c.name as category_title 
                              FROM `products` p 
                              LEFT JOIN `categories` c ON p.category_id = c.id 
                              WHERE p.id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        $product = $stmt->fetch();
        if ($product) {
            $product['images'] = self::getProductImages($product['id']);
            $product['primary_image'] = self::getPrimaryImage($product['images']);
            $product['secondary_image'] = self::getSecondaryImage($product['images']);
        }
        return $product;
    }

    public static function getProductImages($productId) {
        $db = getDB();
        $stmt = $db->prepare("SELECT `image_url`, `is_primary`, `is_secondary` FROM `product_images` WHERE `product_id` = ? ORDER BY `sort_order` ASC, `id` ASC");
        $stmt->execute([(int)$productId]);
        $rows = $stmt->fetchAll();
        $list = [];
        foreach ($rows as $r) {
            $list[] = $r['image_url'];
        }
        return $list;
    }

    public static function getPrimaryImage(array $images) {
        return !empty($images[0]) ? $images[0] : 'uploads/products/default.webp';
    }

    public static function getSecondaryImage(array $images) {
        return !empty($images[1]) ? $images[1] : self::getPrimaryImage($images);
    }

    public static function checkStock($productId, $qty = 1, ?string $fallbackName = null) {
        $db = getDB();
        $productId = (int)$productId;
        if ($productId <= 0 && !empty($fallbackName)) {
            $stmtN = $db->prepare("SELECT id FROM `products` WHERE `name` = ? OR `name` LIKE ? LIMIT 1");
            $stmtN->execute([$fallbackName, '%' . $fallbackName . '%']);
            $fRow = $stmtN->fetch();
            if ($fRow) {
                $productId = (int)$fRow['id'];
            }
        }

        if ($productId <= 0) {
            $displayName = $fallbackName ?: 'Selected product';
            return ['available' => false, 'message' => "Product '{$displayName}' not found."];
        }

        $stmt = $db->prepare("SELECT `id`, `stock`, `status`, `name` FROM `products` WHERE `id` = ?");
        $stmt->execute([$productId]);
        $p = $stmt->fetch();
        if (!$p) {
            $displayName = $fallbackName ?: "ID #{$productId}";
            return ['available' => false, 'message' => "Product '{$displayName}' not found."];
        }
        if ($p['status'] === 'Out of Stock' || (int)$p['stock'] < $qty) {
            return [
                'available' => false,
                'stock' => (int)$p['stock'],
                'message' => "Insufficient stock for {$p['name']}. Available: {$p['stock']}."
            ];
        }
        return ['available' => true, 'stock' => (int)$p['stock'], 'name' => $p['name'], 'id' => (int)$p['id']];
    }

    public static function deductStock($productId, $qty, $refId = null, $type = 'order_placed', $note = 'Order placed', ?string $fallbackName = null) {
        $db = getDB();
        $productId = (int)$productId;
        if ($productId <= 0 && !empty($fallbackName)) {
            $stmtN = $db->prepare("SELECT id FROM `products` WHERE `name` = ? OR `name` LIKE ? LIMIT 1");
            $stmtN->execute([$fallbackName, '%' . $fallbackName . '%']);
            $fRow = $stmtN->fetch();
            if ($fRow) {
                $productId = (int)$fRow['id'];
            }
        }

        if ($productId <= 0) return false;

        $stmt = $db->prepare("SELECT `id`, `stock` FROM `products` WHERE `id` = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $current = $stmt->fetch();
        if (!$current) return false;

        $before = (int)$current['stock'];
        $after = max(0, $before - (int)$qty);
        $newStatus = ($after <= 0) ? 'Out of Stock' : ($after < 20 ? 'Low Stock' : 'Active');

        $update = $db->prepare("UPDATE `products` SET `stock` = ?, `status` = ? WHERE `id` = ?");
        $update->execute([$after, $newStatus, $productId]);

        try {
            $log = $db->prepare("INSERT INTO `inventory_transactions` 
                (`product_id`, `change_qty`, `stock_before`, `stock_after`, `type`, `reference_id`, `note`)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $log->execute([$productId, -((int)$qty), $before, $after, $type, (string)$refId, $note]);
        } catch (\Throwable $e) {
            error_log("Inventory transaction log failed: " . $e->getMessage());
        }

        return true;
    }

    public static function restoreStock($productId, $qty, $refId = null, $type = 'order_cancelled', $note = 'Order cancelled/refunded', ?string $fallbackName = null) {
        $db = getDB();
        $productId = (int)$productId;
        if ($productId <= 0 && !empty($fallbackName)) {
            $stmtN = $db->prepare("SELECT id FROM `products` WHERE `name` = ? OR `name` LIKE ? LIMIT 1");
            $stmtN->execute([$fallbackName, '%' . $fallbackName . '%']);
            $fRow = $stmtN->fetch();
            if ($fRow) {
                $productId = (int)$fRow['id'];
            }
        }

        if ($productId <= 0) return false;

        $stmt = $db->prepare("SELECT `id`, `stock` FROM `products` WHERE `id` = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $current = $stmt->fetch();
        if (!$current) return false;

        $before = (int)$current['stock'];
        $after = $before + (int)$qty;
        $newStatus = ($after <= 0) ? 'Out of Stock' : ($after < 20 ? 'Low Stock' : 'Active');

        $update = $db->prepare("UPDATE `products` SET `stock` = ?, `status` = ? WHERE `id` = ?");
        $update->execute([$after, $newStatus, $productId]);

        try {
            $log = $db->prepare("INSERT INTO `inventory_transactions` 
                (`product_id`, `change_qty`, `stock_before`, `stock_after`, `type`, `reference_id`, `note`)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $log->execute([$productId, (int)$qty, $before, $after, $type, (string)$refId, $note]);
        } catch (\Throwable $e) {
            error_log("Inventory restoration transaction log failed: " . $e->getMessage());
        }

        return true;
    }

    public static function adjustStock($productId, $newStock, $note = 'Manual adjustment', $createdBy = 'Admin') {
        $db = getDB();
        $stmt = $db->prepare("SELECT `stock` FROM `products` WHERE `id` = ? FOR UPDATE");
        $stmt->execute([(int)$productId]);
        $current = $stmt->fetch();
        if (!$current) return false;

        $before = (int)$current['stock'];
        $after = max(0, (int)$newStock);
        $change = $after - $before;
        $newStatus = ($after <= 0) ? 'Out of Stock' : ($after < 20 ? 'Low Stock' : 'Active');

        $update = $db->prepare("UPDATE `products` SET `stock` = ?, `status` = ? WHERE `id` = ?");
        $update->execute([$after, $newStatus, (int)$productId]);

        $log = $db->prepare("INSERT INTO `inventory_transactions` 
            (`product_id`, `change_qty`, `stock_before`, `stock_after`, `type`, `note`, `created_by`)
            VALUES (?, ?, ?, ?, 'manual_adjustment', ?, ?)");
        $log->execute([(int)$productId, $change, $before, $after, $note, $createdBy]);

        return true;
    }

    public static function getMetrics() {
        $db = getDB();
        $totalProducts = (int)$db->query("SELECT COUNT(*) FROM `products` WHERE `status` != 'Inactive'")->fetchColumn();
        $totalUnits = (int)$db->query("SELECT COALESCE(SUM(`stock`), 0) FROM `products` WHERE `status` != 'Inactive'")->fetchColumn();
        $lowStock = (int)$db->query("SELECT COUNT(*) FROM `products` WHERE `status` = 'Low Stock'")->fetchColumn();
        $outOfStock = (int)$db->query("SELECT COUNT(*) FROM `products` WHERE `status` = 'Out of Stock' OR `stock` <= 0")->fetchColumn();

        return [
            'total_products' => $totalProducts,
            'total_units'    => $totalUnits,
            'low_stock'      => $lowStock,
            'out_of_stock'   => $outOfStock
        ];
    }
}
