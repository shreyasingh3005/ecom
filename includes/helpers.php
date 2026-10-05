<?php
// includes/helpers.php
// Common Utility Functions

if (!function_exists('format_price')) {
    function format_price($amount, $includeSymbol = true, $decimals = 2) {
        $val = (float)$amount;
        $formatted = number_format($val, $decimals);
        return $includeSymbol ? ('₹' . $formatted) : $formatted;
    }
}

if (!function_exists('clean_price')) {
    function clean_price($price) {
        if (is_numeric($price)) return (float)$price;
        return (float) preg_replace('/[^0-9.]/', '', (string)$price);
    }
}

if (!function_exists('sanitize')) {
    function sanitize($data) {
        if (is_array($data)) {
            return array_map('sanitize', $data);
        }
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('json_response')) {
    function json_response($success, $message = '', $data = [], $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => (bool)$success,
            'message' => $message,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: {$url}");
        exit;
    }
}

if (!function_exists('slugify')) {
    function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'item-' . time() : $text;
    }
}

if (!function_exists('format_ist_date')) {
    function format_ist_date($dateStr, $format = 'M d, Y') {
        if (empty($dateStr)) return 'N/A';
        try {
            $dt = new DateTime($dateStr);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format($format);
        } catch (Exception $e) {
            return date($format, strtotime($dateStr));
        }
    }
}

if (!function_exists('format_ist_time')) {
    function format_ist_time($dateStr, $format = 'h:i A \I\S\T') {
        if (empty($dateStr)) return '';
        try {
            $dt = new DateTime($dateStr);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format($format);
        } catch (Exception $e) {
            return date('h:i A', strtotime($dateStr)) . ' IST';
        }
    }
}

if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

