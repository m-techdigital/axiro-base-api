<?php

namespace App\Http\Controllers;

use App\Support\MarketplaceContract;
use Illuminate\Http\JsonResponse;

class RuntimeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return success_response(MarketplaceContract::runtime(), 'Thông tin phiên bản hệ thống.');
    }
}
