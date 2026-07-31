<?php

namespace App\Http\Responses;

use App\Contracts\Http\ApiResponder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class DefaultApiResponder implements ApiResponder
{
    public function success(mixed $data = null, string $message = 'Thành công', int $status = 200, array $meta = []): JsonResponse
    {
        return ApiResponse::success($data, $message, $status, $meta);
    }

    public function error(string $message, int $status = 400, array $errors = [], array $meta = []): JsonResponse
    {
        return ApiResponse::error($message, $errors, $status, $meta);
    }

    public function paginated(LengthAwarePaginator $paginator, string $message = 'Thành công'): JsonResponse
    {
        return ApiResponse::paginated($paginator, null, $message);
    }
}
