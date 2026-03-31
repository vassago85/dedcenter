<?php

namespace App\Http\Middleware;

use App\Models\ShootingMatch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceDeviceLock
{
    public function handle(Request $request, Closure $next): Response
    {
        $match = $request->route('match');

        if (! $match instanceof ShootingMatch) {
            return $next($request);
        }

        $lockMode = $match->device_lock_mode ?? 'open';

        if ($lockMode === 'open') {
            return $next($request);
        }

        if ($lockMode === 'locked_to_stage') {
            $lockedStageId = $request->header('X-Device-Lock-Stage');
            $routeStageId = $request->route('stage')?->id ?? $request->route('stage');

            if ($lockedStageId && $routeStageId && (int) $lockedStageId !== (int) $routeStageId) {
                return response()->json([
                    'message' => 'Device is locked to a different stage.',
                    'locked_stage_id' => (int) $lockedStageId,
                ], 403);
            }
        }

        if ($lockMode === 'locked_to_squad') {
            $lockedSquadId = $request->header('X-Device-Lock-Squad');
            $requestSquadId = $request->input('squad_id');

            if ($lockedSquadId && $requestSquadId && (int) $lockedSquadId !== (int) $requestSquadId) {
                return response()->json([
                    'message' => 'Device is locked to a different squad.',
                    'locked_squad_id' => (int) $lockedSquadId,
                ], 403);
            }
        }

        return $next($request);
    }
}
