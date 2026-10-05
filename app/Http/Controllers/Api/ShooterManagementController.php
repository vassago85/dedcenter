<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Tablet squadding: add a walk-in, move a shooter between squads, or nudge
 * firing order. The web squadding page still owns capacity setup, teams, and
 * bulk edits. Same people as that page (RO, MD, creator, owner). Completed
 * matches stay editable because this changes registrations, not scores.
 */
class ShooterManagementController extends Controller
{
    /**
     * Walk-in into one squad. Division is required when the match has any.
     * Caliber is appended as "Name — Caliber", the same convention reports use.
     * No MatchRegistration row — this is a free-standing shooter, same as the
     * simplest web walk-in.
     */
    public function store(Request $request, ShootingMatch $match): JsonResponse
    {
        $this->authorizeSquadding($request, $match);

        $squadIds = $match->squads()->pluck('id')->all();
        $divisionIds = $match->divisions()->pluck('id')->all();
        $hasDivisions = count($divisionIds) > 0;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bib_number' => ['nullable', 'string', 'max:50'],
            'caliber' => ['nullable', 'string', 'max:64'],
            'squad_id' => ['required', 'integer', Rule::in($squadIds)],
            'division_id' => [$hasDivisions ? 'required' : 'nullable', 'integer', Rule::in($divisionIds)],
        ]);

        $squad = Squad::whereKey($validated['squad_id'])
            ->where('match_id', $match->id)
            ->firstOrFail();

        // Honour the per-squad / match-wide capacity exactly like the web
        // flow — stops a tablet from silently overflowing a squad that was
        // explicitly limited for safety-briefing reasons.
        if ($squad->isFull()) {
            return response()->json([
                'message' => "{$squad->name} is full.",
            ], 422);
        }

        $caliber = trim((string) ($validated['caliber'] ?? ''));
        $baseName = trim($validated['name']);
        if ($caliber !== '' && str_contains($baseName, ' — ')) {
            // The MD may have already typed the convention; strip it so we
            // don't double-suffix after we re-append below.
            $baseName = trim(Str::before($baseName, ' — '));
        }
        $displayName = $caliber !== '' ? "{$baseName} — {$caliber}" : $baseName;

        $maxSort = (int) ($squad->shooters()->max('sort_order') ?? 0);

        $shooter = Shooter::create([
            'squad_id' => $squad->id,
            'name' => $displayName,
            'bib_number' => $validated['bib_number'] ?? null,
            'match_division_id' => $hasDivisions ? ($validated['division_id'] ?? null) : null,
            'sort_order' => $maxSort + 1,
            'status' => 'active',
        ]);

        return $this->shooterResponse($shooter, 201);
    }

    /**
     * Move to another squad in this match. Scores stay on the shooter row.
     * Lands at the end of the target squad.
     */
    public function move(Request $request, ShootingMatch $match, Shooter $shooter): JsonResponse
    {
        $this->authorizeSquadding($request, $match);
        $this->assertShooterInMatch($match, $shooter);

        $squadIds = $match->squads()->pluck('id')->all();

        $validated = $request->validate([
            'squad_id' => ['required', 'integer', Rule::in($squadIds)],
        ]);

        if ((int) $validated['squad_id'] === (int) $shooter->squad_id) {
            return $this->shooterResponse($shooter);
        }

        $target = Squad::whereKey($validated['squad_id'])
            ->where('match_id', $match->id)
            ->firstOrFail();

        if ($target->isFull()) {
            return response()->json([
                'message' => "{$target->name} is full.",
            ], 422);
        }

        $maxSort = (int) ($target->shooters()->max('sort_order') ?? 0);
        $shooter->update([
            'squad_id' => $target->id,
            'sort_order' => $maxSort + 1,
        ]);

        return $this->shooterResponse($shooter);
    }

    /**
     * Swap sort_order with the neighbour above or below. Pair-wise so the
     * rest of the bench order stays put.
     */
    public function reorder(Request $request, ShootingMatch $match, Shooter $shooter): JsonResponse
    {
        $this->authorizeSquadding($request, $match);
        $this->assertShooterInMatch($match, $shooter);

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $neighbourQuery = Shooter::where('squad_id', $shooter->squad_id);
        if ($validated['direction'] === 'up') {
            $neighbour = $neighbourQuery
                ->where('sort_order', '<', $shooter->sort_order)
                ->orderByDesc('sort_order')
                ->first();
        } else {
            $neighbour = $neighbourQuery
                ->where('sort_order', '>', $shooter->sort_order)
                ->orderBy('sort_order')
                ->first();
        }

        if (! $neighbour) {
            return $this->shooterResponse($shooter);
        }

        DB::transaction(function () use ($shooter, $neighbour) {
            $a = $shooter->sort_order;
            $b = $neighbour->sort_order;
            // Legacy rows can share a sort_order. Bump instead of swapping equals.
            if ($a === $b) {
                $shooter->update(['sort_order' => $b + 1]);
            } else {
                $shooter->update(['sort_order' => $b]);
                $neighbour->update(['sort_order' => $a]);
            }
        });

        $shooter->refresh();

        return $this->shooterResponse($shooter);
    }

    protected function authorizeSquadding(Request $request, ShootingMatch $match): void
    {
        $user = $request->user();
        abort_unless(
            $user?->can('squad', $match),
            403,
            'You are not authorized to manage shooters in this match.',
        );
    }

    protected function assertShooterInMatch(ShootingMatch $match, Shooter $shooter): void
    {
        $belongs = $match->shooters()->whereKey($shooter->id)->exists();
        abort_unless($belongs, 404, 'Shooter not found in this match.');
    }

    protected function shooterResponse(Shooter $shooter, int $status = 200): JsonResponse
    {
        $shooter->loadMissing('division');

        return response()->json([
            'shooter' => [
                'id' => $shooter->id,
                'squad_id' => $shooter->squad_id,
                'name' => $shooter->name,
                'bib_number' => $shooter->bib_number,
                'sort_order' => $shooter->sort_order,
                'division_id' => $shooter->match_division_id,
                'division' => $shooter->division?->name,
                'status' => $shooter->status,
            ],
        ], $status);
    }
}
