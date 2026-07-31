<?php

namespace App\Http\Requests\Common;

use App\Http\Requests\ApiFormRequest;

class ListQueryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'listing_type' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'max:100'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ];
    }

    public function keyword(): ?string
    {
        $keyword = trim((string) $this->validated('keyword', ''));

        return $keyword !== '' ? $keyword : null;
    }

    public function perPage(int $default = 20): int
    {
        return min(100, max(1, $this->integer('per_page', $default)));
    }

    public function sortDirection(string $default = 'desc'): string
    {
        return $this->validated('sort_direction', $default) === 'asc'
            ? 'asc'
            : 'desc';
    }
}
