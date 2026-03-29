<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => $this->date?->toDateString(),
            'location' => $this->location,
            'status' => $this->status->value,
            'scoring_type' => $this->scoring_type ?? 'standard',
            'notes' => $this->notes,
            'target_sets' => $this->whenLoaded('targetSets', fn () => $this->targetSets->map(fn ($ts) => [
                'id' => $ts->id,
                'label' => $ts->label,
                'distance_meters' => $ts->distance_meters,
                'sort_order' => $ts->sort_order,
                'is_tiebreaker' => (bool) $ts->is_tiebreaker,
                'par_time_seconds' => $ts->par_time_seconds ? (float) $ts->par_time_seconds : null,
                'gongs' => $ts->gongs->map(fn ($g) => [
                    'id' => $g->id,
                    'number' => $g->number,
                    'label' => $g->label,
                    'multiplier' => $g->multiplier,
                ]),
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
                ]),
            ])),
            'scores' => $this->whenLoaded('scores', fn () => ScoreResource::collection($this->scores)),
            'stage_times' => $this->whenLoaded('stageTimes', fn () => $this->stageTimes->map(fn ($st) => [
                'shooter_id' => $st->shooter_id,
                'target_set_id' => $st->target_set_id,
                'time_seconds' => (float) $st->time_seconds,
                'recorded_at' => $st->recorded_at?->toIso8601String(),
            ])),
        ];
    }
}
