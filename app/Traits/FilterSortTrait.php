<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait FilterSortTrait
{
    protected function applyFilters(Builder $query, ?array $filters): Builder
    {
        if ($filters) {
            foreach ($filters as $filter) {
                $field = $filter['field'];
                $operator = $this->getOperator($filter['operator']);
                $value = $filter['value'];

                if ($operator === 'like') {
                    $query->where($field, $operator, '%' . $value . '%');
                } else {
                    $query->where($field, $operator, $value);
                }
            }
        }

        return $query;
    }

    protected function applySorts(Builder $query, ?array $sorts): Builder
    {
        if ($sorts) {
            foreach ($sorts as $sort) {
                $query->orderBy($sort['field'], $sort['direction']);
            }
        }

        return $query;
    }
    private function getOperator(string $operator): string
    {
        $operators = [
            'equals' => '=',
            'not' => '!=',
            'lt' => '<',
            'lte' => '<=',
            'gt' => '>',
            'gte' => '>=',
            'like' => 'like',
        ];

        return $operators[strtolower($operator)] ?? '=';
    }
}