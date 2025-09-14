<?php

namespace Qnox\Crud\Support;

use Illuminate\Database\Eloquent\Builder;

class QueryFilters
{
    public function __construct(protected Builder $builder, protected array $filterable = [])
    {
    }

    public function apply(array $filters): Builder
    {
        foreach ($filters as $field => $ops) {
            if (! in_array($field, $this->filterable)) continue;

            if (is_array($ops)) {
                foreach ($ops as $op => $value) $this->applyOp($field, $op, $value);
            } else {
                $this->applyOp($field, 'eq', $ops);
            }
        }
        return $this->builder;
    }

    protected function applyOp(string $field, string $op, $value): void
    {
        switch ($op) {
            case 'eq': $this->builder->where($field, '=', $value); break;
            case 'neq': $this->builder->where($field, '!=', $value); break;
            case 'gt': $this->builder->where($field, '>', $value); break;
            case 'gte': $this->builder->where($field, '>=', $value); break;
            case 'lt': $this->builder->where($field, '<', $value); break;
            case 'lte': $this->builder->where($field, '<=', $value); break;
            case 'like': $this->builder->where($field, 'like', "%{$value}%"); break;
            case 'between':
                [$a, $b] = is_array($value) ? $value : explode(',', (string)$value, 2);
                $this->builder->whereBetween($field, [$a, $b]); break;
            case 'in':
                $vals = is_array($value) ? $value : explode(',', (string)$value);
                $this->builder->whereIn($field, $vals); break;
            case 'not_in':
                $vals = is_array($value) ? $value : explode(',', (string)$value);
                $this->builder->whereNotIn($field, $vals); break;
            case 'is':
                if ($value === 'null') $this->builder->whereNull($field);
                if ($value === 'not_null') $this->builder->whereNotNull($field);
                break;
        }
    }
}
