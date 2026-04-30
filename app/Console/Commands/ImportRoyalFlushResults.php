<?php

namespace App\Console\Commands;

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports a Royal Flush match's final results from a PHP fixture file
 * and writes a fully scored, completed match into the database.
 *
 * Usage:
 *   php artisan rf:import-results database/data/royal-flush/2026-02-21.php
 *   php artisan rf:import-results database/data/royal-flush/2026-03-28.php --dry-run
 *
 * Fixture shape (see database/data/royal-flush/*.php):
 *   ['meta' => ['name', 'date', 'organization_slug', 'location'],
 *    'shooters' => [['pos', 'name', 'cartridge', 'score', 'hit_pct'], ...]]
 *
 * Per-cell hit/miss patterns are NOT in the fixture — only the documented
 * total Score and Hit Rate. The command reconstructs a pattern that
 * matches both exactly via subset-sum DP, preferring misses on the
 * harder gongs at the longer distances. Totals, leaderboard order, and
 * hit-rate analytics are exact; per-gong patterns are reconstructed.
 */
class ImportRoyalFlushResults extends Command
{
    protected $signature = 'rf:import-results
        {fixture : Relative or absolute path to a Royal Flush results fixture}
        {--dry-run : Verify reconstruction only; do not write to the database}
        {--squad-size=10 : Shooters per relay (squads are filled in finishing-position order)}
        {--restore : If the match was soft-deleted, restore it before re-seeding}';

    protected $description = 'Import a completed Royal Flush match (per-shooter Score + Hit Rate) into the leaderboard.';

    /** Royal Flush spreadsheet uses 1.00, 1.30, 1.50, 1.80, 2.00 (G1 biggest → G5 smallest). */
    private const GONG_MULTIPLIERS = [1.00, 1.30, 1.50, 1.80, 2.00];
    private const DISTANCES        = [400, 500, 600, 700];

    public function handle(): int
    {
        $fixturePath = $this->resolveFixturePath((string) $this->argument('fixture'));
        if ($fixturePath === null) {
            return self::FAILURE;
        }

        $fixture = require $fixturePath;
        if (! is_array($fixture) || ! isset($fixture['meta'], $fixture['shooters'])) {
            $this->error("Fixture {$fixturePath} is malformed (missing 'meta' or 'shooters').");
            return self::FAILURE;
        }

        $meta = $fixture['meta'];
        $shooters = $fixture['shooters'];
        $squadSize = max(1, (int) $this->option('squad-size'));
        $dryRun = (bool) $this->option('dry-run');

        $this->line("Fixture: {$fixturePath}");
        $this->line("Match:   {$meta['name']}  ({$meta['date']})");
        $this->line('Shooters: '.count($shooters).'  ·  squad size: '.$squadSize.($dryRun ? '  ·  DRY RUN' : ''));
        $this->newLine();

        $reconstructed = $this->reconstructAll($shooters);
        if ($reconstructed === null) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry run successful — every shooter\'s pattern reconstructed and verified.');
            return self::SUCCESS;
        }

        $org = Organization::where('slug', $meta['organization_slug'])->first()
            ?? Organization::where('slug', 'like', $meta['organization_slug'].'%')->orderBy('id')->first()
            ?? Organization::where('name', 'Royal Flush')->first();

        if (! $org) {
            $this->error("Organization '{$meta['organization_slug']}' not found. Run RoyalFlush18April2026Seeder first to bootstrap the org.");
            return self::FAILURE;
        }
        $this->line("Org: [{$org->id}] {$org->name} (slug={$org->slug})");

        try {
            DB::transaction(function () use ($org, $meta, $reconstructed, $squadSize) {
                $match = $this->upsertMatch($org, $meta);
                $gongs = $this->ensureTargetSetsAndGongs($match);
                $this->wipeMatchShooters($match);
                $this->writeShootersAndScores($match, $reconstructed, $gongs, $squadSize);
                $match->status = MatchStatus::Completed;
                $match->scores_published = true;
                $match->save();
            });
        } catch (\Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Import complete.');
        return self::SUCCESS;
    }

    // ── Fixture / reconstruction ─────────────────────────────────────────

    private function resolveFixturePath(string $path): ?string
    {
        $candidates = [$path, base_path($path)];
        foreach ($candidates as $c) {
            if (is_readable($c) && is_file($c)) {
                return realpath($c) ?: $c;
            }
        }
        $this->error("Fixture not readable: {$path}");
        return null;
    }

    /**
     * Returns an array shaped like the input shooters, plus a 'pattern' key
     * containing 4×5 binary hits, or null on any failure (errors printed).
     *
     * @param array<int, array{pos:int,name:string,cartridge:string,score:int,hit_pct:float}> $shooters
     * @return array<int, array{pos:int,name:string,cartridge:string,score:int,hit_pct:float,pattern:int[][],actual_score:float}>|null
     */
    private function reconstructAll(array $shooters): ?array
    {
        $cellValuesX10 = $this->cellValuesTimesTen();
        $maxSumX10 = array_sum($cellValuesX10);
        $cellCount = count($cellValuesX10);

        $errors = [];
        $out = [];
        $maxNameLen = max(array_map(fn ($s) => mb_strlen($s['name']), $shooters));

        $this->line(str_pad('Pos', 4)
            .' '.str_pad('Name', $maxNameLen + 2)
            .' '.str_pad('Score', 6)
            .' '.str_pad('HR', 6)
            .' '.str_pad('Reconstructed', 14)
            .' Notes');

        foreach ($shooters as $row) {
            $pos = $row['pos'];
            $name = $row['name'];
            $score = (int) $row['score'];
            $hitPct = (float) $row['hit_pct'];

            $hitCount = (int) round($hitPct * $cellCount / 100.0);
            if ($hitCount < 0 || $hitCount > $cellCount) {
                $errors[] = "Pos {$pos} {$name}: hit_pct {$hitPct} → invalid hit count {$hitCount}";
                continue;
            }

            if ($score === 0 && $hitCount === 0) {
                $pattern = $this->emptyPattern();
                $actual = 0.0;
            } elseif ($score > 0 && $hitCount === $cellCount) {
                $pattern = $this->fullPattern();
                $actual = $maxSumX10 / 10.0;
            } else {
                $missCount = $cellCount - $hitCount;

                // The source spreadsheet shows the integer Score using
                // truncation (floor), not round-half-up — verified against
                // Pos 15 Gerhardu Odendaal (16 hits, real 117.8 → "117").
                // So real_score ∈ [score, score + 1), i.e. real_x10 ∈
                // [10*score, 10*score + 9], giving sum-of-misses in
                // [maxSum - 10*score - 9, maxSum - 10*score].
                $targetSumLow = max(0, $maxSumX10 - (10 * $score + 9));
                $targetSumHigh = min($maxSumX10, $maxSumX10 - 10 * $score);

                $missIndices = $this->findMissIndices($cellValuesX10, $missCount, $targetSumLow, $targetSumHigh);
                if ($missIndices === null) {
                    $errors[] = sprintf(
                        'Pos %d %s: cannot reconstruct (hits=%d, score=%d, target sum range %d..%d)',
                        $pos, $name, $hitCount, $score, $targetSumLow, $targetSumHigh
                    );
                    continue;
                }
                $pattern = $this->patternFromMissIndices($missIndices);
                $actual = ($maxSumX10 - array_sum(array_map(fn ($i) => $cellValuesX10[$i], $missIndices))) / 10.0;
            }

            $computedHits = 0;
            foreach ($pattern as $bank) {
                foreach ($bank as $cell) {
                    if ($cell === 1) {
                        $computedHits++;
                    }
                }
            }
            if ($computedHits !== $hitCount) {
                $errors[] = "Pos {$pos} {$name}: pattern hit count {$computedHits} ≠ expected {$hitCount}";
                continue;
            }
            if ((int) floor($actual + 1e-9) !== $score) {
                $errors[] = sprintf(
                    'Pos %d %s: reconstructed score %.1f does not floor to documented %d',
                    $pos, $name, $actual, $score
                );
                continue;
            }

            $out[] = $row + ['pattern' => $pattern, 'actual_score' => $actual];

            $this->line(str_pad((string) $pos, 4)
                .' '.str_pad($name, $maxNameLen + 2)
                .' '.str_pad((string) $score, 6)
                .' '.str_pad(number_format($hitPct, 1).'%', 6)
                .' '.str_pad(number_format($actual, 1).' / '.$score, 14)
                .' OK');
        }

        if (! empty($errors)) {
            $this->newLine();
            $this->error('Reconstruction failed for '.count($errors).' shooter(s):');
            foreach ($errors as $e) {
                $this->line('  · '.$e);
            }
            return null;
        }

        return $out;
    }

    /**
     * @return int[]  Cell values × 10, indexed bank*5 + gong (banks: 400,500,600,700)
     */
    private function cellValuesTimesTen(): array
    {
        $values = [];
        foreach (self::DISTANCES as $distance) {
            foreach (self::GONG_MULTIPLIERS as $mult) {
                $values[] = (int) round($distance / 100 * $mult * 10);
            }
        }
        return $values;
    }

    /** @return int[]|null Cell indices marked as miss, or null if no valid subset exists. */
    private function findMissIndices(array $cellValuesX10, int $missCount, int $targetLow, int $targetHigh): ?array
    {
        if ($missCount < 0 || $targetLow > $targetHigh) {
            return null;
        }

        $n = count($cellValuesX10);

        // Sort by value desc to bias the greedy traceback toward harder gongs.
        $sorted = [];
        for ($i = 0; $i < $n; $i++) {
            $sorted[] = ['index' => $i, 'value' => $cellValuesX10[$i]];
        }
        usort($sorted, fn ($a, $b) => $b['value'] <=> $a['value']);

        $maxSum = $targetHigh;
        // dp[i][k] = bitset (string) of reachable sums using first i sorted cells with k chosen.
        // We use plain bool arrays for clarity over micro-optimised bitsets — n=20 is tiny.
        $dp = array_fill(0, $n + 1, array_fill(0, $missCount + 1, []));
        $dp[0][0][0] = true;

        for ($i = 0; $i < $n; $i++) {
            $val = $sorted[$i]['value'];
            for ($k = 0; $k <= $missCount; $k++) {
                foreach ($dp[$i][$k] as $s => $_) {
                    $dp[$i + 1][$k][$s] = true;
                    if ($k + 1 <= $missCount && ($s + $val) <= $maxSum) {
                        $dp[$i + 1][$k + 1][$s + $val] = true;
                    }
                }
            }
        }

        $foundSum = null;
        for ($s = $targetLow; $s <= $targetHigh; $s++) {
            if (isset($dp[$n][$missCount][$s])) {
                $foundSum = $s;
                break;
            }
        }
        if ($foundSum === null) {
            return null;
        }

        // Traceback: prefer "in subset" (= miss) when both options are valid.
        // Iterating sorted-desc means we tend to mark the harder gongs as misses.
        $missIndices = [];
        $k = $missCount;
        $s = $foundSum;
        for ($i = $n - 1; $i >= 0; $i--) {
            $val = $sorted[$i]['value'];
            $canBeIn = $k > 0 && ($s - $val) >= 0 && isset($dp[$i][$k - 1][$s - $val]);
            if ($canBeIn) {
                $missIndices[] = $sorted[$i]['index'];
                $k--;
                $s -= $val;
            }
        }

        return $missIndices;
    }

    /** @return int[][]  4 banks × 5 gongs of 0/1, all zeros. */
    private function emptyPattern(): array
    {
        return array_fill(0, count(self::DISTANCES), array_fill(0, count(self::GONG_MULTIPLIERS), 0));
    }

    /** @return int[][]  4 banks × 5 gongs of 0/1, all ones. */
    private function fullPattern(): array
    {
        return array_fill(0, count(self::DISTANCES), array_fill(0, count(self::GONG_MULTIPLIERS), 1));
    }

    /**
     * @param int[] $missIndices  flat indices into the 4×5 grid
     * @return int[][]
     */
    private function patternFromMissIndices(array $missIndices): array
    {
        $pattern = $this->fullPattern();
        $gongCount = count(self::GONG_MULTIPLIERS);
        foreach ($missIndices as $flat) {
            $bank = intdiv($flat, $gongCount);
            $gong = $flat % $gongCount;
            $pattern[$bank][$gong] = 0;
        }
        return $pattern;
    }

    // ── DB writes ────────────────────────────────────────────────────────

    private function upsertMatch(Organization $org, array $meta): ShootingMatch
    {
        $match = ShootingMatch::withTrashed()->firstOrNew([
            'organization_id' => $org->id,
            'name' => $meta['name'],
            'date' => $meta['date'],
        ]);

        if ($match->exists && $match->trashed()) {
            if (! $this->option('restore')) {
                throw new \RuntimeException(
                    "Match '{$meta['name']}' is soft-deleted. Re-run with --restore to bring it back."
                );
            }
            $match->restore();
            $this->line("Restored archived match [{$match->id}].");
        }

        if (! $match->exists) {
            $match->status = MatchStatus::Completed;
        }
        $match->location = $meta['location'] ?? $match->location;
        $match->royal_flush_enabled = true;
        $match->side_bet_enabled = (bool) ($match->side_bet_enabled ?? false);
        $match->scoring_type = in_array($match->scoring_type, ['standard', 'prs', 'elr'], true)
            ? $match->scoring_type
            : 'standard';
        $match->concurrent_relays = $match->concurrent_relays ?: 2;
        $match->max_squad_size = $match->max_squad_size ?: 10;
        $match->self_squadding_enabled = false;
        $match->created_by ??= User::query()->value('id');
        $match->save();

        $this->line(($match->wasRecentlyCreated ? 'Created' : 'Reusing').' match ['.$match->id.'].');
        return $match;
    }

    /**
     * @return array<int, array<int, Gong>>  bank index (0..3) → gong index (0..4) → Gong
     */
    private function ensureTargetSetsAndGongs(ShootingMatch $match): array
    {
        $gongs = [];
        foreach (self::DISTANCES as $bankIdx => $distance) {
            $ts = TargetSet::firstOrCreate(
                ['match_id' => $match->id, 'distance_meters' => $distance],
                [
                    'label' => "{$distance}m",
                    'distance_multiplier' => $distance / 100,
                    'sort_order' => $bankIdx + 1,
                ]
            );
            $ts->fill([
                'label' => "{$distance}m",
                'distance_multiplier' => $distance / 100,
                'sort_order' => $bankIdx + 1,
            ])->save();

            $existing = Gong::where('target_set_id', $ts->id)->orderBy('number')->get()->keyBy('number');
            $bankGongs = [];
            foreach (self::GONG_MULTIPLIERS as $gongIdx => $mult) {
                $number = $gongIdx + 1;
                $row = $existing->get($number);
                if ($row) {
                    $row->fill(['label' => "G{$number}", 'multiplier' => number_format($mult, 2)])->save();
                    $bankGongs[$gongIdx] = $row;
                } else {
                    $bankGongs[$gongIdx] = Gong::create([
                        'target_set_id' => $ts->id,
                        'number' => $number,
                        'label' => "G{$number}",
                        'multiplier' => number_format($mult, 2),
                    ]);
                }
            }
            $gongs[$bankIdx] = $bankGongs;
        }
        return $gongs;
    }

    private function wipeMatchShooters(ShootingMatch $match): void
    {
        $squadIds = Squad::where('match_id', $match->id)->pluck('id');
        if ($squadIds->isNotEmpty()) {
            // Cascading deletes wipe scores via shooters → scores FK.
            Shooter::whereIn('squad_id', $squadIds)->delete();
        }
    }

    /**
     * @param array<int, array<string,mixed>> $shooters Reconstructed shooters with 'pattern'
     * @param array<int, array<int, Gong>>     $gongs
     */
    private function writeShootersAndScores(ShootingMatch $match, array $shooters, array $gongs, int $squadSize): void
    {
        $squadByIdx = $this->ensureRelays($match, count($shooters), $squadSize);
        $now = now()->toDateTimeString();

        $stats = ['users_created' => 0, 'users_existing' => 0, 'shooters' => 0, 'scores' => 0];

        foreach ($shooters as $i => $row) {
            $relayIdx = intdiv($i, $squadSize);
            $squad = $squadByIdx[$relayIdx];

            $user = User::whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'])])->first();
            if (! $user) {
                $slug = Str::slug($row['name'], '.') ?: 'shooter';
                $email = "rf.{$slug}@import.invalid";
                if (User::where('email', $email)->exists()) {
                    $email = 'rf.'.$slug.'.'.substr(md5($row['name'].$row['pos']), 0, 6).'@import.invalid';
                }
                $user = User::create([
                    'name' => $row['name'],
                    'email' => $email,
                    'password' => bcrypt(Str::random(32)),
                ]);
                $stats['users_created']++;
            } else {
                $stats['users_existing']++;
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
            if (empty($reg->caliber)) {
                $reg->caliber = $row['cartridge'];
                $reg->save();
            }

            $shooter = Shooter::create([
                'squad_id' => $squad->id,
                'name' => $row['name'].' — '.$row['cartridge'],
                'user_id' => $user->id,
                'sort_order' => $row['pos'],
                'status' => 'active',
            ]);
            $stats['shooters']++;

            foreach ($row['pattern'] as $bankIdx => $bankCells) {
                foreach ($bankCells as $gongIdx => $isHit) {
                    Score::create([
                        'shooter_id' => $shooter->id,
                        'gong_id' => $gongs[$bankIdx][$gongIdx]->id,
                        'is_hit' => (bool) $isHit,
                        'device_id' => 'rf-import',
                        'recorded_at' => $now,
                        'synced_at' => $now,
                    ]);
                    $stats['scores']++;
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Wrote %d shooters (%d new users, %d reused) and %d score rows.',
            $stats['shooters'], $stats['users_created'], $stats['users_existing'], $stats['scores']
        ));
    }

    /** @return array<int, Squad> indexed 0..relayCount-1 */
    private function ensureRelays(ShootingMatch $match, int $shooterCount, int $squadSize): array
    {
        $relayCount = (int) ceil(max(1, $shooterCount) / $squadSize);
        $relays = [];
        for ($i = 0; $i < $relayCount; $i++) {
            $name = 'Relay '.($i + 1);
            $squad = Squad::firstOrCreate(
                ['match_id' => $match->id, 'name' => $name],
                ['sort_order' => $i + 1, 'max_capacity' => $squadSize]
            );
            $squad->fill(['sort_order' => $i + 1, 'max_capacity' => $squadSize])->save();
            $relays[$i] = $squad;
        }
        return $relays;
    }
}
