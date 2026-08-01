<?php

namespace App\Http\Requests\Common;

use App\Http\Requests\ApiFormRequest;

class ListQueryRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $sortBy = $this->input('sort_by');
        $sortDirection = $this->input('sort_direction');
        $sort = trim((string) $this->input('sort', ''));

        if ($sort !== '' && ! $sortBy) {
            [$sortBy, $sortDirection] = array_pad(explode(':', $sort, 2), 2, null);
        }

        $this->merge([
            'keyword' => $this->input('keyword', $this->input('q')),
            'per_page' => $this->input('per_page', $this->input('limit')),
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ]);
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'listing_type' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'payment_type' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'string', 'max:100'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'audit_type' => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'risk_level' => ['nullable', 'string', 'max:100'],
            'actor_type' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:150'],
            'entity_id' => ['nullable', 'string', 'max:150'],
            'request_id' => ['nullable', 'string', 'max:150'],
            'unread' => ['nullable', 'boolean'],
            'transaction_id' => ['nullable', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'owner_customer_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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

    public function sortBy(string $default = 'id'): string
    {
        return (string) $this->validated('sort_by', $default);
    }

    public function sortDirection(string $default = 'desc'): string
    {
        return $this->validated('sort_direction', $default) === 'asc' ? 'asc' : 'desc';
    }

    public function filters(array $allowed): array
    {
        return collect($allowed)
            ->filter(fn (string $field): bool => $this->filled($field))
            ->mapWithKeys(fn (string $field): array => [$field => $this->validated($field)])
            ->all();
    }
}
