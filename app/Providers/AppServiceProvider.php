<?php

namespace App\Providers;

use App\Models\ShootingMatch;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Volt::mount([
            resource_path('views/pages'),
        ]);

        Route::model('match', ShootingMatch::class);
    }
}
