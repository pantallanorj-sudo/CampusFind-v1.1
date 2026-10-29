<?php
/**
 * CampusFind application settings.
 * This file only returns a plain array. No secrets are stored here.
 */
declare(strict_types=1);

return [
    'app_name'        => 'CampusFind',
    'school_name'     => 'La Verdad Christian College',          // fictional school used for the demo
    'office_name'     => 'Student Affairs Office, Lost & Found Desk',
    'office_location' => 'Ground Floor, La Verdad Christian College, Apalit, Pampanga, Philippines',
    'office_hours'    => 'Monday to Friday, 8:00 AM to 5:00 PM',
    'timezone'        => 'Asia/Manila',

    // Leave null to auto-detect the folder the project is served from
    // (works for php -S, XAMPP htdocs/CampusFind, etc.). Example override: '/CampusFind'
    'base_url'        => '',

    // Set to true while developing to see error details on the error page.
    'debug'           => false,

    'cards_per_page'  => 9,
    'rows_per_page'   => 10,

    'upload_max_bytes' => 2 * 1024 * 1024,   // 2 MB
];
