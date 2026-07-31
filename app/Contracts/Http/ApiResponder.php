<?php

namespace App\Contracts\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

interface ApiResponder
{
    public function success(mixed $data = null, string $message = 'Thành công', int $status = 200, array $meta = []): JsonResponse;

    public function error(string $message, int $status = 400, array $errors = [], array $meta = []): JsonResponse;

    public function paginated(LengthAwarePaginator $paginator, string $message = 'Thành công'): JsonResponse;
}
