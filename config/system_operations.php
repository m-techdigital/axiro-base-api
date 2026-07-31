<?php

return [
    'restore' => [
        'lease_seconds' => (int) env('SYSTEM_OPERATIONS_RESTORE_LEASE_SECONDS', 180),
        'allow_sql_ddl' => (bool) env('SYSTEM_OPERATIONS_ALLOW_SQL_DDL_RESTORE', false),
        'operation_retention_days' => (int) env('SYSTEM_OPERATIONS_OPERATION_RETENTION_DAYS', 90),
        'minimum_free_space_gb' => (int) env('SYSTEM_OPERATIONS_MINIMUM_FREE_SPACE_GB', 5),
    ],
];
