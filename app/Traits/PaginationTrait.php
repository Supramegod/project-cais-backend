<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait PaginationTrait
{
    protected function paginateData(Builder $query, $perPage = null)
    {
        $perPage = $perPage ?? request()->get('per_page', 10);

        $data = $query->paginate($perPage);

        return [
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'has_more' => $data->hasMorePages()
            ]
        ];
    }
}
