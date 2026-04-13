<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMatchbookEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('deadcenter.matchbook_enabled')) {
            abort(503, 'Match books are temporarily unavailable.');
        }

        return $next($request);
    }
}
