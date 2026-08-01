<?php

namespace App\Enums;

enum ProductSelectionContext: string
{
    case MANAGEMENT = 'management';
    case PUBLIC_MARKETPLACE = 'public_marketplace';
    case TRANSACTION = 'transaction';
    case RENTAL = 'rental';
    case ADMIN_REVIEW = 'admin_review';
}
