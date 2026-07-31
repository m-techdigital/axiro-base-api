<?php

return [
    'cache' => [
        'enabled' => false,
        'ttl_seconds' => (int) env('REPORT_AGGREGATE_CACHE_TTL', 60),
        'store' => env('REPORT_AGGREGATE_CACHE_STORE'),
        'version' => (string) env('REPORT_AGGREGATE_CACHE_VERSION', 'v4'),
        'datasets' => [
            'transactions' => 30,
            'customers' => 60,
            'products' => 120,
            'contracts' => 60,
            'employee_payroll_periods' => 30,
            'employee_attendance_periods' => 60,
            'payroll_accounting_sync_attempts' => 30,
        ],
    ],

    'timezone' => [
        'default' => env('REPORT_TIMEZONE', config('app.timezone', 'Asia/Ho_Chi_Minh')),
        'storage' => env('REPORT_STORAGE_TIMEZONE', 'UTC'),
        'allow_user_override' => (bool) env('REPORT_ALLOW_TIMEZONE_OVERRIDE', false),
        'mysql_use_named_timezones' => (bool) env('REPORT_MYSQL_NAMED_TIMEZONES', false),
        'storage_offset' => env('REPORT_STORAGE_TIMEZONE_OFFSET', '+00:00'),
        'default_offset' => env('REPORT_TIMEZONE_OFFSET', '+07:00'),
    ],

    'export' => [
        'sync_row_limit' => (int) env('REPORT_SYNC_EXPORT_ROW_LIMIT', 5000),
        'chunk_size' => (int) env('REPORT_EXPORT_CHUNK_SIZE', 1000),
        'aggregate_row_limit' => (int) env('REPORT_EXPORT_AGGREGATE_ROW_LIMIT', 100000),
        'disk' => env('REPORT_EXPORT_DISK', 'local'),
        'retention_days' => (int) env('REPORT_EXPORT_RETENTION_DAYS', 7),
        'rate_limit_per_minute' => (int) env('REPORT_EXPORT_RATE_LIMIT_PER_MINUTE', 6),
        'quota' => [
            'active_jobs' => (int) env('REPORT_EXPORT_ACTIVE_JOB_LIMIT', 3),
            'daily_jobs' => (int) env('REPORT_EXPORT_DAILY_JOB_LIMIT', 20),
        ],
    ],

    'telemetry' => [
        'enabled' => (bool) env('REPORT_TELEMETRY_ENABLED', true),
        'log_fallback' => (bool) env('REPORT_TELEMETRY_LOG_FALLBACK', true),
        'sample_rate' => max(0.0, min(1.0, (float) env('REPORT_TELEMETRY_SAMPLE_RATE', 1.0))),
    ],

    'audit' => [
        'enabled' => (bool) env('REPORT_QUERY_AUDIT_ENABLED', true),
        'sample_limit' => (int) env('REPORT_QUERY_AUDIT_SAMPLE_LIMIT', 200),
        'min_duration_ms' => (int) env('REPORT_QUERY_AUDIT_MIN_DURATION_MS', 500),
        'sample_rate' => max(0.0, min(1.0, (float) env('REPORT_QUERY_AUDIT_SAMPLE_RATE', 0.1))),
    ],

    'dashboard' => [
        'max_widgets' => (int) env('REPORT_DASHBOARD_MAX_WIDGETS', 50),
        'duration_budget_ms' => (int) env('REPORT_DASHBOARD_DURATION_BUDGET_MS', 8000),
        'execution_budget' => (int) env('REPORT_DASHBOARD_EXECUTION_BUDGET', 30),
    ],

    'runtime' => [
        'budgets' => [
            'preview' => [
                'duration_ms' => (int) env('REPORT_PREVIEW_DURATION_BUDGET_MS', 1500),
                'query_count' => (int) env('REPORT_PREVIEW_QUERY_BUDGET', 8),
            ],
            'runtime' => [
                'duration_ms' => (int) env('REPORT_RUNTIME_DURATION_BUDGET_MS', 2500),
                'query_count' => (int) env('REPORT_RUNTIME_QUERY_BUDGET', 8),
            ],
            'export' => [
                'duration_ms' => (int) env('REPORT_EXPORT_DURATION_BUDGET_MS', 5000),
                'query_count' => (int) env('REPORT_EXPORT_QUERY_BUDGET', 8),
            ],
        ],
    ],
];
