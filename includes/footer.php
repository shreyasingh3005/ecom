<?php
// includes/footer.php
// Single Reusable Global Footer Component for KAMS HEMP

if (!isset($storeName)) {
    $storeName = Settings::get('store_name', 'KAMS HEMP');
}
$supportEmail = Settings::get('support_email', 'support@kamshemp.com');
$supportPhone = Settings::get('support_phone', '+91 98765 43210');
?>
<!-- Global Main Footer -->
<footer class="theme-footer" role="contentinfo">
    <div class="theme-container">
        <!-- Trust Badges Strip -->
        <div class="theme-footer-badges">
            <div class="theme-badge-card">
                <div class="theme-badge-icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <div class="theme-badge-text">
                    <h5>AYUSH Licensed</h5>
                    <p>Government certified formulas</p>
                </div>
            </div>
            <div class="theme-badge-card">
                <div class="theme-badge-icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </div>
                <div class="theme-badge-text">
                    <h5>NABL Lab Tested</h5>
                    <p>100% verified purity COA</p>
                </div>
            </div>
            <div class="theme-badge-card">
                <div class="theme-badge-icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <div class="theme-badge-text">
                    <h5>Pan-India Express</h5>
                    <p>Discreet door delivery</p>
                </div>
            </div>
            <div class="theme-badge-card">
                <div class="theme-badge-icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                </div>
                <div class="theme-badge-text">
                    <h5>Encrypted Checkout</h5>
                    <p>Instant UPI, Cards & COD</p>
                </div>
            </div>
        </div>

        <!-- 5-Column Navigation Grid -->
        <div class="theme-footer-grid">
            <!-- Col 1: Brand Info -->
            <div class="theme-footer-col">
                <div class="theme-logo-text" style="margin-bottom:14px;">
                    <?= htmlspecialchars($storeName) ?>
                    <span>Ayurvedic Wellness</span>
                </div>
                <p>Pioneering authentic Vedic plant pharmacology. We formulate standardized full-spectrum Vijaya leaf extracts, medical tinctures, and therapeutic topicals compliant with Indian law.</p>
                <div style="font-size:13px; color:#e2e8f0; display:flex; flex-direction:column; gap:6px;">
                    <div>📧 <a href="mailto:<?= htmlspecialchars($supportEmail) ?>" style="color:var(--theme-gold);"><?= htmlspecialchars($supportEmail) ?></a></div>
                    <div>📞 <a href="tel:<?= htmlspecialchars($supportPhone) ?>" style="color:var(--theme-gold);"><?= htmlspecialchars($supportPhone) ?></a></div>
                </div>
            </div>

            <!-- Col 2: Shop Formulations -->
            <div class="theme-footer-col">
                <h4>Formulations</h4>
                <ul class="theme-footer-links">
                    <li><a href="cbd-products.php" class="theme-footer-link">All Products</a></li>
                    <li><a href="cbd-products.php?category=Oils+%26+Tinctures" class="theme-footer-link">Vijaya Tinctures</a></li>
                    <li><a href="cbd-products.php?category=Edibles+%26+Wellness" class="theme-footer-link">Sleep Restorative Drops</a></li>
                    <li><a href="cbd-products.php?category=Topicals+%26+Care" class="theme-footer-link">Pain Relief Balms</a></li>
                    <li><a href="cbd-products.php?category=Pet+CBD" class="theme-footer-link">Veterinary Pet Care</a></li>
                </ul>
            </div>

            <!-- Col 3: Quick Links -->
            <div class="theme-footer-col">
                <h4>Quick Links</h4>
                <ul class="theme-footer-links">
                    <li><a href="b2b.php" class="theme-footer-link highlight">B2B Wholesale / Bulk</a></li>
                    <li><a href="order-tracking.php" class="theme-footer-link">Track Your Order</a></li>
                    <li><a href="blog.php" class="theme-footer-link">Research & Science</a></li>
                    <li><a href="testimonials.php" class="theme-footer-link">Verified Reviews</a></li>
                    <li><a href="cbd-about.php" class="theme-footer-link">Our Heritage Story</a></li>
                    <li><a href="faq.php" class="theme-footer-link">Help & FAQs</a></li>
                </ul>
            </div>

            <!-- Col 4: Customer Care & Policies -->
            <div class="theme-footer-col">
                <h4>Customer Care</h4>
                <ul class="theme-footer-links">
                    <li><a href="cbd-contact.php" class="theme-footer-link">Contact Support</a></li>
                    <li><a href="shipping-policy.php" class="theme-footer-link">Shipping Policy</a></li>
                    <li><a href="refund-policy.php" class="theme-footer-link">Returns & Refunds</a></li>
                    <li><a href="privacy-policy.php" class="theme-footer-link">Privacy Policy</a></li>
                    <li><a href="terms.php" class="theme-footer-link">Terms & Conditions</a></li>
                </ul>
            </div>

            <!-- Col 5: Newsletter -->
            <div class="theme-footer-col">
                <h4>Stay Informed</h4>
                <p>Subscribe for exclusive Ayurvedic wellness updates, batch COA reports, and private member offers.</p>
                <form class="theme-newsletter-form" onsubmit="event.preventDefault(); showToast('Thank you for subscribing to KAMS Wellness Insights!', 'success'); this.reset();">
                    <input type="email" placeholder="Enter your email" class="theme-newsletter-input" required>
                    <button type="submit" class="theme-newsletter-btn" aria-label="Subscribe">Join</button>
                </form>
                <div style="font-size:11.5px; color:var(--theme-text-muted);">
                    No spam ever. Unsubscribe at any time.
                </div>
            </div>
        </div>

        <!-- AYUSH Medical Disclaimer -->
        <div class="theme-footer-disclaimer">
            <strong>Statutory Medical Notice:</strong> Formulations containing Vijaya (Cannabis sativa) leaf extracts are Ayurvedic Proprietary Medicines licensed under the Ministry of AYUSH, Government of India. These products are intended for therapeutic and wellness use in compliance with the Drugs and Cosmetics Act. Strictly prohibited for minors below 18 years of age.
        </div>

        <!-- Footer Bottom Row -->
        <div class="theme-footer-bottom">
            <div>
                &copy; <?= date('Y') ?> <?= htmlspecialchars($storeName) ?>. All rights reserved. Handcrafted with Vedic botanical care.
            </div>
            <div class="theme-payment-icons">
                <span class="theme-payment-pill">BHIM UPI</span>
                <span class="theme-payment-pill">GPay</span>
                <span class="theme-payment-pill">PhonePe</span>
                <span class="theme-payment-pill">Paytm</span>
                <span class="theme-payment-pill">Credit/Debit</span>
                <span class="theme-payment-pill">COD</span>
            </div>
        </div>
    </div>
</footer>

<!-- Reusable WhatsApp Floating Widget -->
<?php require_once __DIR__ . '/whatsapp_button.php'; ?>

<!-- Universal Interactive Engine JS -->
<script src="assets/js/theme.js"></script>
