<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Third-Party / External Services
|--------------------------------------------------------------------------
*/

return [
    /*
    |---------------------------------------------------------------------
    | BIR e-Invoicing System (EIS) — RR 8-2022, RR 6-2024
    |---------------------------------------------------------------------
    | Set BIR_EIS_ENABLED=true once the sandbox/production credentials and
    | X.509 signing certificate are provisioned. Until then, the listener
    | logs `tax.eis_submissions` rows in 'pending' status but does not
    | dispatch the transmission job.
    */
    'bir_eis' => [
        'enabled'        => env('BIR_EIS_ENABLED', false),
        'base_url'       => env('BIR_EIS_BASE_URL', 'https://eis-sandbox.bir.gov.ph'),
        'issuance_path'  => env('BIR_EIS_ISSUANCE_PATH', '/api/v1/invoices'),

        // OAuth credentials — obtained from BIR EIS sandbox onboarding.
        // The provisioned bearer token is short-lived; in production a
        // token-refresh job rotates it ahead of expiry.
        'client_id'      => env('BIR_EIS_CLIENT_ID'),
        'client_secret'  => env('BIR_EIS_CLIENT_SECRET'),
        'bearer_token'   => env('BIR_EIS_BEARER_TOKEN'),

        // PKCS#12 signing cert provisioned by BIR ASTRA. Passphrase comes
        // from a separate env so the .p12 can be checked into secrets
        // management without its passphrase ever being colocated.
        'cert_path'      => env('BIR_EIS_CERT_PATH', 'storage/certs/eis-signing.p12'),
        'cert_passphrase'=> env('BIR_EIS_CERT_PASSPHRASE'),

        'timeout'        => (int) env('BIR_EIS_TIMEOUT', 30),
        'retry_max_hours'=> (int) env('BIR_EIS_RETRY_MAX_HOURS', 24),
    ],

    'bir_efps' => [
        'enabled'   => env('BIR_EFPS_ENABLED', false),
        'base_url'  => env('BIR_EFPS_BASE_URL'),
        'tin'       => env('BIR_TIN'),
    ],

    'bsp_fx' => [
        'url' => env('BSP_FX_URL', 'https://www.bsp.gov.ph/statistics/sdds/exchrate.htm'),
    ],

    'semaphore' => [
        'api_key'     => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME', 'PHA'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
    ],
];
