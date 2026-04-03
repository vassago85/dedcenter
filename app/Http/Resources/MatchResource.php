<?php

namespace App\Http\Resources;

use App\Enums\PlacementKey;
use App\Models\Setting;
use App\Services\SponsorPlacementResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    protected function resolveScoringsSponsor(): ?array
    {
        if (! (bool) Setting::get('advertising_enabled', false)) {
            return null;
        }

        try {
            $resolver = app(SponsorPlacementResolver::class);
            $assignment = $resolver->resolve(PlacementKey::MatchScoring, $this->id);
            if (! $assignment?->sponsor) {
                return null;
            }
            $sponsor = $assignment->sponsor;

            return [
                'name' => $sponsor->name,
                'logo_url' => $sponsor->hasLogo() ? asset('storage/'.$sponsor->logo_path) : null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'name' => $this->name,
            'date' => $this->date?->toDateString(),
            'location' => $this->location,
            'status' => $this->status->value,
            'scoring_type' => $this->scoring_type ?? 'standard',
            'scores_published' => (bool) ($this->scores_published ?? true),
            'notes' => $this->notes,
            'public_bio' => $this->public_bio,
            'target_sets' => $this->whenLoaded('targetSets', fn () => $this->targetSets->map(fn ($ts) => [
                'id' => $ts->id,
                'label' => $ts->label,
                'display_name' => $ts->display_name,
                'distance_meters' => $ts->distance_meters,
                'distance_multiplier' => (float) ($ts->distance_multiplier ?? 1),
                'sort_order' => $ts->sort_order,
                'is_tiebreaker' => (bool) $ts->is_tiebreaker,
                'par_time_seconds' => $ts->par_time_seconds ? (float) $ts->par_time_seconds : null,
                'stage_number' => $ts->stage_number,
                'total_shots' => $ts->total_shots,
                'is_timed_stage' => (bool) $ts->is_timed_stage,
                'notes' => $ts->notes,
                'gongs' => $ts->gongs->map(fn ($g) => array_filter([
                    'id' => $g->id,
                    'number' => $g->number,
                    'label' => $g->label,
                    'multiplier' => $g->multiplier,
                    'distance_meters' => $g->distance_meters,
                    'target_size' => $g->target_size,
                ], fn ($v) => $v !== null)),
                'stage_targets' => $ts->relationLoaded('stageTargets') ? $ts->stageTargets->map(fn ($st) => [
                    'id' => $st->id,
                    'sequence_number' => $st->sequence_number,
                    'target_name' => $st->target_name,
                    'distance_meters' => $st->distance_meters,
                    'target_size_mm' => $st->target_size_mm ? (float) $st->target_size_mm : null,
                    'target_size_mrad' => $st->target_size_mrad ? (float) $st->target_size_mrad : null,
                    'notes' => $st->notes,
                ]) : [],
            ])),
            'divisions' => $this->whenLoaded('divisions', fn () => $this->divisions->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
            ])),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ])),
            'squads' => $this->whenLoaded('squads', fn () => $this->squads->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'sort_order' => $s->sort_order,
                'shooters' => $s->shooters->map(fn ($sh) => [
                    'id' => $sh->id,
                    'name' => $sh->name,
                    'bib_number' => $sh->bib_number,
                    'sort_order' => $sh->sort_order,
                    'division_id' => $sh->match_division_id,
                    'division' => $sh->division?->name,
                    'category_ids' => $sh->relationLoaded('categories') ? $sh->categories->pluck('id')->values() : [],
                    'status' => $sh->status ?? 'active',
                ]),
            ])),
            'scores' => $this->whenLoaded('scores', fn () => ScoreResource::collection($this->scores)),
            'stage_times' => $this->whenLoaded('stageTimes', fn () => $this->stageTimes->map(fn ($st) => [
                'shooter_id' => $st->shooter_id,
                'target_set_id' => $st->target_set_id,
                'time_seconds' => (float) $st->time_seconds,
                'recorded_at' => $st->recorded_at?->toIso8601String(),
            ])),
            'side_bet_enabled' => (bool) $this->side_bet_enabled,
            'side_bet_shooter_ids' => $this->whenLoaded('sideBetShooters', fn () => $this->sideBetShooters->pluck('id')->values()),
            'royal_flush_enabled' => (bool) $this->royal_flush_enabled,
            'concurrent_relays' => (int) ($this->concurrent_relays ?? 2),
            'device_lock_mode' => $this->device_lock_mode ?? 'open',
            'corrections_pin' => $this->when(
                $request->user() && ($request->user()->isOwner() || $this->created_by === $request->user()->id || ($this->organization && $request->user()->isOrgRangeOfficer($this->organization))),
                $this->corrections_pin
            ),
            'prs_stage_results' => $this->whenLoaded('prsResults', fn () => $this->prsResults->map(fn ($r) => [
                'shooter_id' => $r->shooter_id,
                'stage_id' => $r->stage_id,
                'hits' => $r->hits,
                'misses' => $r->misses,
                'not_taken' => $r->not_taken,
                'raw_time_seconds' => $r->raw_time_seconds ? (float) $r->raw_time_seconds : null,
                'official_time_seconds' => $r->official_time_seconds ? (float) $r->official_time_seconds : null,
                'completed_at' => $r->completed_at?->toIso8601String(),
            ])),
            'disqualifications' => $this->whenLoaded('disqualifications', fn () => $this->disqualifications->map(fn ($dq) => [
                'id' => $dq->id,
                'shooter_id' => $dq->shooter_id,
                'target_set_id' => $dq->target_set_id,
                'type' => $dq->target_set_id ? 'stage' : 'match',
                'reason' => $dq->reason,
                'issued_by' => $dq->issuedBy?->name,
                'created_at' => $dq->created_at?->toIso8601String(),
            ])),
            'scoring_sponsor' => $this->resolveScoringsSponsor(),
            'elr_stages' => $this->whenLoaded('elrStages', fn () => $this->elrStages->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->label,
                'stage_type' => $s->stage_type->value,
                'sort_order' => $s->sort_order,
                'profile' => $s->resolvedProfile() ? [
                    'name' => $s->resolvedProfile()->name,
                    'multipliers' => $s->resolvedProfile()->multipliers,
                ] : null,
                'targets' => $s->targets->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'distance_m' => $t->distance_m,
                    'base_points' => (float) $t->base_points,
                    'max_shots' => $t->max_shots,
                    'must_hit_to_advance' => $t->must_hit_to_advance,
                    'sort_order' => $t->sort_order,
                ]),
            ])),
        ];
    }
}
