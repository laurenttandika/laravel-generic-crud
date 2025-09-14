<?php

namespace Qnox\Crud\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasTenantScope
{
    protected static function bootHasTenantScope()
    {
        static::creating(function ($model) {
            if (auth()->check() && property_exists($model, 'tenant_column')) {
                $model->{$model->tenant_column} = auth()->user()->tenant_id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && property_exists($builder->getModel(), 'tenant_column')) {
                $builder->where(
                    $builder->getModel()->getTable().'.'.$builder->getModel()->tenant_column,
                    auth()->user()->tenant_id
                );
            }
        });
    }
}
