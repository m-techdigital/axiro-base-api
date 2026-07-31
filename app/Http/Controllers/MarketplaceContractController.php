<?php

namespace App\Http\Controllers;

use App\Support\MarketplaceContract;
use Illuminate\Http\JsonResponse;

class MarketplaceContractController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return success_response(MarketplaceContract::all(), 'Hợp đồng tích hợp Marketplace.');
    }
}
