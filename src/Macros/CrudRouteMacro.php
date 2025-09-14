<?php

namespace Qnox\Crud\Macros;

use Illuminate\Support\Facades\Route;

class CrudRouteMacro
{
    public static function register()
    {
        return function (string $uri, string $controller, array $options = []) {
            $name = $options['name'] ?? $uri;
            $middleware = $options['middleware'] ?? config('crud.auth_middleware', ['auth:sanctum']);
            $bulk = $options['bulk'] ?? true;
            $export = $options['export'] ?? true;

            Route::middleware($middleware)->group(function () use ($uri, $controller, $name, $bulk, $export) {
                Route::get($uri, [$controller, 'index'])->name("$name.index")->can('viewAny', $controller::model());
                Route::post($uri, [$controller, 'store'])->name("$name.store")->can('create', $controller::model());
                Route::get("$uri/{id}", [$controller, 'show'])->name("$name.show")->can('view', $controller::model());
                Route::put("$uri/{id}", [$controller, 'update'])->name("$name.update")->can('update', $controller::model());
                Route::delete("$uri/{id}", [$controller, 'destroy'])->name("$name.destroy")->can('delete', $controller::model());

                if ($bulk) {
                    Route::post("$uri/bulk", [$controller, 'bulk'])->name("$name.bulk")->can('update', $controller::model());
                }
                if ($export) {
                    Route::get("$uri/export", [$controller, 'export'])->name("$name.export")->can('viewAny', $controller::model());
                }
            });
        };
    }
}
