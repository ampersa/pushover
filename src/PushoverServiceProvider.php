<?php

namespace Ampersa\Pushover;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class PushoverServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['pushover'] = $this->app->share(function ($app) {
            return new Pushover;
        });

        $this->app->booting(function () {
            $loader = AliasLoader::getInstance();
            $loader->alias('Pushover', 'Ampersa\Pushover\Facades\Pushover');
        });
    }

    /**
     * Get the services provided by the provider.    
     *
     * @return array
     */
    public function provides()
    {
        return ['pushover'];
    }
}
