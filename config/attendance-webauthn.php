<?php

return [
    // Use the canonical employee portal URL, never a client-supplied Host header.
    'origin' => env('ATTENDANCE_WEBAUTHN_ORIGIN', env('APP_URL', 'http://localhost')),
    'rp_name' => env('ATTENDANCE_WEBAUTHN_RP_NAME', 'حلويات دهب'),
    'challenge_ttl_seconds' => 120,
    'minimum_checkout_gap_seconds' => 60,
];
