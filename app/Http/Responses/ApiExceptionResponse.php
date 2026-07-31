<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiExceptionResponse
{
    public static function unauthenticated(Request $request): JsonResponse
    {
        return ApiResponse::error(
            'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.',
            null,
            401,
            ['error_code' => 'UNAUTHENTICATED'],
        );
    }

    public static function validation(Request $request, array $errors): JsonResponse
    {
        return ApiResponse::error(
            'Dữ liệu chưa hợp lệ. Vui lòng kiểm tra các trường được đánh dấu.',
            $errors,
            422,
            ['error_code' => 'VALIDATION_FAILED'],
        );
    }
}
