<?php

namespace Brendanpetty\Limporter;

use Illuminate\Support\ServiceProvider;

class LimporterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
       //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
		$this->loadViewsFrom(__DIR__.'/../resources/views', 'limporter');
		$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
