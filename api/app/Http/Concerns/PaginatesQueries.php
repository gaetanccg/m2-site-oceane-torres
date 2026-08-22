<?php

namespace App\Http\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait PaginatesQueries
{
    private const PER_PAGE_MAX = 100;

    protected function paginated(Builder $query, Request $request, int $default = 20): LengthAwarePaginator
    {
        $perPage = (int) $request->input('per_page', $default);

        if ($perPage < 1) {
            $perPage = $default;
        }

        return $query->paginate(min($perPage, self::PER_PAGE_MAX));
    }

    /**
     * Tri contrôlé par une allowlist : `sort_by` ne doit jamais atteindre le SQL
     * sans avoir été validé contre les colonnes explicitement autorisées.
     *
     * @param  list<string>  $sortable
     */
    protected function sorted(Builder $query, Request $request, array $sortable, string $default = 'created_at'): Builder
    {
        $column = (string) $request->input('sort_by', $default);

        if (! in_array($column, $sortable, true)) {
            $column = $default;
        }

        $direction = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $direction);
    }
}
