<?php

/*
|--------------------------------------------------------------------------
| Catalog configuration
|--------------------------------------------------------------------------
|
| Technical settings shared by every installation. Business information
| (name, logo, colours, contact details, currency, WhatsApp) is NOT kept
| here — it lives in the database and is edited from /admin/settings.
|
*/

return [
    'setup_token' => env('INSTALL_SETUP_TOKEN'),

    // Who built and maintains this software: shown in the store footer, the admin,
    // the login and setup pages, and the HTML "author" tag.
    'author' => [
        'name' => 'Bakhtech Solutions',
        'url' => 'https://bakhtech.com.ng',
    ],

    // Products shown per page on the catalog and category pages.
    'per_page' => (int) env('CATALOG_PER_PAGE', 12),

    // Maximum upload size per image, in kilobytes. Large phone photos are
    // fine: they are optimised automatically after upload. (The server's
    // upload_max_filesize / post_max_size must also allow this.)
    'max_upload_kb' => (int) env('CATALOG_MAX_UPLOAD_KB', 10240),

    // Maximum number of images per product (including the main image).
    'max_product_images' => 10,

    // Uploaded images are optimised into these sizes (max width in px; images
    // are never enlarged). "lg" is the full-size file; the others are saved
    // alongside with a suffix ("-md", "-sm") and browsers pick the best one
    // for the screen via srcset. After changing sizes, run:
    //   php artisan catalog:regenerate-images
    'images' => [
        'products' => ['lg' => 1600, 'md' => 1000, 'sm' => 600],
        'categories' => ['lg' => 1200, 'sm' => 600],
        'banners' => ['lg' => 1920, 'md' => 1280, 'sm' => 800],
        'branding' => ['lg' => 800, 'sm' => 256],
        // Bank-transfer receipts (private disk): one size, big enough to read every digit.
        'receipts' => ['lg' => 2000],
    ],

    // WebP quality (60-100) for photos. 90 is visually indistinguishable from
    // the original. Logos and graphics are stored losslessly regardless.
    'image_quality' => (int) env('CATALOG_IMAGE_QUALITY', 90),

];
