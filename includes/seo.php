<?php
// includes/seo.php
// Centralized Technical SEO & Structured Data (JSON-LD) Engine for KAMS HEMP

class SEO {
    public static function renderMeta(array $options = []) {
        $storeName = Settings::get('store_name', 'KAMS HEMP');
        $siteUrl = env('APP_URL', 'http://localhost/ecom');
        
        // Normalize Site URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        $canonicalUrl = $options['canonical'] ?? ($protocol . "://" . $host . $currentUri);
        
        $title = !empty($options['title']) ? $options['title'] . " | " . $storeName : Settings::get('meta_title', $storeName . " | Premium Vedic Cannabis Extracts & Ayurvedic Wellness");
        $description = !empty($options['description']) ? $options['description'] : Settings::get('meta_description', "Explore India's most certified Full-Spectrum Vijaya & CBD extracts. 100% AYUSH licensed, doctor formulated, NABL lab tested.");
        $image = !empty($options['image']) ? $options['image'] : ($protocol . "://" . $host . "/ecom/logo.png");
        $type = $options['type'] ?? 'website';
        $keywords = $options['keywords'] ?? 'Vijaya extract, CBD oil India, Ayurvedic cannabis, full spectrum CBD, AYUSH approved CBD, pain relief balm, sleep drops';

        echo "<!-- Technical SEO & Meta Tags -->\n";
        echo '<title>' . htmlspecialchars($title) . "</title>\n";
        echo '<meta name="description" content="' . htmlspecialchars($description) . "\">\n";
        echo '<meta name="keywords" content="' . htmlspecialchars($keywords) . "\">\n";
        echo '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl) . "\">\n";
        echo '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
        
        // Open Graph
        echo '<meta property="og:locale" content="en_IN">' . "\n";
        echo '<meta property="og:type" content="' . htmlspecialchars($type) . '">' . "\n";
        echo '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
        echo '<meta property="og:url" content="' . htmlspecialchars($canonicalUrl) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . htmlspecialchars($storeName) . '">' . "\n";
        echo '<meta property="og:image" content="' . htmlspecialchars($image) . '">' . "\n";
        echo '<meta property="og:image:alt" content="' . htmlspecialchars($title) . '">' . "\n";

        // Twitter Cards
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . htmlspecialchars($image) . '">' . "\n";

        // Structured Data: Organization & Website with SearchAction
        $orgSchema = [
            "@context" => "https://schema.org",
            "@graph" => [
                [
                    "@type" => "Organization",
                    "@id" => $protocol . "://" . $host . "/#organization",
                    "name" => $storeName,
                    "url" => $protocol . "://" . $host . "/",
                    "logo" => [
                        "@type" => "ImageObject",
                        "url" => $protocol . "://" . $host . "/logo.png"
                    ],
                    "contactPoint" => [
                        "@type" => "ContactPoint",
                        "telephone" => Settings::get('support_phone', '+91 98765 43210'),
                        "contactType" => "customer service",
                        "areaServed" => "IN",
                        "availableLanguage" => ["en", "hi"]
                    ]
                ],
                [
                    "@type" => "WebSite",
                    "@id" => $protocol . "://" . $host . "/#website",
                    "url" => $protocol . "://" . $host . "/",
                    "name" => $storeName,
                    "potentialAction" => [
                        "@type" => "SearchAction",
                        "target" => $protocol . "://" . $host . "/cbd-products.php?search={search_term_string}",
                        "query-input" => "required name=search_term_string"
                    ]
                ]
            ]
        ];

        // Specific Structured Data Schema
        if (!empty($options['schema'])) {
            $orgSchema['@graph'][] = $options['schema'];
        }

        echo '<script type="application/ld+json">' . json_encode($orgSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    public static function breadcrumbsSchema(array $items) {
        $itemList = [];
        $i = 1;
        foreach ($items as $name => $url) {
            $itemList[] = [
                "@type" => "ListItem",
                "position" => $i++,
                "name" => $name,
                "item" => $url
            ];
        }
        return [
            "@type" => "BreadcrumbList",
            "itemListElement" => $itemList
        ];
    }
}
