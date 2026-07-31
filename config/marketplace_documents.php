<?php

return [
    'operator' => [
        'name' => env('MARKETPLACE_OPERATOR_NAME', 'CÔNG TY/ĐƠN VỊ VẬN HÀNH MBN'),
        'tax_code' => env('MARKETPLACE_OPERATOR_TAX_CODE', 'ĐANG CẬP NHẬT'),
        'address' => env('MARKETPLACE_OPERATOR_ADDRESS', 'ĐANG CẬP NHẬT'),
        'support_phone' => env('MARKETPLACE_SUPPORT_PHONE', 'ĐANG CẬP NHẬT'),
        'support_email' => env('MARKETPLACE_SUPPORT_EMAIL', 'support@example.com'),
        'website' => env('MARKETPLACE_WEBSITE', 'https://example.com'),
    ],
    'policy_version' => env('MARKETPLACE_POLICY_VERSION', '2026.07'),
    'acceptance_statement' => 'Tôi đã đọc toàn bộ tài liệu, kiểm tra thông tin giao dịch và đồng ý xác nhận bằng phương thức điện tử trên hệ thống.',
];
