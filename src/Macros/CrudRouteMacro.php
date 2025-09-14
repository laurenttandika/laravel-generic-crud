<?php

namespace Qnox\Crud\Macros;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class CrudRouteMacro
{
    public static function register()
    {
        return function (string $uri, string $controller, array $options = []) {
            $name = $options['name'] ?? $uri;
            $middleware = $options['middleware'] ?? config('crud.auth_middleware', ['auth:sanctum']);
            $bulk = $options['bulk'] ?? true;
            $export = $options['export'] ?? true;

            // Support dotted URIs for nested resources, e.g. "posts.comments"
            $segments = explode('.', $uri);
            $path = '';
            $routeName = '';

            // Build nested path like "/posts/{post}/comments"
            foreach ($segments as $i => $seg) {
                $routeName .= ($i ? '.' : '') . $seg;
                if ($i < count($segments) - 1) {
                    // Parent segment
                    $param = Str::singular($seg);
                    $path .= "/{$seg}/{{$param}}";
                } else {
                    // Leaf segment
                    $path .= "/{$seg}";
                }
            }

            Route::middleware($middleware)->group(function () use ($path, $controller, $routeName, $bulk, $export) {
                // Index & Store on collection
                Route::get($path, [$controller, 'index'])->name("{$routeName}.index")->can('viewAny', $controller::model());
                Route::post($path, [$controller, 'store'])->name("{$routeName}.store")->can('create', $controller::model());

                // Show/Update/Destroy on member (append '/{id}')
                Route::get($path.'/{id}', [$controller, 'show'])->name("{$routeName}.show")->can('view', $controller::model());
                Route::put($path.'/{id}', [$controller, 'update'])->name("{$routeName}.update")->can('update', $controller::model());
                Route::delete($path.'/{id}', [$controller, 'destroy'])->name("{$routeName}.destroy")->can('delete', $controller::model());

                if ($bulk) {
                    Route::post($path.'/bulk', [$controller, 'bulk'])->name("{$routeName}.bulk")->can('update', $controller::model());
                }
                if ($export) {
                    Route::get($path.'/export', [$controller, 'export'])->name("{$routeName}.export")->can('viewAny', $controller::model());
                }
            });
        };
    }
}
