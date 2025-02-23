<?php

namespace CodproX\OneSignal;

use Illuminate\Support\ServiceProvider;

class OneSignalServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/onesignal.php' => config_path('onesignal.php'),
        ], 'onesignal-config');

        $this->mergeConfigFrom(__DIR__ . '/../config/onesignal.php', 'onesignal');
    }

    public function register()
    {
        $this->app->singleton(MyOneSignal::class, function ($app) {
            return new MyOneSignal();
        });
    }
}