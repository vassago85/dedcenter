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
            'alrha_class' => $this->alrha_class?->value,
            'alrha_class_label' => $this->alrha_class?->label(),
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
                    'target_size_mm' => $g->target_size_mm ? (float) $g->target_size_mm : null,
                    'target_size_mrad' => ($g->target_size_mm && $g->distance_meters)
                        ? round((float) $g->target_size_mm / (float) $g->distance_meters, 2)
                        : null,
                ], fn ($v) => $v !== null)),
                'positions' => $ts->relationLoaded('positions') ? $ts->positions->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sort_order' => $p->sort_order,
                ]) : [],
                'shot_sequence' => $ts->relationLoaded('shotSequence') ? $ts->shotSequence->map(fn ($s) => [
                    'id' => $s->id,
                    'shot_number' => $s->shot_number,
                    'position_id' => $s->position_id,
                    'position_name' => $s->position?->name,
                    'gong_id' => $s->gong_id,
                    'gong_label' => $s->gong?->label ?? 'T'.$s->gong?->number,
                    'distance_meters' => $s->gong?->distance_meters,
                ]) : [],
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
                    'team' => $sh->relationLoaded('team') ? $sh->team?->name : null,
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
            'can_manage' => $request->user() && (
                $request->user()->isOwner()
                || $this->created_by === $request->user()->id
                || ($this->organization && $request->user()->isOrgAdmin($this->organization))
            ),
            'can_export' => $request->user() && (
                $request->user()->isAdmin()
                || ($this->organization && $request->user()->isOrgMatchDirector($this->organization))
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
            'elr_engagement_mode' => ($this->elr_engagement_mode?->value) ?? 'target_by_target',
            'elr_targets_per_shooter' => $this->elr_targets_per_shooter !== null ? (int) $this->elr_targets_per_shooter : null,
            'elr_shots_per_target' => (int) ($this->elr_shots_per_target ?? 3),
            // Per-team countdown (seconds) for team gong-sequence mode; null = no limit.
            'elr_team_time_limit_seconds' => $this->elr_team_time_limit_seconds !== null ? (int) $this->elr_team_time_limit_seconds : null,
            // Teams + members (with calibre division) so the team-sequence flow
            // can build the gong sequence offline. Only loaded for team mode.
            'teams' => $this->whenLoaded('teams', fn () => $this->teams->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'sort_order' => $t->sort_order,
                'division_category' => $t->divisionCategoryLabel(),
                'shooters' => $t->shooters->map(fn ($sh) => [
                    'id' => $sh->id,
                    'name' => $sh->name,
                    'bib_number' => $sh->bib_number,
                    'sort_order' => $sh->sort_order,
                    'division_id' => $sh->match_division_id,
                    'division' => $sh->division?->name,
                    'status' => $sh->status ?? 'active',
                ])->values(),
            ])),
            'elr_team_stage_entries' => $this->whenLoaded('elrTeamStageEntries', fn () => $this->elrTeamStageEntries->map(fn ($e) => [
                'team_id' => $e->team_id,
                'elr_stage_id' => $e->elr_stage_id,
                'squad_id' => $e->squad_id,
                'first_shooter_id' => $e->first_shooter_id,
                'position' => $e->position,
                'started_at' => $e->started_at?->toIso8601String(),
                'completed_at' => $e->completed_at?->toIso8601String(),
                'timed_out' => (bool) $e->timed_out,
            ])),
            // Mirrors matches.elr_distance_based_scoring so the native app's
            // importer can carry the flag offline and score distance × multiplier
            // for non-seeded ELR matches (seeded matches set base_points =
            // distance, so the result matches either way).
            'elr_distance_based_scoring' => (bool) ($this->elr_distance_based_scoring ?? false),
            // Captured-only flag for a future alternate team scoring mode. The
            // scoring flow reads it but no alternate logic is implemented yet.
            'alternate_scoring' => (bool) ($this->alternate_scoring ?? false),
            'alternate_scoring_pairs' => $this->when(
                ($this->alternate_scoring ?? false)
                    && ($this->elr_engagement_mode?->value ?? '') === 'team_sequence'
                    && $this->relationLoaded('teams'),
                fn () => $this->teams
                    ->sortBy('sort_order')
                    ->values()
                    ->chunk(2)
                    ->map(fn ($pair) => [
                        'team_ids' => $pair->pluck('id')->values()->all(),
                        'team_names' => $pair->pluck('name')->values()->all(),
                    ])
                    ->values()
                    ->all(),
            ),
            'elr_stages' => $this->whenLoaded('elrStages', fn () => $this->elrStages->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->label,
                'sponsor' => $s->sponsor,
                'color' => $s->color,
                'stage_type' => $s->stage_type->value,
                'sort_order' => $s->sort_order,
                'match_day' => $s->match_day,
                'elr_scoring_profile_id' => $s->elr_scoring_profile_id,
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
                    'is_cold_bore' => (bool) $t->is_cold_bore,
                    'alrha_block' => $t->alrha_block,
                    'sort_order' => $t->sort_order,
                    // Divisions that engage this target (Minor T1-T3, Major T2-T4).
                    // Empty = every division shoots it. Drives the scoring flow's
                    // per-shooter target rotation; the cloud scoreboard uses the
                    // same pivot for gated standings.
                    'division_ids' => $t->relationLoaded('divisions')
                        ? $t->divisions->pluck('id')->values()
                        : [],
                ]),
            ])),
        ];
    }
}
