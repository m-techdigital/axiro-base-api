<?php

return [
    'password' => [
        'min_length' => (int) env('SECURITY_PASSWORD_MIN_LENGTH', 8),
        'require_mixed_case' => filter_var(env('SECURITY_PASSWORD_REQUIRE_MIXED_CASE', true), FILTER_VALIDATE_BOOL),
        'require_numbers' => filter_var(env('SECURITY_PASSWORD_REQUIRE_NUMBERS', true), FILTER_VALIDATE_BOOL),
        'require_symbols' => filter_var(env('SECURITY_PASSWORD_REQUIRE_SYMBOLS', false), FILTER_VALIDATE_BOOL),
        'uncompromised' => filter_var(env('SECURITY_PASSWORD_UNCOMPROMISED', false), FILTER_VALIDATE_BOOL),
    ],
];
