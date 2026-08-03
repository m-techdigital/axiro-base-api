<?php

$root = dirname(__DIR__);
$failures = [];
$tracked = [];
exec('git ls-files', $tracked);

foreach (['config/crm.php', 'config/payroll.php', 'config/reports.php'] as $file) {
    if (in_array($file, $tracked, true) && file_exists($root.'/'.$file)) {
        $failures[] = "{$file}: parent-only config is not part of Mini MBN.";
    }
}

foreach ($tracked as $file) {
    if (! file_exists($root.'/'.$file)) {
        continue;
    }
    if (preg_match('/(?:^|[-_])v\d{2,}(?:[-_.]|$)/i', basename($file))) {
        $failures[] = "{$file}: file name must not use V55/V66-style version markers.";
    }
}

$forbiddenParentDomains = [
    'App\\Models\\Company',
    'App\\Models\\Department',
    'App\\Models\\Project',
    'App\\Services\\Accounting',
    'App\\Services\\Reports',
    'App\\Services\\Hr',
    'Spatie\\Permission',
];

foreach ($tracked as $file) {
    if (! file_exists($root.'/'.$file)) {
        continue;
    }
    if (! str_ends_with($file, '.php') || str_starts_with($file, 'vendor/')) {
        continue;
    }
    $source = file_get_contents($root.'/'.$file);
    foreach ($forbiddenParentDomains as $needle) {
        if (str_contains($source, $needle)) {
            $failures[] = "{$file}: Mini must not import parent-only domain {$needle}.";
        }
    }
}

foreach ($tracked as $file) {
    if (! file_exists($root.'/'.$file)) {
        continue;
    }
    if (! preg_match('#^(app|database|routes|config|lang)/#', $file)) {
        continue;
    }
    if (! preg_match('/\.(php|json|js|jsx|ts|tsx)$/', $file)) {
        continue;
    }
    $source = file_get_contents($root.'/'.$file);
    if (preg_match('/\b(company_id|company_member_id|company_member_ids|company_name|company_code|company_type|company_types|department_id|department_name|department_code|department_parent_id|department_manager_member_id|investor_company_id|customer_company_id|payroll|accounting|reports|crm|reservation|opportunity|opportunities|inventory|employee|employees|attendance|payslip|salary|recruitment|resignation|onboarding|offboarding)\b/i', $source)) {
        $failures[] = "{$file}: runtime/lang source must not carry parent-only domain scope in Mini.";
    }
}

$controller = 'app/Http/Controllers/MarketplaceOperationsDashboardController.php';
if (file_exists($root.'/'.$controller)) {
    $lines = count(file($root.'/'.$controller));
    if ($lines > 180) {
        $failures[] = "{$controller}: controller is {$lines} lines; keep HTTP ownership thin.";
    }
    $source = file_get_contents($root.'/'.$controller);
    foreach (['fputcsv(', 'MarketplaceExportRequest::query()', 'ProductHold::query()->where'] as $needle) {
        if (str_contains($source, $needle)) {
            $failures[] = "{$controller}: found {$needle}; export/hold lifecycle logic belongs in service owners.";
        }
    }
}

foreach ([
    'app/Services/Marketplace/Operations/ProductHoldReleaseService.php',
    'app/Services/Marketplace/Operations/RentalSettlementExportService.php',
] as $file) {
    if (! file_exists($root.'/'.$file)) {
        $failures[] = "{$file}: missing extracted operations owner.";
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

echo 'Maintainability guard passed: Mini boundaries, versioned filenames and operations owners are stable.'.PHP_EOL;
