<?php

/*
|--------------------------------------------------------------------------
| SmartCard Studio configuration
|--------------------------------------------------------------------------
| Roles and permissions are defined here and synchronised into the
| database by RolesAndPermissionsSeeder (php artisan db:seed --class=...).
| Super Admin bypasses all checks through a Gate::before hook.
*/

return [

    'permissions' => [
        'dashboard.view' => 'View the administration dashboard',
        'users.manage' => 'Create, edit and deactivate staff and customer accounts',
        'roles.manage' => 'Edit role permissions',
        'activity.view' => 'View the audit log',
        'customers.view-any' => 'View all customers',
        'customers.manage' => 'Create, edit and delete customers',
        'themes.manage' => 'Create and edit card themes',
        'themes.publish' => 'Publish, unpublish and delete themes',
        'designs.view-any' => 'View every card design',
        'designs.manage' => 'Create and edit any card design',
        'designs.assigned' => 'Create and edit designs assigned to you',
        'profiles.manage' => 'Manage any digital profile and profile layouts',
        'nfc.manage' => 'Assign, reassign and replace NFC cards',
        'inventory.manage' => 'Manage NFC inventory and products',
        'orders.manage' => 'Manage card orders',
        'exports.manage' => 'Run exports for any design',
        'imports.manage' => 'Run bulk imports and batch generation',
        'analytics.view-any' => 'View analytics for every customer',
        'revenue.view' => 'View revenue statistics',
    ],

    'roles' => [
        'super-admin' => ['label' => 'Super Admin', 'permissions' => ['*']],
        'admin' => ['label' => 'Admin', 'permissions' => [
            'dashboard.view', 'users.manage', 'activity.view', 'customers.view-any', 'customers.manage',
            'themes.manage', 'themes.publish', 'designs.view-any', 'designs.manage', 'profiles.manage',
            'nfc.manage', 'inventory.manage', 'orders.manage', 'exports.manage', 'imports.manage',
            'analytics.view-any', 'revenue.view',
        ]],
        'designer' => ['label' => 'Designer', 'permissions' => [
            'dashboard.view', 'themes.manage', 'designs.assigned', 'exports.manage',
        ]],
        'sales-manager' => ['label' => 'Sales Manager', 'permissions' => [
            'dashboard.view', 'customers.view-any', 'customers.manage', 'nfc.manage', 'inventory.manage',
            'orders.manage', 'imports.manage', 'analytics.view-any', 'revenue.view',
        ]],
        'customer' => ['label' => 'Customer', 'permissions' => []],
    ],

    // Role given to self-registered accounts.
    'default_role' => 'customer',

    'card' => [
        'width_mm' => 85.6,
        'height_mm' => 54.0,
        'bleed_mm' => 3.0,
        'safe_area_mm' => 3.0,
        'print_dpi' => 300,
    ],

    'profiles' => [
        // Slugs that would clash with app pages or look official.
        'reserved_slugs' => ['admin', 'api', 'app', 'login', 'logout', 'register', 'dashboard', 'settings', 'support', 'help', 'www', 'mail', 'card', 'cards', 'profile', 'profiles', 'new', 'edit', 'create'],
    ],

    'imports' => [
        // Rows per bulk generation file, and the largest upload in KB.
        'max_rows' => (int) env('IMPORT_MAX_ROWS', 1000),
        'max_file_kb' => (int) env('IMPORT_MAX_FILE_KB', 5120),
        // Unfinished imports (and their uploaded files) are deleted after this many days.
        'retention_days' => (int) env('IMPORT_RETENTION_DAYS', 7),
    ],

    'export' => [
        // Headless Chrome/Chromium renders PNG and PDF. Leave empty to auto-detect.
        'chrome_binary' => env('CHROME_PATH'),
        // Needed when the queue worker runs as root (e.g. in containers).
        'chrome_no_sandbox' => (bool) env('CHROME_NO_SANDBOX', true),
        // Ghostscript converts PDFs to CMYK when installed. Leave empty to auto-detect "gs".
        'ghostscript_binary' => env('GHOSTSCRIPT_PATH'),
        'timeout' => (int) env('EXPORT_TIMEOUT', 120),
        // Virtual time Chrome gives the (self-contained) page to lay out fonts and images.
        'font_wait_ms' => 3000,
        'disk' => env('EXPORT_DISK', 'local'),
        // Finished export files are deleted after this many days (exports:prune).
        'retention_days' => (int) env('EXPORT_RETENTION_DAYS', 7),
    ],

    'analytics' => [
        // Request header holding the visitor's country code, set by a trusted proxy/CDN
        // (Cloudflare sends CF-IPCountry). Leave empty to skip country analytics.
        'country_header' => env('ANALYTICS_COUNTRY_HEADER', 'CF-IPCountry'),
    ],

    // Seed demo accounts and sample data even in production (never on a live site).
    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', false),

    // Where landing-page contact enquiries are delivered.
    'contact_email' => env('SMARTCARD_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),

    // Plans shown on the landing page and used for subscriptions.
    'plans' => [
        'starter' => [
            'name' => 'Starter', 'price' => 0, 'interval' => 'month',
            'description' => 'For individuals trying smart cards.',
            'features' => ['1 card design', '1 digital profile', 'QR code (PNG/SVG)', 'Basic analytics'],
        ],
        'professional' => [
            'name' => 'Professional', 'price' => 12, 'interval' => 'month', 'featured' => true,
            'description' => 'For professionals and freelancers.',
            'features' => ['Unlimited designs', '5 digital profiles', 'Print-ready PDF with bleed', 'All premium themes', 'Full analytics'],
        ],
        'business' => [
            'name' => 'Business', 'price' => 49, 'interval' => 'month',
            'description' => 'For teams and print shops.',
            'features' => ['Everything in Professional', 'Brand kits', 'Bulk CSV/Excel generation', 'NFC inventory', 'Team roles'],
        ],
    ],
];