if (!function_exists('get_product_card_data')) {
    function get_product_card_data($product) {
        $id = (int)($product['id'] ?? 0);
        $name = $product['name'] ?? 'Ayurvedic Formulation';
        $price = (float)($product['price'] ?? 0);
        $mrp = !empty($product['mrp']) ? (float)$product['mrp'] : round($price * 1.25);
        $discount = ($mrp > $price) ? round((($mrp - $price) / $mrp) * 100) : 0;
        $badge = !empty($product['sale_badge']) ? $product['sale_badge'] : ($discount > 0 ? "{$discount}% OFF" : '');
        $cat = $product['category_name'] ?? ($product['cat_name'] ?? ($product['category_title'] ?? 'Ayurvedic Extracts'));

        // Rich profiles mapped by ID or keywords
        $profiles = [
            1 => [
                'short_benefit' => 'Full-spectrum botanical relaxation to ease daily stress, calm restless thoughts, and restore natural sleep.',
                'rating' => 4.9,
                'reviews_count' => 158,
                'target_user' => 'Adults managing chronic work stress, nervous tension, and irregular rest patterns.',
                'box' => ['1× 30ml UV Amber Glass Bottle (1500mg)', '1× Calibrated 1ml Glass Pipette', '1× Doctor Dosage Guide & Batch COA'],
                'specs' => [
                    'Volume' => '30 ml (Approx. 600 drops)',
                    'Potency' => '1500 mg Full Spectrum Vijaya',
                    'Carrier Oil' => 'Cold-Pressed Organic Hemp Seed Oil',
                    'Extraction' => 'Supercritical Sub-Zero CO₂',
                    'Dosage' => '3–5 drops sublingually, twice daily',
                    'Certification' => 'Ministry of AYUSH & NABL Lab Certified'
                ]
            ],
            2 => [
                'short_benefit' => 'Herbal sleep restorative formula to help you drift off naturally and wake up refreshed without morning grogginess.',
                'rating' => 4.8,
                'reviews_count' => 134,
                'target_user' => 'Working professionals, light sleepers, and individuals struggling with fragmented nighttime sleep.',
                'box' => ['1× 30ml Restorative Sleep Drops Bottle', '1× Measured Glass Pipette', '1× Bedtime Routine Guide'],
                'specs' => [
                    'Volume' => '30 ml',
                    'Potency' => '1000 mg Botanical Sleep Complex',
                    'Active Botanicals' => 'Vijaya Leaf, Myrcene & Chamomile Terpenes',
                    'Flavor' => 'Mild Herbal & Lavender Notes',
                    'Dosage' => '4–6 drops under tongue 30 mins before sleep',
                    'Habit Forming' => '100% Non-Habit Forming (Melatonin-Free)'
                ]
            ],
            3 => [
                'short_benefit' => 'Fast-acting warming botanical balm that penetrates deep to soothe stiff joints, sore muscles, and backaches in minutes.',
                'rating' => 4.9,
                'reviews_count' => 212,
                'target_user' => 'Individuals with neck/back stiffness, knee pain, posture aches, and post-exercise muscle tightness.',
                'box' => ['1× 50g Amber Glass Ointment Jar', '1× Application Wooden Spatula', '1× Joint Pressure Points Leaflet'],
                'specs' => [
                    'Net Weight' => '50 grams',
                    'Active Vijaya' => '500 mg Full-Spectrum Extract',
                    'Botanical Base' => 'Organic Beeswax, Shea Butter, Eucalyptus, Wintergreen',
                    'Application' => 'Topical massage over affected area 2–3 times daily',
                    'Absorption' => 'Fast-absorbing, non-staining, non-greasy'
                ]
            ],
            4 => [
                'short_benefit' => 'High-potency adaptogen drops designed to speed up muscle recovery, reduce soreness, and fight post-workout fatigue.',
                'rating' => 4.9,
                'reviews_count' => 98,
                'target_user' => 'Athletes, gym runners, cyclists, and fitness enthusiasts wanting faster physical recovery.',
                'box' => ['1× 30ml Athletic Recovery Dropper Bottle', '1× High-Precision Pipette', '1× Post-Workout Dosing Chart'],
                'specs' => [
                    'Volume' => '30 ml',
                    'Potency' => '2000 mg Active Phytocannabinoids',
                    'Carrier Oil' => 'Fractionated Coconut MCT Oil',
                    'Benefits' => 'Eases DOMS, speeds muscular repair',
                    'Certification' => 'WADA Non-Prohibited Herbal Ingredients'
                ]
            ],
            5 => [
                'short_benefit' => 'Gentle herbal drops to help calm nervous pets, ease thunder/firecracker anxiety, and support aging joint mobility.',
                'rating' => 4.8,
                'reviews_count' => 165,
                'target_user' => 'Pet parents caring for dogs and cats with separation anxiety, loud noise phobia, or hip stiffness.',
                'box' => ['1× 30ml Pet-Safe Glass Bottle', '1× Soft Silicone-Tipped Pipette (Tooth-Safe)', '1× Pet Weight Dosing Chart'],
                'specs' => [
                    'Volume' => '30 ml',
                    'Pet Potency' => '500 mg Broad-Spectrum (0.0% THC)',
                    'Flavoring' => 'Wild Alaskan Salmon Oil Infusion',
                    'Suitable For' => 'Dogs & Cats of all breeds',
                    'Administration' => 'Mix directly into daily food or drop in mouth'
                ]
            ],
            6 => [
                'short_benefit' => 'Nutritious raw hulled hemp seeds packed with complete plant protein, essential Omega 3 & 6, and vital minerals for daily energy.',
                'rating' => 4.9,
                'reviews_count' => 240,
                'target_user' => 'Health-conscious families, vegans, athletes, and anyone seeking pure plant superfood nutrition.',
                'box' => ['1× 250g Multi-Layer Resealable Freshness Pouch with Oxygen Barrier'],
                'specs' => [
                    'Net Weight' => '250 grams',
                    'Protein' => '10g Complete Plant Protein per 30g serving',
                    'Omegas' => 'Optimal 3:1 Balance of Omega-6 to Omega-3',
                    'Ingredients' => '100% Raw Shelled Hemp Seeds (Non-GMO)',
                    'Usage' => 'Sprinkle over salads, oats, yogurt, or blend in smoothies'
                ]
            ]
        ];

        $prof = $profiles[$id] ?? [
            'short_benefit' => 'Pure certified Ayurvedic formulation designed to support everyday well-being and natural vitality.',
            'rating' => 4.8,
            'reviews_count' => 110,
            'target_user' => 'Adults looking for genuine Ayurvedic plant-based wellness.',
            'box' => ['1× Standard Amber Bottle / Jar', '1× Certified Usage Leaflet'],
            'specs' => [
                'Standard' => 'Ministry of AYUSH Licensed',
                'Quality' => '100% Natural Himalayan Sourced'
            ]
        ];

        return [
            'id' => $id,
            'name' => $name,
            'slug' => $product['slug'] ?? 'product-' . $id,
            'category' => $cat,
            'price_raw' => $price,
            'price_formatted' => '₹' . number_format($price),
            'mrp_raw' => $mrp,
            'mrp_formatted' => '₹' . number_format($mrp),
            'discount_percent' => $discount,
            'badge' => $badge,
            'stock' => (int)($product['stock'] ?? 0),
            'status' => $product['status'] ?? 'Active',
            'short_benefit' => $prof['short_benefit'],
            'rating' => $prof['rating'],
            'reviews_count' => $prof['reviews_count'],
            'target_user' => $prof['target_user'],
            'whats_in_box' => $prof['box'],
            'specs' => $prof['specs'],
            'add_to_cart_url' => "cart.php?action=add&id={$id}",
            'buy_now_url' => "cart.php?action=add&id={$id}&redirect=checkout",
            'details_url' => "product_details.php?id={$id}"
        ];
    }
}
