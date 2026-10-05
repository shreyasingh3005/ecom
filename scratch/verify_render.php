<?php
// scratch/verify_render.php
require_once __DIR__ . '/../includes/db.php';
session_start();
$_SESSION['cart'] = [
    ['id' => 1, 'name' => 'Hemp Hearts Seeds 250g', 'price' => 499, 'qty' => 1]
];

ob_start();
include __DIR__ . '/../checkout.php';
$html = ob_get_clean();

echo "Rendered checkout.php: " . strlen($html) . " bytes\n";
if (strpos($html, 'Cash on Delivery') !== false && strpos($html, 'UPI / Scan & Pay') !== false) {
    echo "✓ Both payment options (COD & UPI) found in checkout HTML\n";
} else {
    echo "❌ Payment options missing\n";
}

if (strpos($html, 'Credit/Debit Card (Razorpay)') === false && strpos($html, 'Credit/Debit Card (Stripe)') === false) {
    echo "✓ Non-specified options (Razorpay/Stripe cards) successfully removed from checkout\n";
} else {
    echo "❌ Unexpected payment options found\n";
}
