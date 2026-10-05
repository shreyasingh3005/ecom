<?php
// includes/whatsapp_button.php
// Reusable Floating WhatsApp Desk Widget for KAMS HEMP

$waNumber = Settings::get('whatsapp_number', '+919876543210');
$cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
if (empty($cleanWaNumber)) {
    $cleanWaNumber = '919876543210';
}
$storeTitle = Settings::get('store_name', 'KAMS HEMP');
$waMessage = urlencode("Hello {$storeTitle}, I have an inquiry regarding your Ayurvedic Vijaya formulations and dosage guidance.");
?>
<!-- Floating WhatsApp Support Button -->
<a href="https://wa.me/<?= $cleanWaNumber ?>?text=<?= $waMessage ?>" 
   target="_blank" 
   rel="noopener noreferrer" 
   class="theme-whatsapp-btn" 
   aria-label="Chat with our Ayurvedic Support Desk on WhatsApp">
    <div class="theme-whatsapp-tooltip">Chat with our Vaidya Desk</div>
    <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.288.043.088.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.173.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.419-.101.824zM12 2C6.477 2 2 6.477 2 12c0 1.891.524 3.66 1.436 5.176L2 22l4.981-1.306A9.957 9.957 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"></path>
    </svg>
</a>
