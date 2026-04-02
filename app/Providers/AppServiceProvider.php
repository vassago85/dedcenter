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
        $this->app->singleton(\App\Services\NotificationService::class);
    }

    public function boot(): void
    {
        // Match URL scheme to APP_URL so local / Docker over http:// still loads assets (avoid forcing https when APP_URL is http).
        $scheme = parse_url((string) config('app.url', ''), PHP_URL_SCHEME);
        if ($scheme === 'https') {
            URL::forceScheme('https');
        }

        Volt::mount([
            resource_path('views/pages'),
        ]);

        Route::model('match', ShootingMatch::class);
    }
}
