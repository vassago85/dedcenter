<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the combined SAPRF Provincial + PPRC Club Match on 2 May 2026.
 *
 * Reality on the day: one physical event, two classifications running together.
 *   - SAPRF Provincial shooters:   10 shots per stage × 6 stages = 60 rounds
 *   - PPRC Club Match shooters:     7 shots per stage × 6 stages = 42 rounds
 *
 * DeadCenter match model does not currently support variable shot counts
 * per shooter within a stage, so the match is seeded as a single 10-shot
 * stage definition (Provincial-sized). Club shooters simply stop at 7
 * shots; their extra 3 slots are left unscored. Leaderboards naturally
 * split by division — a "Club - Open" shooter is compared only against
 * other "Club - Open" shooters, so the out-of-42 vs out-of-60 delta is
 * implicit in the grouping.
 *
 * Tomorrow we only push squads 1, 2 and 4 through DeadCenter alongside
 * PractiScore, but all 5 squads are seeded so the whole match is present
 * if we want to extend the test during the day.
 *
 * Re-running is safe: stages, squads and shooters are wiped and rebuilt.
 */
class SaprfPprcMatch20260502Seeder extends Seeder
{
    public function run(): void
    {
        $paul = User::where('email', 'paul@charsley.co.za')->first()
            ?? User::where('role', 'owner')->first();

        if (! $paul) {
            $this->command->error('No user paul@charsley.co.za or owner found; aborting.');
            return;
        }

        // PPRC hosts the event. Fall back to Royal Flush / Paul's first org
        // so this still seeds cleanly on a fresh DB without PPRC set up.
        $org = Organization::where('slug', 'like', 'pprc%')->orderBy('id')->first()
            ?? Organization::where('slug', 'like', 'pretoria%')->orderBy('id')->first()
            ?? Organization::where('slug', 'like', 'royal-flush%')->orderBy('id')->first()
            ?? $paul->organizations()->orderBy('organizations.id')->first()
            ?? Organization::where('status', 'active')->orderBy('id')->first()
            ?? Organization::orderBy('id')->first();

        if (! $org) {
            $this->command->error('No organization found; aborting.');
            return;
        }

        $matchName = 'SAPRF Provincial + PPRC Club Match — 2 May 2026';

        DB::transaction(function () use ($paul, $org, $matchName) {
            $match = ShootingMatch::firstOrNew([
                'organization_id' => $org->id,
                'name' => $matchName,
            ]);

            $isNew = ! $match->exists;

            $match->date = '2026-05-02';
            $match->location = 'PPRC Range';
            $match->status = MatchStatus::Active;
            $match->scoring_type = 'prs';
            $match->scores_published = true;
            $match->concurrent_relays = 3;
            $match->max_squad_size = 10;
            $match->entry_fee = 0;
            $match->self_squadding_enabled = false;
            $match->royal_flush_enabled = false;
            $match->side_bet_enabled = false;
            $match->notes = '6 stages — only Stage 1 (tiebreaker) is timed, the rest are untimed. Provincial: 10 shots/stage = 60 rounds. Club: 7 shots/stage = 42 rounds. Testing alongside PractiScore with squads 1, 2, 4.';
            $match->created_by = $paul->id;
            $match->save();

            $this->command?->info(($isNew ? 'Created' : 'Updated')." match [{$match->id}] {$match->name}.");

            // Divisions — Provincial ones are plain, Club ones are prefixed
            // so the leaderboard naturally segregates Provincial from Club
            // while still grouping Open/Limited/Factory within each.
            $divOpen      = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Open'],             ['sort_order' => 1]);
            $divLimited   = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Limited/Tactical'], ['sort_order' => 2]);
            $divFactory   = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Factory'],          ['sort_order' => 3]);
            $divClubOpen  = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Club - Open'],      ['sort_order' => 4]);
            $divClubLim   = MatchDivision::firstOrCreate(['match_id' => $match->id, 'name' => 'Club - Limited/Tactical'], ['sort_order' => 5]);

            // Categories are age/gender overlays on top of divisions. Per the
            // rule from the MD: non-club ladies / seniors / juniors shoot in
            // Open division with these categories set.
            $catOverall = MatchCategory::firstOrCreate(
                ['match_id' => $match->id, 'slug' => 'overall'],
                ['name' => 'Overall', 'sort_order' => 1]
            );
            $catSeniors = MatchCategory::firstOrCreate(
                ['match_id' => $match->id, 'slug' => 'seniors'],
                ['name' => 'Seniors', 'sort_order' => 2]
            );
            $catLadies = MatchCategory::firstOrCreate(
                ['match_id' => $match->id, 'slug' => 'ladies'],
                ['name' => 'Ladies', 'sort_order' => 3]
            );
            $catJuniors = MatchCategory::firstOrCreate(
                ['match_id' => $match->id, 'slug' => 'juniors'],
                ['name' => 'Juniors', 'sort_order' => 4]
            );

            // Wipe target sets so re-seed is idempotent.
            foreach ($match->targetSets as $existing) {
                $existing->gongs()->delete();
                $existing->delete();
            }

            // 6 stages × 10 shots. Only Stage 1 (the tiebreaker) is timed —
            // the rest are untimed, so par_time_seconds is null and
            // is_timed_stage is false. Distances are placeholder — the actual
            // PRS stage design lives on the range book; these are only used
            // by the scoring app to render the shot grid. MD can tune in admin.
            $stageDefs = [
                [
                    'label' => 'Stage 1 — Tiebreaker',
                    'timed' => true,
                    'par' => 60.0,
                    'tiebreaker' => true,
                    'distances' => [300, 300, 400, 400, 500, 500, 600, 600, 700, 700],
                ],
                [
                    'label' => 'Stage 2',
                    'timed' => false,
                    'par' => null,
                    'tiebreaker' => false,
                    'distances' => [300, 300, 350, 350, 400, 400, 450, 450, 500, 500],
                ],
                [
                    'label' => 'Stage 3',
                    'timed' => false,
                    'par' => null,
                    'tiebreaker' => false,
                    'distances' => [400, 400, 450, 450, 500, 500, 550, 550, 600, 600],
                ],
                [
                    'label' => 'Stage 4',
                    'timed' => false,
                    'par' => null,
                    'tiebreaker' => false,
                    'distances' => [500, 500, 550, 550, 600, 600, 650, 650, 700, 700],
                ],
                [
                    'label' => 'Stage 5',
                    'timed' => false,
                    'par' => null,
                    'tiebreaker' => false,
                    'distances' => [350, 350, 450, 450, 550, 550, 650, 650, 750, 750],
                ],
                [
                    'label' => 'Stage 6',
                    'timed' => false,
                    'par' => null,
                    'tiebreaker' => false,
                    'distances' => [400, 500, 500, 600, 600, 700, 700, 800, 800, 900],
                ],
            ];

            foreach ($stageDefs as $i => $stage) {
                $ts = TargetSet::create([
                    'match_id' => $match->id,
                    'label' => $stage['label'],
                    'distance_meters' => 0,
                    'distance_multiplier' => 1,
                    'sort_order' => $i + 1,
                    'is_tiebreaker' => $stage['tiebreaker'],
                    'par_time_seconds' => $stage['par'],
                    'total_shots' => count($stage['distances']),
                    'stage_number' => $i + 1,
                    'is_timed_stage' => $stage['timed'],
                ]);

                foreach ($stage['distances'] as $j => $distance) {
                    Gong::create([
                        'target_set_id' => $ts->id,
                        'number' => $j + 1,
                        'label' => 'T' . ($j + 1),
                        'multiplier' => 1.00,
                        'distance_meters' => $distance,
                        'target_size' => '1 MOA',
                    ]);
                }
            }

            // Wipe squads/shooters so re-seed is idempotent.
            Squad::where('match_id', $match->id)->get()->each(function (Squad $squad) {
                $squad->shooters()->delete();
                $squad->delete();
            });

            // Roster definition. Division keys are resolved below.
            //   p = SAPRF Provincial (10 shots/stage)
            //   c = PPRC Club Match (7 shots/stage)
            $roster = [
                // ── Squad 1 ──
                ['squad' => 1, 'name' => 'Johan Nel',                 'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 1, 'name' => 'Chris Pretorius',           'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 1, 'name' => 'Danie Kruger',              'div' => 'club_lim',  'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 1, 'name' => 'Danie Du Preez',            'div' => 'open',      'cat' => 'seniors', 'comp' => 'p'],
                ['squad' => 1, 'name' => 'Andries Lategan',           'div' => 'open',      'cat' => 'seniors', 'comp' => 'p'],
                ['squad' => 1, 'name' => 'Schalk Van Der Merwe',      'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 1, 'name' => 'Donovan Cook',              'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 1, 'name' => 'Pieter Niemand',            'div' => 'limited',   'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 1, 'name' => 'Perey Labuschagne',         'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],

                // ── Squad 2 ──
                ['squad' => 2, 'name' => 'Marcel Steyn',              'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 2, 'name' => 'Sean Swarts',               'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 2, 'name' => 'Leon Goosen',               'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 2, 'name' => 'Hendrik Nel',               'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 2, 'name' => 'Franco Cilliers',           'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 2, 'name' => 'Ruan Du Plessis',           'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 2, 'name' => 'Tiaan Wessels',             'div' => 'club_open', 'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 2, 'name' => 'Francois De Kock',          'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 2, 'name' => 'Jandre Badenhorst',         'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],

                // ── Squad 3 ──
                ['squad' => 3, 'name' => 'Ismail Arbee',              'div' => 'club_open', 'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 3, 'name' => 'Juro Gurovich',             'div' => 'club_lim',  'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 3, 'name' => 'Justin Le Roux',            'div' => 'club_open', 'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 3, 'name' => 'Rob Jatho',                 'div' => 'club_open', 'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 3, 'name' => 'Neville Glynn',             'div' => 'club_open', 'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 3, 'name' => 'Michael Andrews',           'div' => 'club_open', 'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 3, 'name' => 'Adrian Janse Van Rensburg', 'div' => 'club_open', 'cat' => 'seniors', 'comp' => 'c'],
                ['squad' => 3, 'name' => 'Steven Janse Van Rensburg', 'div' => 'club_open', 'cat' => 'seniors', 'comp' => 'c'],
                ['squad' => 3, 'name' => 'Tiaan Klopper',             'div' => 'club_lim',  'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 3, 'name' => 'Mohamed Ayob',              'div' => 'limited',   'cat' => 'overall', 'comp' => 'p'],

                // ── Squad 4 ──
                ['squad' => 4, 'name' => 'Andre Pj Van Der Westhuizen','div'=> 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 4, 'name' => 'Anton Coetzer',             'div' => 'club_open', 'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 4, 'name' => 'Dirk Pio',                  'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 4, 'name' => 'Henri Klopper',             'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 4, 'name' => 'Paul Charsley',             'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 4, 'name' => 'Francois Van Wyk',          'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 4, 'name' => 'Stephan Van Der Merwe',     'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 4, 'name' => 'Gerhard Smit',              'div' => 'club_open', 'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 4, 'name' => 'Leonard Van Staden',        'div' => 'club_open', 'cat' => 'overall', 'comp' => 'c'],
                ['squad' => 4, 'name' => 'Jaco Van Tonder',           'div' => 'club_open', 'cat' => 'overall', 'comp' => 'c'],

                // ── Squad 5 ──
                ['squad' => 5, 'name' => 'Jandre Weideman',           'div' => 'limited',   'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 5, 'name' => 'Aliza Mey',                 'div' => 'open',      'cat' => 'ladies',  'comp' => 'p'],
                ['squad' => 5, 'name' => 'Liné De Witt',              'div' => 'open',      'cat' => 'ladies',  'comp' => 'p'],
                ['squad' => 5, 'name' => 'Kim-Leigh Ferreira',        'div' => 'open',      'cat' => 'ladies',  'comp' => 'p'],
                ['squad' => 5, 'name' => 'Russell Ferreira',          'div' => 'factory',   'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 5, 'name' => 'Clive Mey',                 'div' => 'factory',   'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 5, 'name' => 'Chris Leeson',              'div' => 'factory',   'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 5, 'name' => 'Sean Graham',               'div' => 'open',      'cat' => 'overall', 'comp' => 'p'],
                ['squad' => 5, 'name' => 'Trevor Graham',             'div' => 'open',      'cat' => 'seniors', 'comp' => 'p'],
            ];

            $divisionMap = [
                'open'      => $divOpen->id,
                'limited'   => $divLimited->id,
                'factory'   => $divFactory->id,
                'club_open' => $divClubOpen->id,
                'club_lim'  => $divClubLim->id,
            ];
            $categoryMap = [
                'overall' => $catOverall->id,
                'seniors' => $catSeniors->id,
                'ladies'  => $catLadies->id,
                'juniors' => $catJuniors->id,
            ];

            // Create all 5 squads first.
            $squads = [];
            foreach (range(1, 5) as $sIdx) {
                $squads[$sIdx] = Squad::create([
                    'match_id' => $match->id,
                    'name' => "Squad {$sIdx}",
                    'sort_order' => $sIdx,
                    'max_capacity' => 10,
                ]);
            }

            $sortBySquad = array_fill_keys(array_keys($squads), 0);
            $bibCounter = 1;

            foreach ($roster as $entry) {
                $sortBySquad[$entry['squad']]++;

                $slug = Str::slug($entry['name'], '.');
                $email = "test.{$slug}@import.invalid";
                $user = User::where('email', $email)->first();
                if (! $user) {
                    $user = User::create([
                        'name' => $entry['name'],
                        'email' => $email,
                        'password' => bcrypt(Str::random(32)),
                    ]);
                }

                $reg = MatchRegistration::firstOrCreate(
                    ['match_id' => $match->id, 'user_id' => $user->id],
                    [
                        'payment_status' => 'confirmed',
                        'payment_reference' => MatchRegistration::generatePaymentReference($user),
                        'amount' => 0,
                        'is_free_entry' => true,
                    ]
                );
                if ($reg->wasRecentlyCreated || empty($reg->admin_notes)) {
                    $reg->admin_notes = $entry['comp'] === 'c'
                        ? 'PPRC Club Match — 42 rounds (7 shots/stage)'
                        : 'SAPRF Provincial — 60 rounds (10 shots/stage)';
                    $reg->save();
                }

                $bibPrefix = $entry['comp'] === 'c' ? 'C' : 'P';
                $bib = $bibPrefix . str_pad((string) $bibCounter, 3, '0', STR_PAD_LEFT);

                $shooter = Shooter::create([
                    'squad_id' => $squads[$entry['squad']]->id,
                    'name' => $entry['name'],
                    'user_id' => $user->id,
                    'bib_number' => $bib,
                    'match_division_id' => $divisionMap[$entry['div']],
                    'sort_order' => $sortBySquad[$entry['squad']],
                    'status' => 'active',
                ]);
                $shooter->categories()->syncWithoutDetaching([$categoryMap[$entry['cat']]]);

                $bibCounter++;
            }

            $this->command?->info('5 squads seeded (up to 10 shooters each).');
            $this->command?->info('6 stages × 10 shots (Stage 1 = tiebreaker). Club shooters stop at shot 7.');
            $this->command?->info("Scoreboard URL: /scoreboard/{$match->id}");
        });
    }
}
