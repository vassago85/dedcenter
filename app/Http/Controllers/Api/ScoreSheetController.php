<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ElrShot;
use App\Models\ScoreSheet;
use App\Models\ShootingMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token lookup for printed ALRHA mark-sense score cards.
 *
 * The phone reads the QR, which carries `DCS1:{token}`. It then calls
 * here with the match it's currently scoring. We only return the card
 * if it belongs to this match — a card that was printed for a
 * different match MUST be rejected so an accidental photo on the
 * wrong event can't rewrite shots on the right one.
 *
 * The layout returned here is the frozen one from print time. Shots
 * already on file for each (shooter, target, shot_number) are attached
 * so the decoder can show the match director any conflicts before they
 * confirm.
 */
class ScoreSheetController extends Controller
{
    /**
     * List every score sheet printed for a match.
     *
     * Used by the Android native app during match-import so Hub mode
     * can resolve tokens offline — a hub that never heard of a card
     * can't let the MD scan it. The payload mirrors {@see show()} but
     * trims the assignable roster (hub derives that from its own
     * Shooter table) and skips `existing_shots` (hub reads those from
     * its own ElrShot cache).
     *
     * The sheet token is case-sensitive opaque; nothing here is PII.
     */
    public function index(Request $request, ShootingMatch $match): JsonResponse
    {
        $user = $request->user();

        if (! $user?->can('score', $match)) {
            return response()->json(['message' => 'You are not authorized to score this match.'], 403);
        }

        $sheets = ScoreSheet::query()
            ->where('match_id', $match->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ScoreSheet $sheet) => [
                'id' => $sheet->id,
                'token' => $sheet->token,
                'format_version' => $sheet->format_version,
                'match_id' => $sheet->match_id,
                'shooter_id' => $sheet->shooter_id,
                'alrha_class' => $sheet->alrha_class?->value,
                'is_spare' => $sheet->isSpare(),
                'layout' => $sheet->layout,
                'printed_at' => $sheet->printed_at?->toIso8601String(),
                'claimed_at' => $sheet->claimed_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'sheets' => $sheets,
            'count' => $sheets->count(),
        ]);
    }

    public function show(Request $request, ShootingMatch $match, string $token): JsonResponse
    {
        $user = $request->user();

        if (! $user?->can('score', $match)) {
            return response()->json(['message' => 'You are not authorized to score this match.'], 403);
        }

        $sheet = ScoreSheet::where('token', strtoupper($token))->first();

        if (! $sheet) {
            return response()->json(['message' => 'Unknown score card token.'], 404);
        }

        if ($sheet->match_id !== $match->id) {
            // Belongs to a different match — refuse. Anything else and
            // someone would be able to rewrite scores on the wrong
            // event by accidentally scanning an old card.
            return response()->json([
                'message' => 'This card was printed for another match.',
            ], 422);
        }

        $sheet->load('shooter.squad', 'shooter.team', 'shooter.division');

        $isSpare = $sheet->shooter_id === null;

        // Spares already carry their own class on the sheet record (the
        // shooter who claims them hasn't been chosen yet). Assigned cards
        // fall back to the match-level class for legacy single-class matches.
        $sheetClass = $isSpare
            ? $sheet->alrha_class
            : ($sheet->shooter->alrha_class ?? $match->alrhaClass());

        $targetIds = collect($sheet->layout['rows'] ?? [])->pluck('target_id')->all();

        $existing = $isSpare
            ? collect()
            : ElrShot::query()
                ->where('shooter_id', $sheet->shooter_id)
                ->whereIn('elr_target_id', $targetIds)
                ->get(['elr_target_id', 'shot_number', 'result', 'points_awarded'])
                ->map(fn (ElrShot $s) => [
                    'elr_target_id' => $s->elr_target_id,
                    'shot_number' => $s->shot_number,
                    'result' => $s->result->value,
                    'points_awarded' => (float) $s->points_awarded,
                ])
                ->values();

        // Assignable roster for the scanner's "pick a shooter" step on
        // spare cards. We filter server-side to the same class as the
        // spare so the MD can't bind a Varmint card to a Hunters shooter.
        $assignable = $isSpare
            ? \App\Models\Shooter::query()
                ->whereIn('squad_id', $match->squads()->select('id'))
                ->where('status', 'active')
                ->when($sheetClass, function ($q) use ($sheetClass, $match) {
                    // Dual-class: only shooters in the sheet's class
                    // (or null-class on a legacy single-class match).
                    if (count($match->alrhaClasses()) > 1) {
                        $q->where(function ($w) use ($sheetClass) {
                            $w->where('alrha_class', $sheetClass->value)
                              ->orWhereNull('alrha_class');
                        });
                    }
                })
                ->with('squad:id,name')
                ->orderBy('squad_id')
                ->orderBy('name')
                ->get(['id', 'name', 'bib_number', 'squad_id', 'alrha_class'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'bib_number' => $s->bib_number,
                    'squad' => $s->squad?->name,
                    'alrha_class' => $s->alrha_class?->value,
                ])
                ->values()
            : collect();

        return response()->json([
            'sheet' => [
                'token' => $sheet->token,
                'format_version' => $sheet->format_version,
                'match_id' => $sheet->match_id,
                'is_spare' => $isSpare,
                'claimed_at' => $sheet->claimed_at?->toIso8601String(),
                'alrha_class' => $sheetClass?->value,
                'alrha_class_label' => $sheetClass?->label(),
                'shooter' => $isSpare ? null : [
                    'id' => $sheet->shooter->id,
                    'name' => $sheet->shooter->name,
                    'bib_number' => $sheet->shooter->bib_number,
                    'squad' => $sheet->shooter->squad?->name,
                    'team' => $sheet->shooter->team?->name,
                    'division' => $sheet->shooter->division?->name,
                    'alrha_class' => $sheetClass?->value,
                    'alrha_class_label' => $sheetClass?->label(),
                    'is_coached' => (bool) $sheet->shooter->is_coached,
                ],
                'layout' => $sheet->layout,
                'existing_shots' => $existing,
                'assignable_shooters' => $assignable,
                'printed_at' => $sheet->printed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Bind an anonymous spare card to a shooter.
     *
     * Atomic: a conditional `UPDATE ... WHERE shooter_id IS NULL`
     * catches the race where two MDs photograph the same spare at the
     * same time. The second one gets a 409 and has to reload — on reload
     * the sheet now shows the bound shooter, so the second import
     * either goes to the correct person (no harm) or the MD rescans a
     * fresh spare.
     */
    public function claim(Request $request, ShootingMatch $match, string $token): JsonResponse
    {
        $user = $request->user();

        if (! $user?->can('score', $match)) {
            return response()->json(['message' => 'You are not authorized to score this match.'], 403);
        }

        $validated = $request->validate([
            'shooter_id' => ['required', 'integer'],
            // Android hub mode captures the claim offline and syncs it
            // back up later — honour its timestamp if it's recent and
            // not in the future. Anything older than 7 days is almost
            // certainly a clock-skew bug and we'd rather stamp "now"
            // than lie about when the MD actually bound the card.
            'claimed_at' => ['nullable', 'date'],
        ]);

        $sheet = ScoreSheet::where('token', strtoupper($token))->first();
        if (! $sheet) {
            return response()->json(['message' => 'Unknown score card token.'], 404);
        }
        if ($sheet->match_id !== $match->id) {
            return response()->json(['message' => 'This card was printed for another match.'], 422);
        }
        if ($sheet->shooter_id !== null) {
            // Already claimed. Tell the scanner so it can refresh and
            // either proceed with the bound shooter or let the MD bail.
            return response()->json([
                'message' => 'This card has already been assigned to a shooter.',
                'bound_shooter_id' => $sheet->shooter_id,
            ], 409);
        }

        $shooter = \App\Models\Shooter::query()
            ->whereIn('squad_id', $match->squads()->select('id'))
            ->where('status', 'active')
            ->where('id', $validated['shooter_id'])
            ->first();

        if (! $shooter) {
            return response()->json(['message' => 'Shooter not found on this match.'], 422);
        }

        // Dual-class matches: a Varmint spare must bind to a Varmint
        // shooter and vice versa, otherwise the layout's row distances
        // don't match the shooter's targets.
        if (count($match->alrhaClasses()) > 1
            && $sheet->alrha_class !== null
            && $shooter->alrha_class !== null
            && $sheet->alrha_class !== $shooter->alrha_class) {
            return response()->json([
                'message' => sprintf(
                    'This is a %s spare card and cannot be bound to a %s shooter.',
                    $sheet->alrha_class->label(),
                    $shooter->alrha_class->label(),
                ),
            ], 422);
        }

        // Honour a hub-supplied `claimed_at` only when it's recent and
        // not in the future. Otherwise stamp "now" so the scoring
        // timeline stays trustworthy.
        $claimedAt = now();
        if (! empty($validated['claimed_at'])) {
            $remote = \Illuminate\Support\Carbon::parse($validated['claimed_at']);
            if ($remote->lte(now()) && $remote->gte(now()->subDays(7))) {
                $claimedAt = $remote;
            }
        }

        // Atomic claim: win if and only if the row is still unassigned.
        $affected = ScoreSheet::where('id', $sheet->id)
            ->whereNull('shooter_id')
            ->update([
                'shooter_id' => $shooter->id,
                'claimed_at' => $claimedAt,
            ]);

        if ($affected === 0) {
            // Lost the race.
            $latest = $sheet->fresh();

            return response()->json([
                'message' => 'Another MD assigned this card first. Reload and try again.',
                'bound_shooter_id' => $latest?->shooter_id,
            ], 409);
        }

        return response()->json([
            'message' => 'Card claimed.',
            'shooter_id' => $shooter->id,
            'claimed_at' => $claimedAt->toIso8601String(),
        ]);
    }
}
