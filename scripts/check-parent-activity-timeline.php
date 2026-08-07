<?php

$required = [
    'app/Services/Marketplace/EscrowBoxTimelineService.php',
    'app/Http/Controllers/AdminEscrowBoxController.php',
    'app/Http/Controllers/CustomerEscrowBoxController.php',
    'tests/Feature/EscrowBoxTimelineContractTest.php',
];
foreach ($required as $file) {
    if (! is_file($file)) {
        throw new RuntimeException("Missing parent timeline owner: {$file}");
    }
}

$service = file_get_contents('app/Services/Marketplace/EscrowBoxTimelineService.php');
foreach (['activity_type', 'activity_subtype', 'changed_by', 'created_at', 'metadata', 'occurred_at'] as $key) {
    if (! str_contains($service, "'{$key}'")) {
        throw new RuntimeException("Timeline contract missing {$key}");
    }
}
foreach (['->paginate(', 'ACTIVITY_EVENT_TYPES', 'whereNotIn', 'setCollection'] as $sourceRule) {
    if (! str_contains($service, $sourceRule)) {
        throw new RuntimeException("Timeline query boundary missing {$sourceRule}");
    }
}
if (str_contains($service, "loadMissing(['events'")) {
    throw new RuntimeException('Timeline must paginate/filter in SQL instead of loading all Box events');
}

$routes = file_get_contents('routes/api/admin.php').file_get_contents('routes/api/customer.php');
if (substr_count($routes, 'escrow-boxes/{escrowBox}/timeline') < 2) {
    throw new RuntimeException('Admin and customer timeline routes are required');
}

$contract = json_decode(file_get_contents('resources/contracts/marketplace-contract.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ([
    ['admin_endpoints', 'GET /escrow-boxes/{escrowBox}/timeline'],
    ['customer_endpoints', 'GET /customer/escrow-boxes/{escrowBox}/timeline'],
] as [$group, $endpoint]) {
    if (! in_array($endpoint, $contract[$group] ?? [], true)) {
        throw new RuntimeException("Marketplace contract missing {$endpoint}");
    }
}

echo "Parent activity timeline alignment: PASS\n";
