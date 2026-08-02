<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Support\Marketplace\MarketplaceOptionsCatalog;
use App\Support\MarketplaceContract;
use Illuminate\Http\Request;

class MarketplaceOptionsController extends Controller
{
    public function __invoke(Request $request)
    {
        $etag = '"'.MarketplaceOptionsCatalog::hash().'"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->withHeaders($this->cacheHeaders($etag));
        }

        return ApiResponse::success(
            MarketplaceOptionsCatalog::payload(),
            'Thành công',
            200,
            MarketplaceOptionsCatalog::meta(),
        )->withHeaders($this->cacheHeaders($etag));
    }

    private function cacheHeaders(string $etag): array
    {
        return [
            'Cache-Control' => 'public, max-age='.MarketplaceOptionsCatalog::CACHE_TTL_SECONDS.', stale-while-revalidate=60',
            'ETag' => $etag,
            'X-Marketplace-Options-Version' => MarketplaceOptionsCatalog::VERSION,
            'X-Marketplace-Options-Hash' => MarketplaceOptionsCatalog::hash(),
            'X-Marketplace-Contract-Version' => MarketplaceContract::version(),
            'X-Marketplace-Contract-Hash' => MarketplaceContract::hash(),
        ];
    }
}
