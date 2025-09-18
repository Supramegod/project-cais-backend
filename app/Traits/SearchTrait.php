<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait SearchTrait
{
    protected function applySearch(Builder $query, array $columns): Builder
    {
        if ($search = request()->get('search')) {
            $query->where(function ($q) use ($columns, $search) {
                $searchTerm = '%' . $search . '%';

                foreach ($columns as $column) {

                    if (Str::contains($column, '.')) {
                        [$relation, $field] = explode('.', $column, 2);

                        $q->orWhereHas($relation, function ($subQuery) use ($field, $searchTerm) {
                            $subQuery->where($field, 'LIKE', $searchTerm);
                        });
                    } else {
                        $q->orWhere($column, 'LIKE', $searchTerm);
                    }
                }
            });
        }

        return $query;
    }
}
