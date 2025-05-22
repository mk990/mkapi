<?php

namespace Mk990\MkApi\ServiceProvider;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Mk990\MkApi\Console\Commands\ApiInstallCommand;
use Mk990\MkApi\Console\Commands\ControllerSWG;
use Mk990\MkApi\Console\Commands\ModelSWG;
use Mk990\MkApi\MkApi;

class MkApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('MkApi', function () {
            return new MkApi();
        });
    }

    /**
    * Bootstrap any package services.
    */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModelSWG::class,
                ControllerSWG::class,
                ApiInstallCommand::class,
            ]);
        }
        AboutCommand::add('MkApi', fn () => ['Version' => '0.0.1']);
        // $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        // $this->loadViewsFrom(__DIR__ . '/../resources/views', 'charge');
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
