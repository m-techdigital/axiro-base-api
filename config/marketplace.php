<?php

return [
    'bank' => [
        'id' => env('MARKETPLACE_BANK_ID', 'MB'),
        'name' => env('MARKETPLACE_BANK_NAME', 'MB BANK'),
        'account_no' => env('MARKETPLACE_BANK_ACCOUNT_NO', '0123456789'),
        'account_name' => env('MARKETPLACE_BANK_ACCOUNT_NAME', 'NGUYEN VAN A'),
        'qr_template' => env('MARKETPLACE_BANK_QR_TEMPLATE', 'compact2'),
        'transfer_prefix' => env('MARKETPLACE_BANK_TRANSFER_PREFIX', 'MBN'),
    ],
    'risk' => [
        'withdrawal_review_threshold' => env('MARKETPLACE_WITHDRAWAL_REVIEW_THRESHOLD', '10000000'),
    ],
];
