<?php

namespace Qnox\Crud;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Qnox\Crud\Macros\CrudRouteMacro;

class CrudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/crud.php', 'crud');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/crud.php' => config_path('crud.php'),
        ], 'crud-config');

        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/crud'),
        ], 'crud-stubs');

        Route::macro('crud', CrudRouteMacro::register());

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Qnox\Crud\Console\MakeCrudCommand::class,
            ]);
        }
    }
}
