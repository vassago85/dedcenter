<?php

namespace App\Console\Commands;

use App\Enums\PrsShotResult;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\TargetSet;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fill missing PRS stage results for a match from a PractiScore export.
 *
 * Use case: a PRS match was partially scored on DeadCenter (e.g. some squads
 * went through the live-scoring app, others were scored on PractiScore and
 * never got into DC). This command imports the PractiScore-only data into
 * DC without touching anything that was already scored on DC.
 *
 * Strict-by-default merge policy:
 *   - If a `prs_stage_results` row exists with hits>0 OR misses>0, it is
 *     LEFT ALONE (live-scored data wins, period).
 *   - Otherwise the row is created (or its zero-value placeholder updated)
 *     using PractiScore stage points as the hit count.
 *
 * Per-shot data:
 *   PractiScore exports give stage totals only — no per-shot detail. To make
 *   the Full Match Report heatmap render meaningfully, we synthesise shot
 *   rows in `prs_shot_scores`: shots 1..hits = Hit, hits+1..(hits+misses) =
 *   Miss, the rest (filling to DC's gong count) = NotTaken. These are
 *   marked-up totals, not real per-shot evidence — by design.
 *
 * Stage 1 times:
 *   PractiScore exports occasionally save `1:05` (mm:ss) as `1.05` (a float).
 *   Any time value < 10 is treated as the stage cap (105s) — confirmed
 *   correct by the user for this match's tiebreaker stage.
 *
 * Usage:
 *   php artisan prs:fill-practiscore 22 database/data/practiscore/match-22-2026-05-02.txt --dry-run
 *   php artisan prs:fill-practiscore 22 database/data/practiscore/match-22-2026-05-02.txt
 *
 * The `--dry-run` flag prints the full plan (per-shooter per-stage action)
 * without touching the database. Always run dry-run first on a real match.
 */
class FillPrsGapsFromPractiscore extends Command
{
    protected $signature = 'prs:fill-practiscore
        {match : DC match ID to fill}
        {file : Path to PractiScore paste text file (relative to base path or absolute)}
        {--dry-run : Preview changes without writing to the DB}';

    protected $description = 'Fill missing PRS stage results for a match from a PractiScore paste, preserving any data already scored on DeadCenter.';

    /**
     * Stage-1 times like 1.03 / 1.05 are PractiScore's mm:ss string
     * accidentally cast to a float. Anything below this threshold gets
     * normalised to the stage cap.
     */
    private const STAGE1_TIME_CAP_THRESHOLD = 10.0;
    private const STAGE1_TIME_CAP_SECONDS = 105.0;

    public function handle(): int
    {
        $matchId = (int) $this->argument('match');
        $filePath = $this->resolveFilePath((string) $this->argument('file'));
        if ($filePath === null) {
            return self::FAILURE;
        }
        $dryRun = (bool) $this->option('dry-run');

        $match = ShootingMatch::find($matchId);
        if (! $match) {
            $this->error("Match {$matchId} not found.");
            return self::FAILURE;
        }
        if (! $match->isPrs()) {
            $this->error("Match {$matchId} is not a PRS match (scoring_type={$match->scoring_type}).");
            return self::FAILURE;
        }

        $stages = $match->targetSets()
            ->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();
        $stagesByOrder = $stages->keyBy('sort_order');

        if ($stages->isEmpty()) {
            $this->error("Match {$matchId} has no target sets configured.");
            return self::FAILURE;
        }

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.*', 'squads.name as squad_name')
            ->get();
        $shootersByName = $this->indexShootersByNormalizedName($shooters);

        $parsed = $this->parsePaste(file_get_contents($filePath));
        if (empty($parsed)) {
            $this->error("Could not parse any stage data from {$filePath}.");
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Match %d: %s — %d stages, %d shooters in DC, %d stages parsed from PractiScore.',
            $match->id,
            $match->name,
            $stages->count(),
            $shooters->count(),
            count($parsed),
        ));
        $this->line($dryRun ? '** DRY RUN — no changes will be written. **' : '** LIVE RUN — changes will be written. **');
        $this->newLine();

        $summary = ['inserted' => 0, 'updated' => 0, 'skipped_existing' => 0, 'unmatched' => []];
        $tableRows = [];

        foreach ($parsed as $stageNum => $stageData) {
            $stage = $stagesByOrder->get($stageNum);
            if (! $stage) {
                $this->warn("PractiScore Stage {$stageNum} has no matching target_set in DC (skipped {$stageData['rows']->count()} rows).");
                continue;
            }

            $dcShotCount = $stage->gongs->count();
            $psStageMax = (int) $stageData['rows']->pluck('pts')->max();

            foreach ($stageData['rows'] as $row) {
                $shooter = $this->resolveShooter($row['name'], $shootersByName);
                if (! $shooter) {
                    $summary['unmatched'][] = "S{$stageNum} · {$row['name']}";
                    continue;
                }

                $existing = PrsStageResult::where('match_id', $match->id)
                    ->where('stage_id', $stage->id)
                    ->where('shooter_id', $shooter->id)
                    ->first();

                if ($existing && ($existing->hits > 0 || $existing->misses > 0)) {
                    $summary['skipped_existing']++;
                    continue;
                }

                $hits = (int) min($row['pts'], $dcShotCount);
                $misses = (int) max(0, min($psStageMax, $dcShotCount) - $hits);
                $notTaken = (int) max(0, $dcShotCount - $hits - $misses);
                $time = isset($row['time']) ? $this->normaliseStage1Time((float) $row['time']) : null;

                $action = $existing ? 'UPDATE' : 'INSERT';
                $tableRows[] = [
                    "S{$stageNum}",
                    Str::limit($shooter->name, 28),
                    $shooter->squad_name,
                    "{$hits}/{$misses}/{$notTaken}",
                    $time !== null ? number_format($time, 2).'s' : '—',
                    $action,
                ];

                if ($action === 'INSERT') {
                    $summary['inserted']++;
                } else {
                    $summary['updated']++;
                }

                if ($dryRun) {
                    continue;
                }

                $this->writeStageResult(
                    $match->id, $stage, $shooter->id,
                    $hits, $misses, $notTaken, $time, $existing,
                );
            }
        }

        if (! empty($tableRows)) {
            $this->table(
                ['Stage', 'Shooter', 'Squad', 'H/M/NT', 'Time', 'Action'],
                $tableRows,
            );
        } else {
            $this->line('No gaps to fill — every PractiScore row already has scored data in DC.');
        }

        $this->newLine();
        $this->info(sprintf(
            'Summary: %d inserted · %d updated · %d skipped (existing scored data preserved) · %d unmatched names',
            $summary['inserted'], $summary['updated'], $summary['skipped_existing'], count($summary['unmatched']),
        ));

        if (! empty($summary['unmatched'])) {
            $this->newLine();
            $this->warn('Unmatched PractiScore names (no DC shooter matched):');
            foreach (array_unique($summary['unmatched']) as $u) {
                $this->line('  · '.$u);
            }
        }

        return self::SUCCESS;
    }

    private function resolveFilePath(string $path): ?string
    {
        foreach ([$path, base_path($path)] as $candidate) {
            if (is_readable($candidate) && is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }
        $this->error("Paste file not found: {$path}");
        return null;
    }

    /**
     * Parse the PractiScore tab-separated paste into [stageNum => ['has_time' => bool, 'rows' => Collection]].
     *
     * Each section starts with `... Stage N ... - YYYY-MM-DD` and ends at
     * the next stage header or EOF. The header row is auto-detected by the
     * presence of a `Time` column (stage 1 is the only timed stage in this
     * match's format).
     */
    private function parsePaste(string $text): array
    {
        $sections = preg_split('/^.*?Stage\s+(\d+).*?-\s+\d{4}-\d{2}-\d{2}\s*$/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $parsed = [];
        for ($i = 0; $i < count($sections); $i += 2) {
            // Sections come back as [pre-header-text, stageNum, body, stageNum, body, ...]
            // After the first split chunk (preamble) we land on stageNum/body pairs.
            if (! ctype_digit((string) trim($sections[$i]))) {
                continue;
            }
            $stageNum = (int) trim($sections[$i]);
            $body = $sections[$i + 1] ?? '';

            $rows = collect();
            $hasTime = false;
            foreach (preg_split('/\r?\n/', $body) as $line) {
                $line = rtrim($line);
                if ($line === '') {
                    continue;
                }
                $cols = explode("\t", $line);
                if (count($cols) < 6) {
                    continue;
                }

                // Header row: detect once and remember whether Time column exists.
                if (strcasecmp(trim($cols[0]), 'Place') === 0) {
                    $hasTime = in_array('Time', array_map('trim', $cols), true);
                    continue;
                }

                if (! ctype_digit(trim($cols[0]))) {
                    continue;
                }

                // Layout (with Time):    Place Name No. Class Div Time StagePts Stage%
                // Layout (without Time): Place Name No. Class Div StagePts Stage%
                if ($hasTime && count($cols) < 8) {
                    continue;
                }
                if (! $hasTime && count($cols) < 7) {
                    continue;
                }

                $name = trim($cols[1]);
                $pts = (float) trim($cols[$hasTime ? 6 : 5]);
                $time = $hasTime ? (float) trim($cols[5]) : null;

                $rows->push([
                    'name' => $name,
                    'pts' => $pts,
                    'time' => $time,
                ]);
            }

            if ($rows->isNotEmpty()) {
                $parsed[$stageNum] = ['has_time' => $hasTime, 'rows' => $rows];
            }
        }

        ksort($parsed);
        return $parsed;
    }

    /**
     * Build a `[normalised_name => Shooter[]]` lookup so PractiScore name
     * variants can be resolved in O(1). Names are normalised to lowercase,
     * "Last, First" → "First Last", whitespace collapsed.
     */
    private function indexShootersByNormalizedName(Collection $shooters): array
    {
        $map = [];
        foreach ($shooters as $s) {
            $key = $this->normaliseName($s->name);
            $map[$key][] = $s;
        }
        return $map;
    }

    /**
     * Resolve a PractiScore name to a single DC shooter, or null if no
     * unambiguous match. Tries exact normalised match first, then falls
     * back to "tokens-equal-as-set" for cases like "wyk, Francois Van"
     * vs "Francois Van Wyk" (PractiScore mangles compound surnames).
     */
    private function resolveShooter(string $psName, array $byName): ?Shooter
    {
        $key = $this->normaliseName($psName);
        if (isset($byName[$key])) {
            $matches = $byName[$key];
            return count($matches) === 1 ? $matches[0] : null;
        }

        // Fallback: token-set equality (covers PractiScore mangling compound surnames).
        $psTokens = collect(explode(' ', $key))->filter()->sort()->values()->all();
        foreach ($byName as $candidateKey => $candidates) {
            $candidateTokens = collect(explode(' ', $candidateKey))->filter()->sort()->values()->all();
            if ($psTokens === $candidateTokens && count($candidates) === 1) {
                return $candidates[0];
            }
        }

        return null;
    }

    private function normaliseName(string $name): string
    {
        $name = trim($name);
        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));
            $name = "{$first} {$last}";
        }
        $name = preg_replace('/\s+/', ' ', $name);
        return Str::lower($name);
    }

    private function normaliseStage1Time(float $time): float
    {
        return $time < self::STAGE1_TIME_CAP_THRESHOLD ? self::STAGE1_TIME_CAP_SECONDS : $time;
    }

    /**
     * Persist the stage result + synthesised per-shot rows in one
     * transaction. Per-shot rows are wiped first (only safe because we
     * already verified the existing aggregate row was empty/missing — we
     * never destroy live-scored data).
     */
    private function writeStageResult(
        int $matchId,
        TargetSet $stage,
        int $shooterId,
        int $hits,
        int $misses,
        int $notTaken,
        ?float $time,
        ?PrsStageResult $existing,
    ): void {
        DB::transaction(function () use ($matchId, $stage, $shooterId, $hits, $misses, $notTaken, $time, $existing) {
            $payload = [
                'hits' => $hits,
                'misses' => $misses,
                'not_taken' => $notTaken,
                'official_time_seconds' => $time,
                'completed_at' => $existing?->completed_at ?? now(),
            ];

            if ($existing) {
                $existing->update($payload);
            } else {
                PrsStageResult::create($payload + [
                    'match_id' => $matchId,
                    'stage_id' => $stage->id,
                    'shooter_id' => $shooterId,
                    'raw_time_seconds' => $time,
                ]);
            }

            // Wipe any previous synthetic shots for this (shooter, stage)
            // before regenerating — `prs_shot_scores` has no unique index
            // on (shooter, stage, shot_number) so without the wipe we'd
            // accumulate duplicates on re-runs.
            PrsShotScore::where('match_id', $matchId)
                ->where('stage_id', $stage->id)
                ->where('shooter_id', $shooterId)
                ->delete();

            $shotNumber = 1;
            for ($i = 0; $i < $hits; $i++, $shotNumber++) {
                PrsShotScore::create($this->shotPayload($matchId, $stage->id, $shooterId, $shotNumber, PrsShotResult::Hit));
            }
            for ($i = 0; $i < $misses; $i++, $shotNumber++) {
                PrsShotScore::create($this->shotPayload($matchId, $stage->id, $shooterId, $shotNumber, PrsShotResult::Miss));
            }
            for ($i = 0; $i < $notTaken; $i++, $shotNumber++) {
                PrsShotScore::create($this->shotPayload($matchId, $stage->id, $shooterId, $shotNumber, PrsShotResult::NotTaken));
            }
        });
    }

    private function shotPayload(int $matchId, int $stageId, int $shooterId, int $shotNumber, PrsShotResult $result): array
    {
        return [
            'match_id' => $matchId,
            'stage_id' => $stageId,
            'shooter_id' => $shooterId,
            'shot_number' => $shotNumber,
            'result' => $result,
            'device_id' => 'practiscore-import',
            'recorded_at' => now(),
        ];
    }
}
