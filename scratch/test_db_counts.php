<?php
require_once __DIR__ . '/../includes/db.php';
$db = Database::getInstance();
echo "Orders: " . $db->query("SELECT COUNT(*) FROM orders")->fetchColumn() . PHP_EOL;
echo "Blogs: " . $db->query("SELECT COUNT(*) FROM blogs")->fetchColumn() . PHP_EOL;
echo "FAQs: " . $db->query("SELECT COUNT(*) FROM faqs")->fetchColumn() . PHP_EOL;
echo "Testimonials: " . $db->query("SELECT COUNT(*) FROM testimonials")->fetchColumn() . PHP_EOL;
echo "Banners: " . $db->query("SELECT COUNT(*) FROM banners")->fetchColumn() . PHP_EOL;
echo "Contact: " . $db->query("SELECT COUNT(*) FROM contact_submissions")->fetchColumn() . PHP_EOL;
