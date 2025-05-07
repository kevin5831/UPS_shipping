<?php

$config = [
    'client_id' => env('UPS_CLIENT_ID'),
    'client_secret' => env('UPS_CLIENT_SECRET'),
    'sandbox' => env('UPS_SANDBOX', true),
    'account_number' => env('UPS_BILLING_ACCOUNT'),
    'production_url' => env('UPS_API_PRODUCTION_URL'),
    'sandbox_url' => env('UPS_API_SANDBOX_URL'),
];

return $config;