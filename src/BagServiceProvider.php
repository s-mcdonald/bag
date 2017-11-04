<?php

namespace SamMcDonald\Bag;

use Illuminate\Auth\Events\Logout;
use Illuminate\Session\SessionManager;
use Illuminate\Support\ServiceProvider;

class BagServiceProvider extends ServiceProvider
{

    /**
     * Bind the Cart to the app.
     * 
     * @return
     */
    public function register()
    {
        $this->app->bind('bag', 'SamMcDonald\Bag\Bag');
    }


    public function boot()
    {
        // https://laravel.com/docs/5.5/packages#configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/bag.php', 'bag'
        );

        $this->publishes([__DIR__ . '/../config/bag.php' => config_path('bag.php')],'bag');

    }
}
