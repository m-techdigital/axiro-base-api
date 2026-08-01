<?php

use App\Http\Responses\ApiResponse;
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
        return ['X-Marketplace-Contract-Version' => MarketplaceContract::version(), 'X-Marketplace-Contract-Hash' => MarketplaceContract::hash(), 'X-Api-Version' => MarketplaceContract::apiVersion()];
    }
}
if (! function_exists('success_response')) {
    function success_response(mixed $data = null, string $message = 'Thành công', int $code = 200, array $meta = []): JsonResponse
    {
        return ApiResponse::success($data, $message, $code, $meta);
    }
}
if (! function_exists('pagy_success_response')) {
    function pagy_success_response(mixed $paginator, mixed $data = null, string $message = 'Thành công', int $code = 200, array $meta = []): JsonResponse
    {
        return ApiResponse::paginated($paginator, $data, $message, $meta);
    }
}
if (! function_exists('error_response')) {
    function error_response(string $message = 'Không thể xử lý yêu cầu.', mixed $errors = null, int $code = 400): JsonResponse
    {
        return ApiResponse::error($message, $errors, $code);
    }
}
