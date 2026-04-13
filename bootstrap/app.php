<?php

use App\Http\Middleware\DomainContext;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureMatchbookEnabled;
use App\Http\Middleware\EnsureOrgAdmin;
use App\Http\Middleware\EnsureOrganizationPortalAccessible;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();

        $middleware->append(DomainContext::class);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'org.admin' => EnsureOrgAdmin::class,
            'org.portal' => EnsureOrganizationPortalAccessible::class,
            'matchbook.enabled' => EnsureMatchbookEnabled::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
