<?php

namespace App\Providers;

use App\Models\ShootingMatch;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Volt::mount([
            resource_path('views/pages'),
        ]);

        Route::model('match', ShootingMatch::class);
    }
}
