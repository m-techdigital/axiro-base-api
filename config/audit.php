<?php

return [
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),
    'validation_retention_days' => (int) env('AUDIT_VALIDATION_RETENTION_DAYS', 90),
    'max_payload_depth' => 8,
];
