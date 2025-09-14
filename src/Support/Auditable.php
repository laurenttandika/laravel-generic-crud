<?php

namespace Qnox\Crud\Support;

use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable()
    {
        static::creating(function ($model) {
            if (schema_has_column($model->getTable(), 'created_by')) $model->created_by = Auth::id();
            if (schema_has_column($model->getTable(), 'updated_by')) $model->updated_by = Auth::id();
        });

        static::updating(function ($model) {
            if (schema_has_column($model->getTable(), 'updated_by')) $model->updated_by = Auth::id();
        });
    }
}

if (! function_exists('schema_has_column')) {
    function schema_has_column(string $table, string $column): bool {
        try { return \Illuminate\Support\Facades\Schema::hasColumn($table, $column); }
        catch (\Throwable $e) { return false; }
    }
}
