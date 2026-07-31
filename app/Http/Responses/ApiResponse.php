<?php

namespace App\Http\Responses;

use App\Support\CorrelationContext;
use App\Support\Http\PaginationMeta;
use App\Support\MarketplaceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Thành công',
        int $code = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'status' => [
                'success' => true,
                'code' => $code,
                'message' => $message,
            ],
            'data' => $data,
        ];
        $combined = array_merge(self::requestMeta(), $meta);

        if ($combined !== []) {
            $payload['meta'] = $combined;
        }

        return response()->json($payload, $code)->withHeaders(self::headers());
    }

    public static function paginated(
        LengthAwarePaginator $paginator,
        mixed $data = null,
        string $message = 'Thành công',
        array $meta = [],
    ): JsonResponse {
        return self::success(
            $data ?? $paginator->items(),
            $message,
            200,
            array_merge(PaginationMeta::from($paginator), $meta),
        );
    }

    public static function error(
        string $message = 'Không thể xử lý yêu cầu.',
        mixed $errors = null,
        int $code = 400,
        array $meta = [],
    ): JsonResponse {
        $errorCode = $meta['error_code'] ?? null;
        unset($meta['error_code']);

        $payload = [
            'status' => [
                'success' => false,
                'code' => $code,
                'message' => $message,
            ],
            'message' => $message,
            'errors' => $errors,
            'meta' => array_merge(self::requestMeta(), $meta),
        ];

        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode;
        }

        return response()->json($payload, $code)->withHeaders(self::headers());
    }

    private static function requestMeta(): array
    {
        return array_filter([
            'request_id' => request()?->attributes->get('request_id'),
            'correlation_id' => CorrelationContext::resolve(
                request()?->attributes->get('correlation_id'),
            ),
            'contract_version' => MarketplaceContract::version(),
            'contract_hash' => MarketplaceContract::hash(),
        ]);
    }

    private static function headers(): array
    {
        return [
            'X-Marketplace-Contract-Version' => MarketplaceContract::version(),
            'X-Marketplace-Contract-Hash' => MarketplaceContract::hash(),
            'X-Api-Version' => MarketplaceContract::apiVersion(),
        ];
    }
}
