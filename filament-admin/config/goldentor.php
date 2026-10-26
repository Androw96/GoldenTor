<?php

return [
    // The same admin key as unas/config.php; only used to create the very first Filament admin.
    'admin_key' => env('GOLDENTOR_ADMIN_KEY'),
    'admin_setup_key' => env('GOLDENTOR_ADMIN_SETUP_KEY', env('GOLDENTOR_ADMIN_KEY')),
    'public_url' => env('GOLDENTOR_PUBLIC_URL', 'https://goldentor.hu'),
    'mail_from' => env('GOLDENTOR_MAIL_FROM', 'info@goldentor.hu'),
    'contact_recipient' => env('GOLDENTOR_CONTACT_RECIPIENT', 'info@goldentor.hu'),
    'mail_enabled' => (bool) env('GOLDENTOR_MAIL_ENABLED', true),
    // Fallback only: the bookable slots are set in the admin (Naptár → Foglalási szabályok).
    'slots' => ['09:00', '11:00', '14:00', '16:00'],
    // The website root that holds unas/calendar.php (the admin lives in <root>/admin-panel).
    'site_root' => env('GOLDENTOR_SITE_ROOT', dirname(__DIR__, 2)),
    'topics' => ['Befektetés', 'Ingatlan', 'Finanszírozás', 'Biztosítás', 'Komplex pénzügyi átvilágítás'],
    'meetings' => ['Online konzultáció', 'Személyes találkozó Győrben', 'Telefonos egyeztetés'],
];
