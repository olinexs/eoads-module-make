<?php

namespace Eoads\LaravelModuleMake;

use Illuminate\Support\ServiceProvider;
use Eoads\LaravelModuleMake\Commands\ModuleMakeCommand;

class ModuleMakeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleMakeCommand::class,
            ]);
        }
    }
}
