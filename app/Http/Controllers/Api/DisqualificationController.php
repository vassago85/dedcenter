<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Disqualification;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Services\ScoreAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisqualificationController extends Controller
{
    public function index(ShootingMatch $match): JsonResponse
    {
        $dqs = $match->disqualifications()
            ->with(['shooter:id,name,bib_number', 'targetSet:id,label,distance_meters,stage_number', 'issuedBy:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($dq) => [
                'id' => $dq->id,
                'shooter_id' => $dq->shooter_id,
                'shooter_name' => $dq->shooter?->name,
                'target_set_id' => $dq->target_set_id,
                'stage_label' => $dq->targetSet
                    ? ($dq->targetSet->label ?: "Stage {$dq->targetSet->stage_number}")
                    : null,
                'type' => $dq->isMatchDq() ? 'match' : 'stage',
                'reason' => $dq->reason,
                'issued_by_name' => $dq->issuedBy?->name,
                'created_at' => $dq->created_at->toIso8601String(),
            ]);

        return response()->json(['disqualifications' => $dqs]);
    }

    public function store(Request $request, ShootingMatch $match): JsonResponse
    {
        $this->authorizeMatchDirector($request, $match);

        $validShooterIds = $match->shooters()->pluck('shooters.id')->toArray();
        $validTargetSetIds = $match->targetSets()->pluck('id')->toArray();

        $validated = $request->validate([
            'shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'target_set_id' => ['nullable', 'integer', Rule::in($validTargetSetIds)],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $shooter = Shooter::findOrFail($validated['shooter_id']);
        $isMatchDq = empty($validated['target_set_id']);

        $existing = Disqualification::where('match_id', $match->id)
            ->where('shooter_id', $shooter->id)
            ->where('target_set_id', $validated['target_set_id'] ?? null)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => $isMatchDq
                    ? 'This shooter is already disqualified from the match.'
                    : 'This shooter is already disqualified from this stage.',
            ], 422);
        }

        $dq = Disqualification::create([
            'match_id' => $match->id,
            'shooter_id' => $shooter->id,
            'target_set_id' => $validated['target_set_id'] ?? null,
            'reason' => $validated['reason'],
            'issued_by' => $request->user()->id,
        ]);

        if ($isMatchDq) {
            $shooter->update(['status' => 'dq']);
        }

        ScoreAuditService::log(
            $match->id,
            $shooter,
            $isMatchDq ? 'match_dq' : 'stage_dq',
            null,
            [
                'target_set_id' => $dq->target_set_id,
                'reason' => $dq->reason,
            ],
            $dq->reason,
            $request,
        );

        return response()->json([
            'message' => $isMatchDq
                ? "{$shooter->name} has been disqualified from the match."
                : "{$shooter->name} has been disqualified from the stage.",
            'disqualification' => [
                'id' => $dq->id,
                'type' => $isMatchDq ? 'match' : 'stage',
                'shooter_id' => $dq->shooter_id,
                'target_set_id' => $dq->target_set_id,
                'reason' => $dq->reason,
            ],
        ], 201);
    }

    public function destroy(Request $request, ShootingMatch $match, Disqualification $disqualification): JsonResponse
    {
        $this->authorizeMatchDirector($request, $match);

        if ($disqualification->match_id !== $match->id) {
            abort(404);
        }

        $shooter = $disqualification->shooter;
        $wasMatchDq = $disqualification->isMatchDq();

        ScoreAuditService::log(
            $match->id,
            $shooter,
            'dq_revoked',
            [
                'target_set_id' => $disqualification->target_set_id,
                'reason' => $disqualification->reason,
            ],
            null,
            "DQ revoked by {$request->user()->name}",
            $request,
        );

        $disqualification->delete();

        if ($wasMatchDq) {
            $hasOtherMatchDq = Disqualification::where('match_id', $match->id)
                ->where('shooter_id', $shooter->id)
                ->whereNull('target_set_id')
                ->exists();

            if (! $hasOtherMatchDq) {
                $shooter->update(['status' => 'active']);
            }
        }

        return response()->json([
            'message' => "{$shooter->name}'s disqualification has been revoked.",
        ]);
    }

    private function authorizeMatchDirector(Request $request, ShootingMatch $match): void
    {
        $user = $request->user();

        $canManage = $user->isOwner()
            || $match->created_by === $user->id
            || ($match->organization && $user->isOrgRangeOfficer($match->organization));

        if (! $canManage) {
            abort(403, 'Only match directors can issue disqualifications.');
        }
    }
}
