<?php

$root = dirname(__DIR__);
$failures = [];
$tracked = [];
exec('git ls-files', $tracked);

foreach ($tracked as $file) {
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
