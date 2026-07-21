<?php

namespace App\Services\Scoring;

use App\Enums\AlrhaClass;
use App\Models\ElrScoringProfile;
use App\Models\ElrStage;
use App\Models\MatchCategory;
use App\Models\ShootingMatch;
use Illuminate\Support\Facades\DB;

/**
 * Central place to (re)build the ALRHA stage tree for a match.
 *
 * ALRHA matches run both classes concurrently on the same day, so the
 * default template lays down BOTH Hunters and Varmint trees tagged by
 * class. Callers can also request a single-class rebuild for the rare
 * one-class-only match (back-compat with matches seeded before dual-
 * class support).
 *
 * Idempotent: re-running with the same input rebuilds the ALRHA-tagged
 * stages only; ELR stages that aren't ALRHA-tagged are left alone so
 * mixing an ALRHA match with a hand-authored side stage is safe.
 *
 * Shared by:
 *  - resources/views/pages/org/matches/edit.blade.php (Apply Template)
 *  - resources/views/pages/admin/matches/edit.blade.php (Apply Template)
 *  - App\Console\Commands\SeedAlrhaTestMatch
 */
class AlrhaMatchBuilder
{
    public const PROFILE_NAME = 'ALRHA 5-4-3-2-1';
    public const PROFILE_MULTIPLIERS = [5, 4, 3, 2, 1];

    /**
     * Build the ALRHA layout on $match. When $classes is null the default
     * dual-class template (both Hunters and Varmint) is applied. Passing
     * a single-element array rebuilds only that class's stages.
     *
     * @param  array<int, AlrhaClass>|null  $classes
     */
    public function apply(ShootingMatch $match, ?array $classes = null): void
    {
        $classes = $classes ?: [AlrhaClass::Hunters, AlrhaClass::Varmint];

        DB::transaction(function () use ($match, $classes) {
            $match->update([
                'scoring_type' => 'alrha',
                'elr_distance_based_scoring' => false,
            ]);

            $profile = ElrScoringProfile::updateOrCreate(
                ['match_id' => $match->id, 'name' => self::PROFILE_NAME],
                ['multipliers' => self::PROFILE_MULTIPLIERS],
            );
            $match->update(['elr_scoring_profile_id' => $profile->id]);

            // Drop only the stages we own — an operator's hand-authored
            // stages (no alrha_class tag) survive re-applies.
            foreach ($classes as $class) {
                $match->elrStages()
                    ->where('alrha_class', $class->value)
                    ->get()
                    ->each(fn (ElrStage $s) => $s->delete());
            }

            $baseSort = (int) ($match->elrStages()->max('sort_order') ?? 0);

            $categorySlugs = collect($classes)
                ->flatMap(fn (AlrhaClass $c) => $c->categorySlugs())
                ->unique()
                ->values();

            foreach ($classes as $class) {
                $this->buildClassStages($match, $profile->id, $class, $baseSort);
                $baseSort += 3; // CBC + Far + Near per class
            }

            foreach ($categorySlugs as $sort => $slug) {
                MatchCategory::updateOrCreate(
                    ['match_id' => $match->id, 'slug' => $slug],
                    ['name' => ucfirst($slug), 'sort_order' => $sort],
                );
            }

            // For dual-class matches we clear the legacy match-level class;
            // for single-class we keep it in sync so old consumers still work.
            $match->update([
                'alrha_class' => count($classes) === 1 ? $classes[0]->value : null,
                'team_size' => $this->needsTeams($classes) ? 2 : ($match->team_size ?: 1),
            ]);
        });

        $match->refresh();
    }

    /**
     * Whether any of the given classes uses team scoring (Hunters).
     *
     * @param  array<int, AlrhaClass>  $classes
     */
    private function needsTeams(array $classes): bool
    {
        foreach ($classes as $class) {
            if ($class->hasTeamScoring()) {
                return true;
            }
        }

        return false;
    }

    private function buildClassStages(ShootingMatch $match, int $profileId, AlrhaClass $class, int $baseSort): void
    {
        $prefix = $class->label();

        $cbc = $match->elrStages()->create([
            'label' => "{$prefix} — Cold Bore Challenge",
            'stage_type' => 'static',
            'elr_scoring_profile_id' => $profileId,
            'sort_order' => $baseSort + 1,
            'alrha_class' => $class->value,
        ]);
        $cbc->targets()->create([
            'name' => $class->coldBoreTargetName(),
            'distance_m' => $class->coldBoreDistance(),
            'base_points' => 1,
            'max_shots' => 1,
            'must_hit_to_advance' => false,
            'sort_order' => 1,
            'is_cold_bore' => true,
            'alrha_block' => 'cbc',
        ]);

        $far = $match->elrStages()->create([
            'label' => "{$prefix} — Far block",
            'stage_type' => 'static',
            'elr_scoring_profile_id' => $profileId,
            'sort_order' => $baseSort + 2,
            'alrha_class' => $class->value,
        ]);
        foreach ($class->farBlockDistances() as $i => $distance) {
            $far->targets()->create([
                'name' => "{$distance} m",
                'distance_m' => $distance,
                'base_points' => 1,
                'max_shots' => 5,
                'must_hit_to_advance' => false,
                'sort_order' => $i + 1,
                'alrha_block' => 'far',
            ]);
        }

        $near = $match->elrStages()->create([
            'label' => "{$prefix} — Near block",
            'stage_type' => 'static',
            'elr_scoring_profile_id' => $profileId,
            'sort_order' => $baseSort + 3,
            'alrha_class' => $class->value,
        ]);
        foreach ($class->nearBlockDistances() as $i => $distance) {
            $near->targets()->create([
                'name' => "{$distance} m",
                'distance_m' => $distance,
                'base_points' => 1,
                'max_shots' => 5,
                'must_hit_to_advance' => false,
                'sort_order' => $i + 1,
                'alrha_block' => 'near',
            ]);
        }
    }
}
