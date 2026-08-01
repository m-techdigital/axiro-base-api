<?php

namespace App\Support\Query;

use App\Http\Requests\Common\ListQueryRequest;
use Closure;
use Illuminate\Database\Eloquent\Builder;

trait AppliesListQuery
{
    protected function applyListFilters(
        Builder $query,
        ListQueryRequest $request,
        array $searchColumns = [],
        array $exactFilters = ['status'],
        array $sortableColumns = [],
        string $defaultSort = 'id',
        ?string $dateColumn = 'created_at',
        array $customFilters = [],
    ): Builder {
        $keyword = $request->keyword();

        if ($keyword && $searchColumns !== []) {
            $query->where(function (Builder $nested) use ($keyword, $searchColumns): void {
                foreach ($searchColumns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $nested->{$method}($column, 'like', "%{$keyword}%");
                }
            });
        }

        foreach ($request->filters(array_keys($exactFilters) === range(0, count($exactFilters) - 1) ? $exactFilters : array_keys($exactFilters)) as $filter => $value) {
            $column = $exactFilters[$filter] ?? $filter;
            $query->where($column, $value);
        }

        foreach ($customFilters as $field => $callback) {
            if ($request->filled($field) && $callback instanceof Closure) {
                $callback($query, $request->validated($field), $request);
            }
        }

        if ($dateColumn && $request->filled('date_from')) {
            $query->whereDate($dateColumn, '>=', $request->validated('date_from'));
        }

        if ($dateColumn && $request->filled('date_to')) {
            $query->whereDate($dateColumn, '<=', $request->validated('date_to'));
        }

        $sortBy = $request->sortBy($defaultSort);
        if (! in_array($sortBy, $sortableColumns ?: [$defaultSort], true)) {
            $sortBy = $defaultSort;
        }

        return $query->orderBy($sortBy, $request->sortDirection());
    }
}
