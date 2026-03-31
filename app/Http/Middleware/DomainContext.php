<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class DomainContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        $context = match (true) {
            $host === config('domains.app')     => 'app',
            $host === config('domains.md')      => 'md',
            $host === config('domains.shooter') => 'shooter',
            default                             => 'shooter',
        };

        $request->attributes->set('domain_context', $context);
        View::share('domainContext', $context);

        return $next($request);
    }
}
