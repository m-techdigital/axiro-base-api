<?php

namespace App\Support\Query;

use App\Http\Requests\Common\ListQueryRequest;
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

        foreach ($exactFilters as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->validated($filter));
            }
        }

        $sortBy = (string) $request->validated('sort_by', $defaultSort);
        if (! in_array($sortBy, $sortableColumns ?: [$defaultSort], true)) {
            $sortBy = $defaultSort;
        }

        return $query->orderBy($sortBy, $request->sortDirection());
    }
}
