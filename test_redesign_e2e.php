<?php
/**
 * End-to-End Automated Verification Script for Website Redesign
 */

$baseUrl = 'http://localhost/ecom';

$routes = [
    'Home Page' => '/cbd.php',
    'Category Page (Oils & Tinctures)' => '/category.php?cat=oils-tinctures',
    'Category Page (Sleep Restorative)' => '/category.php?cat=sleep-restorative',
    'Shop / Catalog' => '/cbd-products.php',
    'Product Details' => '/product_details.php?id=1',
    'B2B Bulk Order Portal' => '/b2b.php',
    'Blog Index' => '/blog.php',
    'Cart Page' => '/cart.php',
    'Checkout Page' => '/checkout.php',
    'FAQ Page' => '/faq.php',
    'Contact Page' => '/contact.php',
    'About Page' => '/about.php'
];

echo "=== STARTING E2E VERIFICATION ===\n\n";
$allPassed = true;

foreach ($routes as $name => $path) {
    $url = $baseUrl . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200) {
        echo "[FAIL] $name: HTTP $httpCode - Error: $error\n";
        $allPassed = false;
        continue;
    }

    // Check for real PHP fatal errors or warnings in HTML (exclude AYUSH compliance text)
    $hasFatal = (stripos($html, 'Fatal error:') !== false) || (stripos($html, 'Parse error:') !== false);
    
    // Check for PHP Warning or Notice specifically formatted by PHP
    $hasPHPWarning = preg_match('/<b>(Warning|Notice|Deprecated)<\/b>:/i', $html) || preg_match('/PHP (Warning|Notice|Deprecated):/i', $html);

    if ($hasFatal) {
        echo "[FAIL] $name: PHP Fatal/Parse Error detected in response!\n";
        $allPassed = false;
        continue;
    }

    // Check specific elements depending on page
    $checks = [];
    if (in_array($name, ['Home Page', 'Shop / Catalog', 'Category Page (Oils & Tinctures)'])) {
        $hasAddToCart = stripos($html, 'Add to Cart') !== false;
        $hasBuyNow = stripos($html, 'Buy Now') !== false;
        if ($hasAddToCart && $hasBuyNow) {
            $checks[] = "Dual CTAs Present";
        } else {
            $checks[] = "Missing Dual CTAs (Add to Cart: " . ($hasAddToCart ? 'Yes' : 'No') . ", Buy Now: " . ($hasBuyNow ? 'Yes' : 'No') . ")";
            $allPassed = false;
        }
    }

    if ($name === 'Home Page') {
        $hasBenefits = stripos($html, 'Real Benefits You Can Feel') !== false || stripos($html, 'Stress Relief') !== false;
        $hasB2BSection = stripos($html, 'B2B & Bulk Orders') !== false || stripos($html, 'Enquire for Bulk Order') !== false;
        $checks[] = $hasBenefits ? "Benefits Section OK" : "Benefits Section Missing";
        $checks[] = $hasB2BSection ? "B2B Banner OK" : "B2B Banner Missing";
    }

    if ($name === 'Product Details') {
        $hasDualCta = stripos($html, 'Add to Cart') !== false && stripos($html, 'Buy Now') !== false;
        $hasBenefits = stripos($html, 'Target Audience') !== false || stripos($html, 'Stress & Overwhelm') !== false;
        $checks[] = $hasDualCta ? "Product Dual CTAs OK" : "Product Dual CTAs Missing";
        $checks[] = $hasBenefits ? "Product Benefits OK" : "Product Benefits Missing";
    }

    if ($name === 'B2B Bulk Order Portal') {
        $hasForm = stripos($html, 'Submit Wholesale Inquiry') !== false || stripos($html, 'applyForm') !== false;
        $checks[] = $hasForm ? "B2B Form OK" : "B2B Form Missing";
    }

    $statusStr = empty($checks) ? "OK (HTTP 200)" : "OK - " . implode(", ", $checks);
    if ($hasPHPWarning) {
        $statusStr .= " (PHP Notice/Warning in output)";
        $allPassed = false;
    }
    echo "[PASS] $name -> $statusStr\n";
}

echo "\n==================================\n";
if ($allPassed) {
    echo "RESULT: ALL TESTS PASSED SUCCESSFULLY!\n";
} else {
    echo "RESULT: SOME TESTS FAILED. CHECK LOGS ABOVE.\n";
}
