<?php

$shortName = env('BRAND_SHORT_NAME', 'Sadbhavna');
$website = env('BRAND_WEBSITE_URL', 'https://sadbhavnadham.org');

return [
    'name' => env('BRAND_NAME', env('APP_NAME', 'Sadbhavna Vrudhashram')),
    'short_name' => $shortName,
    'legal_name' => env('BRAND_LEGAL_NAME', 'Manav Seva Cheritable Trust'),
    'tagline' => env('BRAND_TAGLINE', 'Support our causes and make a difference'),
    'admin_label' => env('BRAND_ADMIN_LABEL', 'Donation Admin'),
    'razorpay_name' => env('BRAND_RAZORPAY_NAME', $shortName),
    'recurring_mandate' => env(
        'BRAND_RECURRING_MANDATE',
        'I authorize {brand} to debit the selected amount every month via UPI/card mandate until I cancel.'
    ),
    'receipt_thank_you' => env('BRAND_RECEIPT_THANK_YOU', 'Thank you for supporting {brand}.'),
    'payment_link_description' => env('BRAND_PAYMENT_LINK_DESCRIPTION', 'Donation to {brand}'),

    'assets' => [
        'logo' => env('BRAND_LOGO', '/assets/img/logo/logo-black.png'),
        'logo_public' => env('BRAND_LOGO_PUBLIC', '/images/logo_main.png'),
        'favicon' => env('BRAND_FAVICON', '/assets/img/logo/favicon.png'),
        'og_image' => env('BRAND_OG_IMAGE', '/images/logo_main.png'),
        'upi_qr' => env('BRAND_UPI_QR', '/images/footer/upi-qr.png'),
        'upi_apps' => env('BRAND_UPI_APPS', '/images/footer/upi-apps.png'),
        'payment_gateways' => env('BRAND_PAYMENT_GATEWAYS', '/images/payments/payment-logos.png'),
    ],

    'seo' => [
        'canonical_url' => env('BRAND_CANONICAL_URL', env('APP_URL')),
        'home_description' => env('BRAND_SEO_HOME_DESCRIPTION', env('BRAND_TAGLINE', 'Support our causes and make a difference')),
    ],

    'urls' => [
        'website' => $website,
        'privacy' => env('BRAND_PRIVACY_URL', 'https://sadbhavnadham.org/privacy-policy/'),
        'refund' => env('BRAND_REFUND_URL', 'https://sadbhavnadham.org/refund-and-cancellation/'),
        'terms' => env('BRAND_TERMS_URL', 'https://sadbhavnadham.org/terms-and-conditions/'),
    ],

    'footer' => [
        'about' => env(
            'BRAND_FOOTER_ABOUT',
            'Founded on August 15, 2015, our NGO runs Vrudhashram, animal welfare programs, and tree plantation for environmental conservation. Creating compassionate impact across communities. Join us in serving humanity, animals, and nature.'
        ),
        'useful_links' => [
            ['label' => 'Home', 'url' => rtrim((string) $website, '/').'/'],
            ['label' => 'Know Us', 'url' => rtrim((string) $website, '/').'/know-us/'],
            ['label' => 'Infrastructure', 'url' => rtrim((string) $website, '/').'/infrastructure/'],
            ['label' => 'Achievements', 'url' => rtrim((string) $website, '/').'/certificates/'],
            ['label' => 'Contact Us', 'url' => rtrim((string) $website, '/').'/contact-us/'],
            ['label' => 'Bank Details', 'url' => '/bank-details'],
        ],
        'initiatives' => [
            ['label' => 'Tree Plantation', 'url' => rtrim((string) $website, '/').'/tree-plantation/'],
            ['label' => 'Old Age Home', 'url' => rtrim((string) $website, '/').'/old-age-home/'],
            ['label' => 'Animal Hospital', 'url' => rtrim((string) $website, '/').'/animal-hospital/'],
            ['label' => 'Dog Shelter', 'url' => rtrim((string) $website, '/').'/dog-shelter/'],
            ['label' => 'Bull Shelter', 'url' => rtrim((string) $website, '/').'/bull-shelter/'],
            ['label' => 'Medical', 'url' => rtrim((string) $website, '/').'/medical/'],
        ],
    ],

    'header_nav' => [
        ['label' => 'Home', 'url' => rtrim((string) $website, '/').'/'],
        [
            'label' => 'The Journey',
            'url' => rtrim((string) $website, '/').'/know-us/',
            'children' => [
                ['label' => 'Know Us', 'url' => rtrim((string) $website, '/').'/know-us/'],
                ['label' => 'Infrastructure', 'url' => rtrim((string) $website, '/').'/infrastructure/'],
                ['label' => 'Certificates', 'url' => rtrim((string) $website, '/').'/certificates/'],
            ],
        ],
        [
            'label' => 'Our Initiative',
            'url' => rtrim((string) $website, '/').'/tree-plantation/',
            'children' => [
                ['label' => 'Tree Plantation', 'url' => rtrim((string) $website, '/').'/tree-plantation/'],
                ['label' => 'Old Age Home', 'url' => rtrim((string) $website, '/').'/old-age-home/'],
                ['label' => 'Animal Hospital', 'url' => rtrim((string) $website, '/').'/animal-hospital/'],
                ['label' => 'Dog Shelter', 'url' => rtrim((string) $website, '/').'/dog-shelter/'],
                ['label' => 'Bull Shelter', 'url' => rtrim((string) $website, '/').'/bull-shelter/'],
                ['label' => 'Medical', 'url' => rtrim((string) $website, '/').'/medical/'],
            ],
        ],
        ['label' => 'CSR', 'url' => rtrim((string) $website, '/').'/csr/'],
        ['label' => 'Blog', 'url' => rtrim((string) $website, '/').'/blog/'],
        ['label' => 'Contact Us', 'url' => rtrim((string) $website, '/').'/contact-us/'],
    ],

    'contact' => [
        'address' => env(
            'BRAND_CONTACT_ADDRESS',
            'Vinubhai Bachubhai Nagrecha Parisar- Sadbhavna Vrudhashram, Jamnagar - Rajkot Highway, Mota Rampar, Gujarat 360110'
        ),
        'phone_primary' => env('BRAND_CONTACT_PHONE_PRIMARY', '+91 85301 38001'),
        'phone_secondary' => env('BRAND_CONTACT_PHONE_SECONDARY', '+91 80002 88888'),
        'email' => env('BRAND_CONTACT_EMAIL', 'info@sadbhavnadham.org'),
    ],

    'social' => [
        'facebook' => env('BRAND_SOCIAL_FACEBOOK', 'https://www.facebook.com/SadbhavnaVrudhashram'),
        'instagram' => env('BRAND_SOCIAL_INSTAGRAM', 'https://www.instagram.com/sadbhavnavrudhashram'),
        'youtube' => env('BRAND_SOCIAL_YOUTUBE', 'https://www.youtube.com/@sadbhavnavrudhashram'),
        'whatsapp' => env('BRAND_SOCIAL_WHATSAPP', 'https://wa.aisensy.com/aab10a'),
    ],

    'bank' => [
        'account_name' => env('BRAND_BANK_ACCOUNT_NAME', 'Manav Seva Cheritable Trust'),
        'account_number' => env('BRAND_BANK_ACCOUNT_NUMBER', '065821010000069'),
        'ifsc' => env('BRAND_BANK_IFSC', 'UBIN0906581'),
        'bank_name' => env('BRAND_BANK_NAME', 'Union Bank of India'),
        'branch' => env('BRAND_BANK_BRANCH', ''),
        'account_type' => env('BRAND_BANK_ACCOUNT_TYPE', 'Current'),
        'upi_id' => env('BRAND_BANK_UPI_ID', ''),
        'note' => env(
            'BRAND_BANK_NOTE',
            'After transferring, please share the payment screenshot on WhatsApp or email so we can issue your receipt.'
        ),
    ],
];
