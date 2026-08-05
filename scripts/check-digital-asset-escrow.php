<?php

$root = dirname(__DIR__);
$required = [
    'app/Models/EscrowBox.php',
    'app/Models/EscrowFeeRule.php',
    'app/Services/Marketplace/EscrowBoxService.php',
    'app/Services/Marketplace/EscrowBoxFeeService.php',
    'app/Services/Marketplace/EscrowBoxPresenter.php',
    'app/Services/Marketplace/EscrowBoxMediaService.php',
    'app/Rules/EscrowBoxPublicText.php',
    'app/Http/Controllers/CustomerEscrowBoxController.php',
    'app/Http/Controllers/AdminEscrowBoxController.php',
    'tests/Feature/EscrowBoxWorkflowTest.php',
    'tests/Feature/MarketplaceEscrowHandoverTest.php',
    'docs/canonical/ESCROW_BOX_CANONICAL_20260805.md',
];

foreach ($required as $relative) {
    if (! is_file($root.'/'.$relative)) {
        fwrite(STDERR, "Missing escrow box owner: {$relative}\n");
        exit(1);
    }
}

$expectations = [
    'database/migrations/2026_01_01_000475_create_marketplace_closure_tables.php' => [
        "Schema::create('escrow_boxes'",
        "Schema::create('escrow_fee_rules'",
        "Schema::create('escrow_box_agreement_versions'",
        "Schema::create('escrow_box_payment_obligations'",
        "Schema::create('escrow_box_handover_steps'",
        "Schema::create('escrow_box_media'",
        "invite_token_hash",
        "agreement_version",
        "expected_version",
    ],
    'app/Services/Marketplace/EscrowBoxService.php' => [
        "hash('sha256', \$rawToken)",
        "'invite_token_hash' => null",
        "'status' => 'admin_review'",
        "'status' => 'payment_pending'",
        "createFinancialAdapter(",
        "createObligations(",
        "createHandoverSteps(",
        "confirmReceipt(",
        "openDispute(",
    ],
    'app/Services/Marketplace/EscrowBoxPresenter.php' => [
        "'counterparty_label'",
        "'Bên A'",
        "'Bên B'",
        "except(['customer_id'",
    ],
    'app/Services/Marketplace/EscrowBoxMediaService.php' => [
        "Storage::disk('local')",
        'imagewebp',
        '1920',
        '480',
        "hash_file('sha256'",
    ],
    'routes/api/customer.php' => [
        "Route::post('escrow-boxes'",
        "escrow-boxes/join/{token}/claim",
        "escrow-boxes/{escrowBox}/confirm-receipt",
        "escrow-boxes/{escrowBox}/disputes",
    ],
    'routes/api/admin.php' => [
        "Route::get('escrow-boxes'",
        "escrow-boxes/{escrowBox}/review",
        "escrow-fee-rules",
    ],
];

foreach ($expectations as $relative => $needles) {
    $source = file_get_contents($root.'/'.$relative);
    foreach ($needles as $needle) {
        if (! str_contains($source, $needle)) {
            fwrite(STDERR, "Escrow box contract missing {$needle} in {$relative}\n");
            exit(1);
        }
    }
}

$forbidden = [
    'app/Services/Marketplace/DirectEscrowAgreementService.php',
    'app/Http/Requests/Customer/DirectEscrowCreateRequest.php',
    'tests/Feature/DirectEscrowAgreementTest.php',
];
foreach ($forbidden as $relative) {
    if (is_file($root.'/'.$relative)) {
        fwrite(STDERR, "Legacy direct escrow owner must not coexist: {$relative}\n");
        exit(1);
    }
}

$customerRoutes = file_get_contents($root.'/routes/api/customer.php');
foreach (["escrow-deals", "/escrow/accept", "/escrow/reject"] as $legacyRoute) {
    if (str_contains($customerRoutes, $legacyRoute)) {
        fwrite(STDERR, "Legacy direct escrow route still exists: {$legacyRoute}\n");
        exit(1);
    }
}

$contract = json_decode(file_get_contents($root.'/resources/contracts/marketplace-contract.json'), true, flags: JSON_THROW_ON_ERROR);
if (($contract['contract_version'] ?? null) !== '2026-08-05.3'
    || ! ($contract['capabilities']['private_escrow_box'] ?? false)
    || ! ($contract['capabilities']['escrow_box_one_time_invite'] ?? false)
    || ! ($contract['capabilities']['escrow_box_private_optimized_media'] ?? false)) {
    fwrite(STDERR, "Marketplace contract does not expose the canonical escrow box module.\n");
    exit(1);
}

$presenter = file_get_contents($root.'/app/Services/Marketplace/EscrowBoxPresenter.php');
foreach (['username', 'email', 'phone'] as $identityField) {
    if (! str_contains($presenter, $identityField)) {
        fwrite(STDERR, "Presenter sanitizer must explicitly remove {$identityField}.\n");
        exit(1);
    }
}

echo "Escrow box guard passed: privacy, one-time claim, versioned agreement, fee snapshot, payment, handover and media owners are intact.\n";
