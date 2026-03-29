<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrgAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        if (! $organization instanceof Organization) {
            $organization = Organization::where('slug', $organization)->firstOrFail();
        }

        if (! $request->user()?->isOrgAdmin($organization)) {
            abort(403, 'You are not authorized to manage this organization.');
        }

        return $next($request);
    }
}
