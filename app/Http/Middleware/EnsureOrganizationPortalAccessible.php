<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationPortalAccessible
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        if (! $organization instanceof Organization) {
            $organization = Organization::where('slug', $organization)->firstOrFail();
        }

        if (! $organization->canAccessPortal()) {
            abort(404);
        }

        return $next($request);
    }
}
