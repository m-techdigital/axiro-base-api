<?php

use App\Models\User;
use App\Support\MarketplaceContract;
use Illuminate\Http\JsonResponse;

if (! function_exists('user')) {
    function user(): ?User
    {
        return auth('api')->user();
    }
}

if (! function_exists('user_id')) {
    function user_id(): ?int
    {
        return user()?->id;
    }
}

if (! function_exists('marketplace_response_headers')) {
    function marketplace_response_headers(): array
    {
        return [
            'X-Marketplace-Contract-Version' => MarketplaceContract::version(),
            'X-Marketplace-Contract-Hash' => MarketplaceContract::hash(),
            'X-Api-Version' => MarketplaceContract::apiVersion(),
        ];
    }
}

if (! function_exists('success_response')) {
    function success_response(mixed $data = null, string $message = 'Thành công', int $code = 200, array $meta = []): JsonResponse
    {
        $requestMeta = array_filter([
            'request_id' => request()?->attributes->get('request_id'),
            'correlation_id' => request()?->attributes->get('correlation_id'),
            'contract_version' => MarketplaceContract::version(),
            'contract_hash' => MarketplaceContract::hash(),
        ]);

        $payload = [
            'status' => ['success' => true, 'code' => $code, 'message' => $message],
            'data' => $data,
        ];

        $combinedMeta = array_merge($requestMeta, $meta);
        if ($combinedMeta !== []) {
            $payload['meta'] = $combinedMeta;
        }

        return response()->json($payload, $code)->withHeaders(marketplace_response_headers());
    }
}

if (! function_exists('error_response')) {
    function error_response(string $message = 'Không thể xử lý yêu cầu.', mixed $errors = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'status' => ['success' => false, 'code' => $code, 'message' => $message],
            'message' => $message,
            'errors' => $errors,
            'meta' => [
                'request_id' => request()?->attributes->get('request_id'),
                'correlation_id' => request()?->attributes->get('correlation_id'),
                'contract_version' => MarketplaceContract::version(),
                'contract_hash' => MarketplaceContract::hash(),
            ],
        ], $code)->withHeaders(marketplace_response_headers());
    }
}
