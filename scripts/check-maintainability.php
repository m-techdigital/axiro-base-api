<?php

$root = dirname(__DIR__);
$failures = [];

$tracked = [];
exec('git -C '.escapeshellarg($root).' rev-parse --is-inside-work-tree 2>/dev/null', $gitProbe, $gitStatus);

if ($gitStatus === 0) {
    exec('git -C '.escapeshellarg($root).' ls-files 2>/dev/null', $tracked);
} else {
    $ignoredDirectories = [
        '.git' => true,
        'build' => true,
        'dist' => true,
        'node_modules' => true,
        'vendor' => true,
    ];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $current) use ($ignoredDirectories): bool {
                return ! ($current->isDir() && isset($ignoredDirectories[$current->getFilename()]));
            }
        )
    );

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }
        $tracked[] = ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR);
    }
}

foreach ([
    'config/crm.php',
    'config/models.php',
    'config/payroll.php',
    'config/reports.php',
    'config/system_operations.php',
] as $file) {
    if (in_array($file, $tracked, true) && file_exists($root.'/'.$file)) {
        $failures[] = "{$file}: parent/tooling-only config is not part of Mini MBN.";
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
    if (preg_match('/(^|[^a-z0-9])(company_id|company_member_id|company_member_ids|company_name|company_code|company_type|company_types|department_id|department_name|department_code|department_parent_id|department_manager_member_id|investor_company_id|customer_company_id|payroll|accounting|reports|crm|reservation|opportunity|opportunities|inventory|employee|employees|attendance|payslip|salary|recruitment|resignation|onboarding|offboarding)([^a-z0-9]|$)/i', $source)) {
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

$lifecycle = 'app/Services/Marketplace/TransactionLifecycleService.php';
if (file_exists($root.'/'.$lifecycle)) {
    $lines = count(file($root.'/'.$lifecycle));
    if ($lines > 340) {
        $failures[] = "{$lifecycle}: lifecycle facade is {$lines} lines; extracted owners must remain authoritative.";
    }
    $source = file_get_contents($root.'/'.$lifecycle);
    foreach (['reserveAvailable(', 'debitHeld(', 'restoreHeldToAvailable('] as $needle) {
        if (str_contains($source, $needle)) {
            $failures[] = "{$lifecycle}: direct wallet mutation {$needle} must stay in wallet/settlement owners.";
        }
    }
}

foreach ([
    'app/Services/Documents/MarketplaceDocumentPayloadBuilder.php',
    'app/Services/Documents/MarketplaceDocumentRenderer.php',
    'app/Services/Payouts/WithdrawalStateTransitionService.php',
    'app/Services/Marketplace/TransactionPaymentCaptureService.php',
    'app/Services/Marketplace/TransactionPaymentPlanService.php',
    'app/Services/Marketplace/TransactionSettlementService.php',
    'app/Services/Marketplace/TransactionDisputeResolutionService.php',
    'app/Services/Marketplace/TransactionActionPolicy.php',
    'app/Http/Requests/Customer/CreateWithdrawalRequest.php',
    'app/Http/Requests/Admin/RejectWithdrawalRequest.php',
    'app/Http/Requests/Admin/MarkWithdrawalPaidRequest.php',
] as $file) {
    if (! file_exists($root.'/'.$file)) {
        $failures[] = "{$file}: missing transaction/payout lifecycle owner.";
    }
}

$payoutController = 'app/Http/Controllers/CustomerPayoutController.php';
if (file_exists($root.'/'.$payoutController)) {
    $source = file_get_contents($root.'/'.$payoutController);
    if (str_contains($source, "->validate(['payout_account_id'")) {
        $failures[] = "{$payoutController}: withdrawal validation must stay in CreateWithdrawalRequest.";
    }
    if (! str_contains($source, 'cancelWithdrawal(')) {
        $failures[] = "{$payoutController}: customer withdrawal cancellation endpoint is missing.";
    }
}

$documentService = 'app/Services/Documents/MarketplaceDocumentService.php';
if (file_exists($root.'/'.$documentService)) {
    $source = file_get_contents($root.'/'.$documentService);
    if (count(file($root.'/'.$documentService)) > 170) {
        $failures[] = "{$documentService}: document coordinator must remain below 170 lines.";
    }
    foreach (['new Dompdf', 'function money(', 'function label('] as $needle) {
        if (str_contains($source, $needle)) {
            $failures[] = "{$documentService}: rendering/payload concern {$needle} belongs in extracted owners.";
        }
    }
}

$withdrawalService = 'app/Services/Payouts/WithdrawalService.php';
if (file_exists($root.'/'.$withdrawalService)) {
    $source = file_get_contents($root.'/'.$withdrawalService);
    if (count(file($root.'/'.$withdrawalService)) > 100) {
        $failures[] = "{$withdrawalService}: payout facade must remain below 100 lines.";
    }
    foreach (['restoreHeldToAvailable(', 'debitHeld(', "status' => 'approved'"] as $needle) {
        if (str_contains($source, $needle)) {
            $failures[] = "{$withdrawalService}: payout transition {$needle} belongs in WithdrawalStateTransitionService.";
        }
    }
}

$documentVersioning = 'app/Services/Documents/DocumentTemplateVersioningService.php';
if (! file_exists($root.'/'.$documentVersioning)) {
    $failures[] = $documentVersioning.': missing immutable template version owner.';
} else {
    $source = file_get_contents($root.'/'.$documentVersioning);
    foreach (["status' => 'deprecated'", 'supersedes_template_id', 'generatedDocuments()->exists()'] as $needle) {
        if (! str_contains($source, $needle)) {
            $failures[] = "{$documentVersioning}: missing immutable versioning contract {$needle}.";
        }
    }
    if (str_contains($source, 'republishIssuedDocuments')) {
        $failures[] = "{$documentVersioning}: historical documents must not be republished when a template changes.";
    }
}

$documentController = 'app/Http/Controllers/DocumentTemplateController.php';
if (file_exists($root.'/'.$documentController)) {
    $source = file_get_contents($root.'/'.$documentController);
    foreach (["withCount('generatedDocuments')", 'draft,published,deprecated'] as $needle) {
        if (! str_contains($source, $needle)) {
            $failures[] = "{$documentController}: missing document lifecycle surface {$needle}.";
        }
    }
}

$actionCenter = 'app/Http/Controllers/AdminActionCenterController.php';
if (file_exists($root.'/'.$actionCenter)) {
    $source = file_get_contents($root.'/'.$actionCenter);
    foreach (['rental_deposit_review', 'pending_payouts', 'expired_holds', "approval_status', 'pending"] as $needle) {
        if (! str_contains($source, $needle)) {
            $failures[] = "{$actionCenter}: missing operational queue {$needle}.";
        }
    }
}

$releaseRunner = $root.'/scripts/release-all.sh';
if (! file_exists($releaseRunner)) {
    $failures[] = 'scripts/release-all.sh: missing one-command release runner.';
} else {
    $source = file_get_contents($releaseRunner);
    foreach (['BUNDLE_BUDGET_STRICT=1', 'AXIRO_RELEASE_ALLOW_BUNDLE_WAIVER', 'e2e:browser-core', 'e2e:transactional-api', 'e2e:browser-crud'] as $needle) {
        if (! str_contains($source, $needle)) {
            $failures[] = "scripts/release-all.sh: missing release gate {$needle}.";
        }
    }
}

foreach ([
    'tests/Feature/MiniCustomerIsolationContractTest.php',
    'tests/Feature/TransactionCommandCenterContractTest.php',
] as $testFile) {
    if (! file_exists($root.'/'.$testFile)) {
        $failures[] = "{$testFile}: missing Mini customer isolation/lifecycle regression coverage.";
    }
}

$isolationTest = $root.'/tests/Feature/MiniCustomerIsolationContractTest.php';
if (file_exists($isolationTest)) {
    $source = file_get_contents($isolationTest);
    foreach (['customer/payouts', 'demo-withdrawal-submitted', 'assertNotFound'] as $needle) {
        if (! str_contains($source, $needle)) {
            $failures[] = "tests/Feature/MiniCustomerIsolationContractTest.php: missing payout isolation marker {$needle}.";
        }
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

echo 'Maintainability guard passed: Mini boundaries, versioned filenames and operations owners are stable.'.PHP_EOL;
