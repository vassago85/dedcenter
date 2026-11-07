<?php

namespace Database\Seeders;

use App\Enums\ElrShotResult;
use App\Models\ElrShot;
use App\Models\ElrTarget;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Services\Scoring\ELRScoringService;
use Illuminate\Database\Seeder;

/**
 * Squad 3 paper scores for Peregrine Alma, 7 Nov 2026.
 *
 * Nine characters per station, three shots on each of the three gongs that
 * shooter's class engages (Minor gongs 1–3, Major gongs 2–4). 1 is a hit,
 * 0 is a miss, "." is a cell the photo did not settle — those are left
 * unscored. Brothers Arms, Delta Optics and Zeiss Optics are omitted: the
 * sheet was blank, struck through, or too overlapped to read.
 *
 * Idempotent for the cells it writes. Run after PeregrineAlma7Nov2026Seeder:
 *   docker exec deadcenter-app php artisan db:seed --class=PeregrineAlma7Nov2026ScoresSeeder
 */
class PeregrineAlma7Nov2026ScoresSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    private const SCORES = [
        'Carl Louw' => [
            'Warrior' => '011111010',
            'Integrix' => '001001000',
        ],
        'De Waal Uys' => [
            'Warrior' => '001010100',
            'Integrix' => '011000000',
        ],
        'Brian Beeming' => [
            'Warrior' => '110000101',
            'Integrix' => '101000000',
        ],
        'Jason Odendaal' => [
            'Warrior' => '101011100',
            'Integrix' => '000000000',
        ],
        'Danie Koch' => [
            'Warrior' => '111111111',
            'Integrix' => '111111...',
        ],
        'JD Els' => [
            'Warrior' => '001011111',
            'Integrix' => '010111...',
        ],
        'Pieter Meyer' => [
            'Warrior' => '011101000',
            'Integrix' => '111011101',
        ],
        'Karien Els' => [
            'Warrior' => '001111100',
            'Integrix' => '111101101',
        ],
        'Eddie Engelbrecht' => [
            'Warrior' => '111111000',
            'Integrix' => '011010000',
        ],
        'Henry King' => [
            'Warrior' => '011111000',
            'Integrix' => '010001000',
        ],
        'Christiaan Liebenberg' => [
            'Warrior' => '011011110',
            'Integrix' => '111011000',
        ],
        'Herman Boshoff' => [
            'Warrior' => '100011.11',
        ],
        'Jaco van der Merwe' => [
            'Warrior' => '001111101',
            'Integrix' => '101001000',
        ],
        'Andre ten Haaf' => [
            'Warrior' => '111101101',
            'Integrix' => '111011100',
        ],
    ];

    public function run(): void
    {
        $match = ShootingMatch::query()->where('name', PeregrineAlma7Nov2026Seeder::MATCH_NAME)->first();
        if (! $match) {
            $this->command?->error('Alma 7 Nov match not found — roster seeder has not been run.');

            return;
        }

        $service = app(ELRScoringService::class);
        $stages = $match->elrStages()->with(['targets' => fn ($q) => $q->orderBy('sort_order')])->get()->keyBy('label');
        $written = 0;
        $skipped = 0;

        foreach (self::SCORES as $name => $stations) {
            $shooter = Shooter::query()
                ->where('name', $name)
                ->whereHas('squad', fn ($q) => $q->where('match_id', $match->id))
                ->with('division.elrTargets:id')
                ->first();

            if (! $shooter) {
                $this->command?->warn("No shooter named {$name} on the Alma match — skipped.");

                continue;
            }

            $allowed = $shooter->division?->elrTargets->pluck('id') ?? collect();

            foreach ($stations as $label => $grid) {
                $stage = $stages->get($label);
                if (! $stage) {
                    $this->command?->warn("Station {$label} is not on the Alma match.");

                    continue;
                }

                $engaged = $stage->targets
                    ->filter(fn (ElrTarget $target) => $allowed->contains($target->id))
                    ->sortBy('sort_order')
                    ->values();

                $touched = [];
                foreach (str_split($grid) as $index => $mark) {
                    if ($mark !== '0' && $mark !== '1') {
                        $skipped++;

                        continue;
                    }

                    $target = $engaged->get(intdiv($index, 3));
                    if (! $target) {
                        $this->command?->warn("{$name} has no engaged gong for {$label} column ".($index + 1).'.');

                        continue;
                    }

                    $service->recordShot(
                        $shooter,
                        $target,
                        ($index % 3) + 1,
                        $mark === '1' ? ElrShotResult::Hit : ElrShotResult::Miss,
                        $match->created_by,
                        'paper-squad3',
                    );
                    $touched[$target->id] = $target;
                    $written++;
                }

                foreach ($touched as $target) {
                    $service->recomputeTargetImpacts($shooter->id, $target);
                }
            }
        }

        $this->command?->info("Alma squad 3 paper scores: {$written} shots written, {$skipped} cells left blank.");
    }
}
