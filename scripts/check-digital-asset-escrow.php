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
    'app/Http/Requests/Admin/EscrowBoxCreateRequest.php',
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
        'invite_token_hash',
        'party_a_invite_token_hash',
        'party_b_invite_token_hash',
        'agreement_version',
        'expected_version',
        'party_a_invite_accepted_at',
        'party_b_invite_accepted_at',
    ],
    'app/Services/Marketplace/EscrowBoxService.php' => [
        "hash('sha256', \$rawToken)",
        "'invite_token_hash' => null",
        "'status' => 'admin_review'",
        "'status' => 'payment_pending'",
        'createFinancialAdapter(',
        'createObligations(',
        'createHandoverSteps(',
        'confirmReceipt(',
        'openDispute(',
        'createByAdmin(',
        'acceptAssignedInvite(',
        'rotateAssignedInvites(',
        'rotateCustomerInvite(',
        'cloneCancelled(',
        'cancelByAdmin(',
        'assignedInvite(',
        'resolveCounterpartyByPhone(',
        'inviteCounterpartyCandidate(',
        'maskedCustomerLabel(',
        'cancelCounterpartyInvite(',
        'acceptCounterpartyInvite(',
        'phoneLookupVariants(',
        'escrow_box_invite_cancelled',
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
    'app/Http/Requests/Customer/EscrowBoxCounterpartyResolveRequest.php' => [
        "preg_replace('/\\D+/'",
        "'phone' => ['required', 'string']",
    ],
    'app/Http/Requests/Customer/EscrowBoxCounterpartyInviteRequest.php' => [
        "'candidate_token' => ['required', 'string']",
        'Vui lòng tìm và chọn khách hàng Bên B',
    ],
    'app/Http/Requests/Customer/EscrowBoxCreateRequest.php' => [
        'exclude_unless:deal_type,exchange_with_topup',
        'HasEscrowBoxValidationAttributes',
    ],
    'app/Http/Requests/Concerns/HasEscrowBoxValidationAttributes.php' => [
        'Số tiền bù phải tối thiểu 1.000 đ.',
        'topup_amount.min',
    ],
    'routes/api/customer.php' => [
        "Route::post('escrow-boxes'",
        'escrow-boxes/join/{token}/claim',
        'escrow-boxes/assigned-invite/{token}',
        'assigned-invite/{token}/accept',
        'escrow-boxes/{escrowBox}/confirm-receipt',
        'escrow-boxes/{escrowBox}/disputes',
        'escrow-boxes/{escrowBox}/invite/rotate',
        'escrow-boxes/{escrowBox}/clone',
        'escrow-boxes/{escrowBox}/counterparty-candidates/resolve',
        'escrow-boxes/{escrowBox}/counterparty-invite',
    ],
    'routes/api/admin.php' => [
        "Route::get('escrow-boxes'",
        "Route::post('escrow-boxes'",
        'escrow-boxes/{escrowBox}/invites/rotate',
        'escrow-boxes/{escrowBox}/cancel',
        'escrow-boxes/{escrowBox}/review',
        'escrow-fee-rules',
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
foreach (['escrow-deals', '/escrow/accept', '/escrow/reject'] as $legacyRoute) {
    if (str_contains($customerRoutes, $legacyRoute)) {
        fwrite(STDERR, "Legacy direct escrow route still exists: {$legacyRoute}\n");
        exit(1);
    }
}

$model = file_get_contents($root.'/app/Models/EscrowBox.php');
foreach (['invite_token_hash', 'party_a_invite_token_hash', 'party_b_invite_token_hash'] as $hiddenToken) {
    if (! str_contains($model, $hiddenToken)) {
        fwrite(STDERR, "Escrow token hash is not protected: {$hiddenToken}\n");
        exit(1);
    }
}
$service = file_get_contents($root.'/app/Services/Marketplace/EscrowBoxService.php');
if (! str_contains($service, 'Admin sẽ gửi link xác nhận riêng') || ! str_contains($service, "'party_a_invite_path' => '/escrow-box/accept/'")) {
    fwrite(STDERR, "Assigned invite delivery must return raw links once without persisting them in notification action URLs.\n");
    exit(1);
}

$contract = json_decode(file_get_contents($root.'/resources/contracts/marketplace-contract.json'), true, flags: JSON_THROW_ON_ERROR);
if (($contract['contract_version'] ?? null) !== '2026-08-06.6'
    || ! ($contract['capabilities']['private_escrow_box'] ?? false)
    || ! ($contract['capabilities']['escrow_box_one_time_invite'] ?? false)
    || ! ($contract['capabilities']['escrow_box_private_optimized_media'] ?? false)
    || ! ($contract['capabilities']['escrow_box_admin_assigned_parties'] ?? false)
    || ! ($contract['capabilities']['escrow_box_dual_private_acceptance_links'] ?? false)
    || ! ($contract['capabilities']['escrow_box_phone_counterparty_invite'] ?? false)
    || ! ($contract['capabilities']['escrow_box_field_validation_mapping'] ?? false)
    || ! ($contract['capabilities']['escrow_box_masked_counterparty_resolution'] ?? false)
    || ! ($contract['capabilities']['escrow_box_update_history_detail'] ?? false)
    || ! ($contract['capabilities']['escrow_box_parent_activity_timeline'] ?? false)) {
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
