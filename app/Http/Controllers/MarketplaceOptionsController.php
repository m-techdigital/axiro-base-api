<?php

namespace App\Http\Controllers;

use App\Enums\DisputeOutcome;
use App\Support\Marketplace\DocumentType;

class MarketplaceOptionsController extends Controller
{
    public function __invoke()
    {
        return success_response([
            'document_types' => DocumentType::options(),
            'dispute_outcomes' => DisputeOutcome::options(),
        ]);
    }
}
