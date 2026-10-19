<?php

return [
    // The same admin key as unas/config.php; only used to create the very first Filament admin.
    'admin_key' => env('GOLDENTOR_ADMIN_KEY'),
    'public_url' => env('GOLDENTOR_PUBLIC_URL', 'https://goldentor.hu'),
    'mail_from' => env('GOLDENTOR_MAIL_FROM', 'info@goldentor.hu'),
    'contact_recipient' => env('GOLDENTOR_CONTACT_RECIPIENT', 'info@goldentor.hu'),
    'mail_enabled' => (bool) env('GOLDENTOR_MAIL_ENABLED', true),
    // Bookable consultation slots (same list as GOLDENTOR_SLOTS in unas/api.php).
    'slots' => ['09:00', '11:00', '14:00', '16:00'],
    'topics' => ['Befektetés', 'Ingatlan', 'Finanszírozás', 'Biztosítás', 'Komplex pénzügyi átvilágítás'],
    'meetings' => ['Online konzultáció', 'Személyes találkozó Győrben', 'Telefonos egyeztetés'],
];
