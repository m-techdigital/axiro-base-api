<?php

return [
    /*
    | Payroll currently persists monetary values in DECIMAL(18, 2). Keep the
    | storage scale explicit so calculation, reconciliation and exports use the
    | same rounding contract. A future currency migration must version payslip
    | engines and historical snapshots rather than silently changing this value.
    */
    'currency' => env('PAYROLL_CURRENCY', 'VND'),
    'storage_scale' => (int) env('PAYROLL_STORAGE_SCALE', 2),
    'rounding_mode' => env('PAYROLL_ROUNDING_MODE', 'half_up'),
];
