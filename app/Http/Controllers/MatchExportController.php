<?php

namespace App\Http\Controllers;

use App\Enums\PlacementKey;
use App\Models\Organization;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use App\Models\UserAchievement;
use App\Services\MatchReportService;
use App\Services\MatchStandingsService;
use App\Services\PdfDocumentRenderer;
use App\Services\RoyalFlushHighlightsService;
use App\Services\Scoring\ELRScoringService;
use App\Services\SponsorPlacementResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatchExportController extends Controller
{
    /**
     * $organization is only injected when the request comes through the
     * org-scoped route group (prefix: org/{organization}/...). When the
     * platform-admin group calls the same method there is no such route
     * parameter, so Laravel passes null. The sanity check prevents URL
     * tampering (e.g. mixing an org slug the user CAN admin with a match
     * that belongs to a DIFFERENT org).
     */
    public function standings(?Organization $organization, ShootingMatch $match): StreamedResponse
    {
        $this->ensureOrgMatch($organization, $match);
        $this->authorizeExport($match);
        $slug = Str::slug($match->name);

        if ($match->isElr()) {
            return $this->streamCsv("{$slug}-standings.csv", fn ($out) => $this->elrStandings($match, $out));
        }

        if ($match->isPrs()) {
            return $this->streamCsv("{$slug}-standings.csv", fn ($out) => $this->prsStandings($match, $out));
        }

        return $this->streamCsv("{$slug}-standings.csv", fn ($out) => $this->standardStandings($match, $out));
    }

    public function detailed(?Organization $organization, ShootingMatch $match): StreamedResponse
    {
        $this->ensureOrgMatch($organization, $match);
        $this->authorizeExport($match);
        $slug = Str::slug($match->name);

        if ($match->isElr()) {
            return $this->streamCsv("{$slug}-detailed.csv", fn ($out) => $this->elrDetailed($match, $out));
        }

        if ($match->isPrs()) {
            return $this->streamCsv("{$slug}-detailed.csv", fn ($out) => $this->prsDetailed($match, $out));
        }

        return $this->streamCsv("{$slug}-detailed.csv", fn ($out) => $this->standardDetailed($match, $out));
    }

    /**
     * Royal Flush shots CSV: Name, Caliber, Shot 1 … Shot N (1 = hit, 0 = miss or unscored).
     * Shot order is target_sets sort_order ascending × gongs number ascending.
     */
    public function royalFlushShots(ShootingMatch $match): StreamedResponse
    {
        $this->authorizeExport($match);
        $slug = Str::slug($match->name);

        return $this->streamCsv("{$slug}-rf-shots.csv", fn ($out) => $this->rfShots($match, $out));
    }

    // ── Standard ────────────────────────────────────────────────

    private function standardStandings(ShootingMatch $match, $out): void
    {
        $divisionNames = $this->divisionLookup($match);

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('scores', 'shooters.id', '=', 'scores.shooter_id')
            ->leftJoin('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->leftJoin('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id as shooter_id', 'shooters.name', 'squads.name as squad')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 1 THEN 1 END) as agg_hits')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 0 THEN 1 END) as agg_misses')
            ->selectRaw('COALESCE(SUM(CASE WHEN scores.is_hit = 1 THEN COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier ELSE 0 END), 0) as agg_total')
            ->groupBy('shooters.id', 'shooters.name', 'squads.name')
            ->orderByDesc('agg_total')
            ->get();

        fputcsv($out, ['Rank', 'Name', 'Squad', 'Division', 'Hits', 'Misses', 'Total Score']);

        foreach ($shooters->values() as $i => $s) {
            fputcsv($out, [
                $i + 1,
                $s->name,
                $s->squad,
                $divisionNames[(int) $s->shooter_id] ?? '',
                (int) $s->agg_hits,
                (int) $s->agg_misses,
                round((float) $s->agg_total, 2),
            ]);
        }
    }

    private function standardDetailed(ShootingMatch $match, $out): void
    {
        $divisionNames = $this->divisionLookup($match);

        $targetSets = $match->targetSets()
            ->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();

        $allGongs = $targetSets->flatMap->gongs;

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id', 'shooters.name', 'squads.name as squad_name')
            ->get();

        $allScores = Score::query()
            ->whereIn('shooter_id', $shooters->pluck('id'))
            ->whereIn('gong_id', $allGongs->pluck('id'))
            ->get()
            ->groupBy('shooter_id');

        $header = ['Rank', 'Name', 'Squad', 'Division'];
        foreach ($targetSets as $ts) {
            $label = $ts->label ?: "{$ts->distance_meters}m";
            foreach ($ts->gongs as $g) {
                $header[] = "{$label} G{$g->number}";
            }
            $header[] = "{$label} Subtotal";
        }
        $header = array_merge($header, ['Hits', 'Misses', 'Total Score']);
        fputcsv($out, $header);

        $rows = $shooters->map(function ($shooter) use ($allScores, $targetSets, $divisionNames) {
            $scores = $allScores->get($shooter->id, collect())->keyBy('gong_id');
            $totalScore = 0;
            $totalHits = 0;
            $totalMisses = 0;
            $cells = [];

            foreach ($targetSets as $ts) {
                $distMult = (float) ($ts->distance_multiplier ?? 1);
                $distSubtotal = 0;

                foreach ($ts->gongs as $g) {
                    $score = $scores->get($g->id);
                    if (! $score) {
                        $cells[] = '-';
                    } elseif ($score->is_hit) {
                        $cells[] = 'H';
                        $points = round($distMult * $g->multiplier, 2);
                        $distSubtotal += $points;
                        $totalScore += $points;
                        $totalHits++;
                    } else {
                        $cells[] = 'M';
                        $totalMisses++;
                    }
                }
                $cells[] = round($distSubtotal, 2);
            }

            return [
                'name' => $shooter->name,
                'squad' => $shooter->squad_name,
                'division' => $divisionNames[$shooter->id] ?? '',
                'cells' => $cells,
                'hits' => $totalHits,
                'misses' => $totalMisses,
                'total' => round($totalScore, 2),
            ];
        })->sortByDesc('total')->values();

        foreach ($rows as $i => $row) {
            fputcsv($out, array_merge(
                [$i + 1, $row['name'], $row['squad'], $row['division']],
                $row['cells'],
                [$row['hits'], $row['misses'], $row['total']],
            ));
        }
    }

    // ── Royal Flush shots (1/0) ────────────────────────────────

    /**
     * CSV: Name, Caliber, Shot 1 … Shot N.
     * Canonical shot order = target_sets.sort_order ASC × gongs.number ASC.
     * 1 = hit, 0 = miss or unscored. Shooter caliber pulled from match_registrations.
     */
    private function rfShots(ShootingMatch $match, $out): void
    {
        $targetSets = $match->targetSets()
            ->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();

        $orderedGongIds = $targetSets->flatMap->gongs->pluck('id')->values();
        $shotCount = $orderedGongIds->count();

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('match_registrations', function ($j) use ($match) {
                $j->on('match_registrations.user_id', '=', 'shooters.user_id')
                    ->where('match_registrations.match_id', '=', $match->id);
            })
            ->where('squads.match_id', $match->id)
            ->orderBy('squads.sort_order')
            ->orderBy('shooters.sort_order')
            ->select(
                'shooters.id',
                'shooters.name',
                'shooters.user_id',
                'match_registrations.caliber as reg_caliber',
            )
            ->get();

        $scoresByShooter = Score::query()
            ->whereIn('shooter_id', $shooters->pluck('id'))
            ->whereIn('gong_id', $orderedGongIds)
            ->get(['shooter_id', 'gong_id', 'is_hit'])
            ->groupBy('shooter_id');

        $header = ['Name', 'Caliber'];
        for ($i = 1; $i <= $shotCount; $i++) {
            $header[] = "Shot {$i}";
        }
        fputcsv($out, $header, ',', '"', '\\');

        foreach ($shooters as $shooter) {
            // Prefer stored registration caliber; fall back to suffix "Name — Caliber" from shooter name.
            $caliber = $shooter->reg_caliber;
            $displayName = $shooter->name;
            if (str_contains($displayName, ' — ')) {
                [$displayName, $suffix] = array_pad(explode(' — ', $displayName, 2), 2, '');
                if (empty($caliber)) {
                    $caliber = $suffix;
                }
            }

            $scores = $scoresByShooter->get($shooter->id, collect())->keyBy('gong_id');
            $row = [$displayName, $caliber ?? ''];
            foreach ($orderedGongIds as $gid) {
                $score = $scores->get($gid);
                $row[] = ($score && $score->is_hit) ? 1 : 0;
            }
            fputcsv($out, $row, ',', '"', '\\');
        }
    }

    // ── PRS ─────────────────────────────────────────────────────

    private function prsStandings(ShootingMatch $match, $out): void
    {
        $divisionNames = $this->divisionLookup($match);
        $targetSets = $match->targetSets()->get();
        $targetSetIds = $targetSets->pluck('id');
        $totalTargets = DB::table('gongs')->whereIn('target_set_id', $targetSetIds)->count();

        $perShooter = $this->prsAggregatesForCsv($match, $targetSets);

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id as shooter_id', 'shooters.name', 'squads.name as squad')
            ->get();

        $entries = $shooters->map(function ($s) use ($perShooter, $divisionNames, $totalTargets) {
            $sid = (int) $s->shooter_id;
            $agg = $perShooter[$sid] ?? null;

            $hits = $agg['hits'] ?? 0;
            $misses = $agg['misses'] ?? 0;

            return [
                'sid' => $sid, 'name' => $s->name, 'squad' => $s->squad,
                'division' => $divisionNames[$sid] ?? '',
                'hits' => $hits, 'misses' => $misses,
                'not_taken' => max(0, $totalTargets - $hits - $misses),
                'total_time' => round($agg['total_time'] ?? 0.0, 2),
                'tb_hits' => $agg['tb_hits'] ?? 0,
                'tb_time' => round($agg['tb_time'] ?? 0.0, 2),
            ];
        })->sort(function ($a, $b) {
            if ($a['hits'] !== $b['hits']) {
                return $b['hits'] <=> $a['hits'];
            }
            if ($a['tb_hits'] !== $b['tb_hits']) {
                return $b['tb_hits'] <=> $a['tb_hits'];
            }
            if ($a['tb_time'] !== $b['tb_time']) {
                return $a['tb_time'] <=> $b['tb_time'];
            }

            return $a['total_time'] <=> $b['total_time'];
        })->values();

        fputcsv($out, ['Rank', 'Name', 'Squad', 'Division', 'Hits', 'Misses', 'Not Taken', 'Total Time', 'TB Hits', 'TB Time']);

        foreach ($entries as $i => $e) {
            fputcsv($out, [$i + 1, $e['name'], $e['squad'], $e['division'], $e['hits'], $e['misses'], $e['not_taken'], $e['total_time'], $e['tb_hits'], $e['tb_time']]);
        }
    }

    private function prsDetailed(ShootingMatch $match, $out): void
    {
        $divisionNames = $this->divisionLookup($match);
        $targetSets = $match->targetSets()->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])->get();
        $targetSetIds = $targetSets->pluck('id');
        $totalTargets = DB::table('gongs')->whereIn('target_set_id', $targetSetIds)->count();

        $perShooter = $this->prsAggregatesForCsv($match, $targetSets);
        $shotMap = $this->prsShotMapForCsv($match, $targetSets);

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id', 'shooters.name', 'squads.name as squad_name')
            ->get();

        $header = ['Rank', 'Name', 'Squad', 'Division'];
        foreach ($targetSets as $ts) {
            $label = $ts->label ?: "Stage {$ts->sort_order}";
            foreach ($ts->gongs as $g) {
                $header[] = "{$label} G{$g->number}";
            }
            $header[] = "{$label} Time";
        }
        $header = array_merge($header, ['Hits', 'Misses', 'Not Taken', 'Total Time', 'TB Hits', 'TB Time']);
        fputcsv($out, $header);

        $rows = $shooters->map(function ($shooter) use ($shotMap, $perShooter, $targetSets, $divisionNames, $totalTargets) {
            $sid = (int) $shooter->id;
            $shotsForShooter = $shotMap[$sid] ?? [];
            $agg = $perShooter[$sid] ?? [];
            $totalHits = $agg['hits'] ?? 0;
            $totalMisses = $agg['misses'] ?? 0;
            $cells = [];
            $totalTime = 0.0;

            foreach ($targetSets as $ts) {
                foreach ($ts->gongs as $g) {
                    $state = $shotsForShooter[$ts->id][$g->id] ?? 'none';
                    $cells[] = match ($state) {
                        'hit' => 'H',
                        'miss' => 'M',
                        default => '-',
                    };
                }
                $stageTime = $agg['stage_times'][$ts->id] ?? 0.0;
                $totalTime += $stageTime;
                $cells[] = round((float) $stageTime, 2);
            }

            return [
                'name' => $shooter->name, 'squad' => $shooter->squad_name,
                'division' => $divisionNames[$sid] ?? '',
                'cells' => $cells,
                'hits' => $totalHits, 'misses' => $totalMisses,
                'not_taken' => max(0, $totalTargets - $totalHits - $totalMisses),
                'total_time' => round($totalTime, 2),
                'tb_hits' => $agg['tb_hits'] ?? 0,
                'tb_time' => round($agg['tb_time'] ?? 0.0, 2),
            ];
        })->sort(function ($a, $b) {
            if ($a['hits'] !== $b['hits']) {
                return $b['hits'] <=> $a['hits'];
            }
            if ($a['tb_hits'] !== $b['tb_hits']) {
                return $b['tb_hits'] <=> $a['tb_hits'];
            }
            if ($a['tb_time'] !== $b['tb_time']) {
                return $a['tb_time'] <=> $b['tb_time'];
            }

            return $a['total_time'] <=> $b['total_time'];
        })->values();

        foreach ($rows as $i => $row) {
            fputcsv($out, array_merge(
                [$i + 1, $row['name'], $row['squad'], $row['division']],
                $row['cells'],
                [$row['hits'], $row['misses'], $row['not_taken'], $row['total_time'], $row['tb_hits'], $row['tb_time']],
            ));
        }
    }

    /**
     * Aggregate PRS data per shooter for CSV exports.
     *
     * Returns an array keyed by shooter_id with: hits, misses, total_time,
     * tb_hits, tb_time, and stage_times (keyed by target_set_id).
     *
     * Sources data from `prs_stage_results` when populated (current PRS
     * scoring app), otherwise falls back to `scores` + `stage_times` so
     * legacy PRS matches scored gong-by-gong before the dedicated PRS
     * tables existed still export correctly.
     *
     * @return array<int, array{hits:int, misses:int, total_time:float, tb_hits:int, tb_time:float, stage_times:array<int, float>}>
     */
    private function prsAggregatesForCsv(ShootingMatch $match, \Illuminate\Support\Collection $targetSets): array
    {
        $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);
        $tbStageId = $tiebreakerStage?->id;

        $usePrsTables = PrsStageResult::where('match_id', $match->id)->exists();

        if ($usePrsTables) {
            $results = PrsStageResult::where('match_id', $match->id)
                ->get()
                ->groupBy('shooter_id');

            $perShooter = [];
            foreach ($results as $sid => $stages) {
                $hits = (int) $stages->sum('hits');
                $misses = (int) $stages->sum('misses');
                $totalTime = (float) $stages->whereNotNull('official_time_seconds')
                    ->sum(fn ($r) => (float) $r->official_time_seconds);

                $stageTimes = [];
                foreach ($stages as $r) {
                    $stageTimes[(int) $r->stage_id] = $r->official_time_seconds !== null
                        ? (float) $r->official_time_seconds
                        : 0.0;
                }

                $tbHits = 0;
                $tbTime = 0.0;
                if ($tbStageId !== null) {
                    $tbResult = $stages->firstWhere('stage_id', $tbStageId);
                    if ($tbResult) {
                        $tbHits = (int) $tbResult->hits;
                        $tbTime = $tbResult->official_time_seconds !== null
                            ? (float) $tbResult->official_time_seconds
                            : 0.0;
                    }
                }

                $perShooter[(int) $sid] = [
                    'hits' => $hits,
                    'misses' => $misses,
                    'total_time' => $totalTime,
                    'tb_hits' => $tbHits,
                    'tb_time' => $tbTime,
                    'stage_times' => $stageTimes,
                ];
            }

            return $perShooter;
        }

        // Legacy PRS — score table + stage_times.
        $targetSetIds = $targetSets->pluck('id');
        $allGongIds = DB::table('gongs')->whereIn('target_set_id', $targetSetIds)->pluck('id');

        $shooterAgg = Score::query()
            ->whereIn('gong_id', $allGongIds)
            ->select('shooter_id', DB::raw('COUNT(CASE WHEN is_hit = 1 THEN 1 END) as agg_hits'))
            ->selectRaw('COUNT(CASE WHEN is_hit = 0 THEN 1 END) as agg_misses')
            ->groupBy('shooter_id')
            ->get();

        $stageTimesRaw = StageTime::whereIn('target_set_id', $targetSetIds)->get();
        $totalTimeByShooter = $stageTimesRaw->groupBy('shooter_id')
            ->map(fn ($t) => (float) $t->sum('time_seconds'))->toArray();
        $stageTimesByShooter = [];
        foreach ($stageTimesRaw as $st) {
            $stageTimesByShooter[(int) $st->shooter_id][(int) $st->target_set_id] = (float) $st->time_seconds;
        }

        $tbHitsMap = [];
        $tbTimesMap = [];
        if ($tbStageId !== null) {
            $tbGongIds = DB::table('gongs')->where('target_set_id', $tbStageId)->pluck('id');
            $tbHitsMap = Score::whereIn('gong_id', $tbGongIds)->where('is_hit', true)
                ->select('shooter_id', DB::raw('COUNT(*) as c'))->groupBy('shooter_id')
                ->pluck('c', 'shooter_id')->map(fn ($v) => (int) $v)->toArray();
            $tbTimesMap = StageTime::where('target_set_id', $tbStageId)
                ->pluck('time_seconds', 'shooter_id')->map(fn ($v) => (float) $v)->toArray();
        }

        $perShooter = [];
        foreach ($shooterAgg as $row) {
            $sid = (int) $row->shooter_id;
            $perShooter[$sid] = [
                'hits' => (int) $row->agg_hits,
                'misses' => (int) $row->agg_misses,
                'total_time' => $totalTimeByShooter[$sid] ?? 0.0,
                'tb_hits' => $tbHitsMap[$sid] ?? 0,
                'tb_time' => $tbTimesMap[$sid] ?? 0.0,
                'stage_times' => $stageTimesByShooter[$sid] ?? [],
            ];
        }

        return $perShooter;
    }

    /**
     * Per-(shooter, stage, gong) hit/miss/none lookup used by the PRS
     * detailed CSV.
     *
     * Returns: $map[shooter_id][stage_id][gong_id] = 'hit' | 'miss' | 'none'
     *
     * For modern PRS matches we read `prs_shot_scores` and map shot_number
     * to gong_id by ordering each stage's gongs by `number`. Legacy matches
     * (pre-PRS-tables) fall back to the `scores` table keyed directly by
     * gong_id. `not_taken` shots collapse to 'none' so the CSV cell shows
     * '-' rather than misrepresenting a skipped shot as a miss.
     */
    private function prsShotMapForCsv(ShootingMatch $match, \Illuminate\Support\Collection $targetSets): array
    {
        $hasPrsShots = PrsShotScore::where('match_id', $match->id)->exists();

        if ($hasPrsShots) {
            $shotToGong = [];
            foreach ($targetSets as $ts) {
                $i = 1;
                foreach ($ts->gongs as $g) {
                    $shotToGong[(int) $ts->id][$i] = (int) $g->id;
                    $i++;
                }
            }

            $shots = PrsShotScore::where('match_id', $match->id)
                ->orderBy('shot_number')
                ->get(['shooter_id', 'stage_id', 'shot_number', 'result']);

            $map = [];
            foreach ($shots as $s) {
                $stageId = (int) $s->stage_id;
                $gongId = $shotToGong[$stageId][(int) $s->shot_number] ?? null;
                if ($gongId === null) {
                    continue;
                }
                $result = $s->result instanceof \BackedEnum ? $s->result->value : (string) $s->result;
                $map[(int) $s->shooter_id][$stageId][$gongId] = match ($result) {
                    'hit' => 'hit',
                    'miss' => 'miss',
                    default => 'none',
                };
            }

            return $map;
        }

        // Legacy: per-gong scores in the `scores` table.
        $allGongs = $targetSets->flatMap->gongs;
        $gongToStage = [];
        foreach ($targetSets as $ts) {
            foreach ($ts->gongs as $g) {
                $gongToStage[(int) $g->id] = (int) $ts->id;
            }
        }

        $scores = Score::whereIn('gong_id', $allGongs->pluck('id'))->get();

        $map = [];
        foreach ($scores as $s) {
            $stageId = $gongToStage[(int) $s->gong_id] ?? null;
            if ($stageId === null) {
                continue;
            }
            $map[(int) $s->shooter_id][$stageId][(int) $s->gong_id] = $s->is_hit ? 'hit' : 'miss';
        }

        return $map;
    }

    // ── ELR ─────────────────────────────────────────────────────

    private function elrStandings(ShootingMatch $match, $out): void
    {
        $data = (new ELRScoringService)->calculateStandings($match);

        fputcsv($out, ['Rank', 'Name', 'Squad', 'Total Points', 'Total Hits', '1st Round Hits', '2nd Round Hits', 'Furthest Hit (m)']);

        foreach ($data['standings'] as $s) {
            fputcsv($out, [
                $s['rank'], $s['name'], $s['squad_name'],
                $s['total_points'], $s['total_hits'],
                $s['first_round_hits'], $s['second_round_hits'],
                $s['furthest_hit_m'],
            ]);
        }
    }

    private function elrDetailed(ShootingMatch $match, $out): void
    {
        $data = (new ELRScoringService)->calculateStandings($match);
        $stages = $data['stages'];

        $header = ['Rank', 'Name', 'Squad'];
        foreach ($stages as $stage) {
            foreach ($stage['targets'] as $target) {
                for ($s = 1; $s <= $target['max_shots']; $s++) {
                    $header[] = "{$stage['label']} - {$target['name']} S{$s}";
                }
            }
            $header[] = "{$stage['label']} Points";
        }
        $header = array_merge($header, ['Total Points', 'Total Hits', '1st Round Hits', '2nd Round Hits', 'Furthest Hit (m)']);
        fputcsv($out, $header);

        foreach ($data['standings'] as $entry) {
            $row = [$entry['rank'], $entry['name'], $entry['squad_name']];

            foreach ($stages as $si => $stage) {
                $entryStage = $entry['stages'][$si] ?? null;
                foreach ($stage['targets'] as $ti => $target) {
                    $entryTarget = $entryStage['targets'][$ti] ?? null;
                    $shots = $entryTarget['shots'] ?? [];
                    for ($s = 1; $s <= $target['max_shots']; $s++) {
                        $shot = collect($shots)->firstWhere('shot_number', $s);
                        $row[] = $shot ? ucfirst($shot['result']) : '-';
                    }
                }
                $row[] = $entryStage['points'] ?? 0;
            }

            $row[] = $entry['total_points'];
            $row[] = $entry['total_hits'];
            $row[] = $entry['first_round_hits'];
            $row[] = $entry['second_round_hits'];
            $row[] = $entry['furthest_hit_m'];

            fputcsv($out, $row);
        }
    }

    // ── PDF Exports ──────────────────────────────────────────────

    public function pdfStandings(?Organization $organization, ShootingMatch $match, PdfDocumentRenderer $renderer)
    {
        $this->ensureOrgMatch($organization, $match);
        $this->authorizeExport($match);
        $slug = Str::slug($match->name);
        $data = $this->buildPdfStandingsData($match);

        return $renderer->stream('exports.pdf-standings', $data, "{$slug}-standings.pdf");
    }

    public function pdfDetailed(?Organization $organization, ShootingMatch $match, PdfDocumentRenderer $renderer)
    {
        $this->ensureOrgMatch($organization, $match);
        $this->authorizeExport($match);
        $slug = Str::slug($match->name);
        $data = $this->buildPdfDetailedData($match);

        return $renderer->stream('exports.pdf-detailed', $data, "{$slug}-detailed.pdf");
    }

    /**
     * Full Match Report PDF — all shooters on a tick/cross heatmap, podium,
     * stat cards, branded header. Digital-first: rendered as one tall
     * continuous navy page (@page size: 210mm auto) so there are no
     * awkward page breaks in the viewer and the dark background never has
     * to paginate for print fidelity it won't achieve anyway.
     *
     * Replaces the legacy pdfPostMatchReport and pdfExecutiveSummary entry
     * points (same routes, new output + name).
     */
    public function pdfPostMatchReport(?Organization $organization, ShootingMatch $match, PdfDocumentRenderer $renderer)
    {
        return $this->pdfFullMatchReport($organization, $match, $renderer);
    }

    /**
     * Royal Flush results report — A4 portrait HTML page.
     *
     * Light/magazine aesthetic, every shot rendered as a hit/miss cell grouped
     * by distance with per-distance multipliers shown in column headers.
     * Same data shape as the full match report (re-uses buildExecutiveSummaryData).
     */
    public function royalFlushReport(ShootingMatch $match)
    {
        $this->authorizeExport($match);

        abort_unless(
            $match->royal_flush_enabled,
            404,
            'This report is only available for Royal Flush matches.',
        );

        $data = $this->buildExecutiveSummaryData($match);

        return view('reports.royal-flush', $data + ['match' => $match]);
    }

    /**
     * Full Match Report PDF generation.
     *
     * Passes singlePage=true so Gotenberg/Chromium flatten the entire
     * document to a single tall page. The template's `@page { size: 210mm
     * auto }` rule is a hint — Chromium still paginates to A4 unless the
     * Gotenberg flag overrides it. The shooter report uses the same flag.
     */
    public function pdfFullMatchReport(?Organization $organization, ShootingMatch $match, PdfDocumentRenderer $renderer)
    {
        $this->ensureOrgMatch($organization, $match);
        $this->authorizeExport($match);
        $slug = Str::slug($match->name);
        $data = $this->buildExecutiveSummaryData($match);

        return $renderer->stream(
            'exports.pdf-executive-summary',
            $data,
            "{$slug}-full-match-report.pdf",
            null,
            true,
        );
    }

    /**
     * Full Match Report — HTML view.
     *
     * Same template as the PDF (so content never drifts) but rendered with
     * `$viewMode = 'html'`, which layers in responsive overrides and a
     * sticky Download PDF action bar. Available to anyone authorized to
     * see exports — org/admin routes are middleware-gated, but the method
     * itself also runs authorizeExport() for belt-and-braces.
     */
    public function fullMatchReport(?Organization $organization, ShootingMatch $match)
    {
        $this->ensureOrgMatch($organization, $match);
        $this->authorizeExport($match);
        $data = $this->buildExecutiveSummaryData($match);

        // Same Laravel-injects-an-empty-Organization gotcha as ensureOrgMatch:
        // on the admin route (no `{organization}` segment) `$organization`
        // is a slug-less, unsaved model — truthy but unusable. Treat only a
        // persisted Organization as a real org binding.
        $downloadUrl = ($organization && $organization->exists)
            ? route('org.matches.export.pdf-executive-summary', [$organization, $match])
            : route('admin.matches.export.pdf-executive-summary', $match);

        return view('exports.pdf-executive-summary', $data + [
            'viewMode' => 'html',
            'downloadUrl' => $downloadUrl,
        ]);
    }

    /**
     * Public Full Match Report — HTML view linked from the scoreboard.
     *
     * No authorizeExport(): any scoreboard viewer can read the HTML
     * report for a completed match. The Download PDF button is hidden
     * for unauthenticated viewers; authenticated staff/admins get the
     * right export URL.
     */
    public function publicFullMatchReport(\Illuminate\Http\Request $request, ShootingMatch $match)
    {
        $data = $this->buildExecutiveSummaryData($match);

        $downloadUrl = null;
        $user = $request->user();
        if ($user) {
            if ($user->isAdmin()) {
                $downloadUrl = route('admin.matches.export.pdf-executive-summary', $match);
            } elseif ($match->organization_id && $user->isOrgMatchDirector($match->organization)) {
                $downloadUrl = route('org.matches.export.pdf-executive-summary', [$match->organization, $match]);
            }
        }

        return view('exports.pdf-executive-summary', $data + [
            'viewMode' => 'html',
            'downloadUrl' => $downloadUrl,
        ]);
    }

    /**
     * Backwards-compatible shim — some controllers/services still reference
     * this by its historical name. Delegates to pdfFullMatchReport so the
     * output is always the current template.
     */
    public function pdfExecutiveSummary(?Organization $organization, ShootingMatch $match, PdfDocumentRenderer $renderer)
    {
        return $this->pdfFullMatchReport($organization, $match, $renderer);
    }

    /**
     * Individual shooter report — A4 portrait, styled identically to the
     * in-app/email match report (stat cards, per-stage tick/cross chips,
     * Best & Worst, field comparison, fun facts, badges).
     *
     * Renders `exports.pdf-match-report` using `MatchReportService` so the
     * download, the preview URL, and the emailed attachment all stay
     * visually in lock-step.
     */
    public function pdfShooterReport(?Organization $organization, ShootingMatch $match, Shooter $shooter, PdfDocumentRenderer $renderer, MatchReportService $reportService)
    {
        $this->ensureOrgMatch($organization, $match);
        $this->authorizeExport($match);

        abort_unless(
            $shooter->squad && $shooter->squad->match_id === $match->id,
            404,
            'Shooter does not belong to this match.',
        );

        $slug = Str::slug($match->name.'-'.$shooter->name);
        $report = $reportService->generateReport($match, $shooter);

        return $renderer->stream('exports.pdf-match-report', ['report' => $report], "{$slug}-shooter-report.pdf", null, true);
    }

    /**
     * Member-facing shooter report.
     *
     * Any logged-in user can download the PDF for the match they shot — we
     * resolve their own shooter row (via `shooters.user_id = auth()->id()`)
     * and stream the same per-shooter report the org/admin surfaces use.
     *
     * Unlike pdfShooterReport(), this does NOT call authorizeExport(); the
     * gate is purely "the shooter row is linked to this user". If they claim
     * a result later, the link is updated via ShooterAccountClaim approval
     * and this endpoint starts working for them — no config change needed.
     */
    public function pdfMyShooterReport(ShootingMatch $match, PdfDocumentRenderer $renderer, MatchReportService $reportService)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $shooter = Shooter::query()
            ->whereHas('squad', fn ($q) => $q->where('match_id', $match->id))
            ->where('user_id', $user->id)
            ->first();

        abort_unless(
            $shooter,
            404,
            'We couldn’t find your shooter record for this match. If you shot under a different name, claim that result first.',
        );

        $slug = Str::slug($match->name.'-'.$shooter->name);
        $report = $reportService->generateReport($match, $shooter);

        return $renderer->stream('exports.pdf-match-report', ['report' => $report], "{$slug}-shooter-report.pdf", null, true);
    }

    /**
     * Executive summary data — builds the all-shooters heatmap plus podium and stats.
     *
     * Re-uses buildPostMatchReportData for scoring/standings/distances, then computes:
     *   - a flat heatmap matrix: rows = shooters in rank order, columns = every gong in match order
     *   - stat cards: total shots, total hits, hit rate, avg score, winner, top distance
     *   - per-distance aggregate hit rate (for the stat strip)
     */
    private function buildExecutiveSummaryData(ShootingMatch $match): array
    {
        $base = $this->buildPostMatchReportData($match);

        $distanceTables = $base['distanceTables'];
        $standings = $base['standings'];

        // Ranked competitors only — DQs and no-shows sit below the leaderboard
        // and must NOT be counted toward field averages, hit rate, or stat cards.
        // (No-shows in particular were previously dragging the field average
        // down because they counted as a zero-score competitor.)
        $rankedStandings = collect($standings)->filter(fn ($s) => $s->rank !== null)->values();

        // Build heatmap matrix: one entry per shooter, cells = flat list of hit/miss/none per gong, same column order across all shooters.
        $heatmapColumns = [];
        foreach ($distanceTables as $dt) {
            foreach ($dt['gongs'] as $g) {
                $heatmapColumns[] = [
                    'distance_meters' => $dt['distance_meters'],
                    'distance_label' => $dt['label'] ?? ($dt['distance_meters'].'m'),
                    'distance_multiplier' => $dt['distance_multiplier'],
                    'gong_number' => $g['number'],
                    'gong_multiplier' => $g['multiplier'],
                    'points_per_hit' => $g['points_per_hit'],
                ];
            }
        }

        // Index distance rows by shooter for fast lookup. We key by the
        // distance-table index (not distance_meters) because PRS matches
        // typically have multiple stages at distance_meters = 0 and using
        // the meters value as a key would silently overwrite earlier
        // stages with later ones (the "all stages report 0/0 — 0% hit
        // rate" symptom on completed PRS matches).
        $shooterRows = [];
        foreach ($distanceTables as $dtIdx => $dt) {
            foreach ($dt['rows'] as $row) {
                $shooterRows[$row['name']][$dtIdx] = $row;
            }
        }

        // Relative score denominator = the top ranked competitor's total.
        // Mirrors the public scoreboard API (ScoreboardController) so the
        // report, the live scoreboard, and season standings all speak the
        // same language: winner is always 100, the rest are a rounded
        // integer proportion of that.
        $winnerScoreForRelative = (float) ($rankedStandings->first()->total_score ?? 0);

        $heatmap = [];
        foreach ($standings as $standing) {
            $cells = [];
            foreach ($distanceTables as $dtIdx => $dt) {
                $row = $shooterRows[$standing->name][$dtIdx] ?? null;
                if (! $row) {
                    foreach ($dt['gongs'] as $_) {
                        $cells[] = ['state' => 'none', 'points' => null];
                    }

                    continue;
                }
                foreach ($row['cells'] as $cell) {
                    $cells[] = $cell;
                }
            }

            $heatmap[] = [
                'rank' => $standing->rank,
                'name' => $standing->name,
                'display_name' => Str::before($standing->name, ' — ') ?: $standing->name,
                'caliber' => $this->caliberFromShooterName($standing->name),
                'squad' => $standing->squad,
                'status' => $standing->status,
                'total_score' => $standing->total_score,
                'total_hits' => $standing->hits ?? 0,
                'total_shots' => ($standing->hits ?? 0) + ($standing->misses ?? 0),
                'hit_rate' => (($standing->hits ?? 0) + ($standing->misses ?? 0)) > 0
                    ? round((($standing->hits ?? 0) / (($standing->hits ?? 0) + ($standing->misses ?? 0))) * 100)
                    : 0,
                'relative_score' => $winnerScoreForRelative > 0 && $standing->rank !== null
                    ? (int) round(((float) $standing->total_score / $winnerScoreForRelative) * 100)
                    : null,
                'cells' => $cells,
            ];
        }

        // Stat cards — count shots from ranked competitors only so the field
        // hit rate isn't polluted by no-shows / DQs who were scored as misses.
        $rankedNames = $rankedStandings->pluck('name')->flip();
        $totalShots = 0;
        $totalHits = 0;
        foreach ($heatmap as $row) {
            if (! isset($rankedNames[$row['name']])) {
                continue;
            }
            foreach ($row['cells'] as $c) {
                if ($c['state'] === 'hit' || $c['state'] === 'miss') {
                    $totalShots++;
                    if ($c['state'] === 'hit') {
                        $totalHits++;
                    }
                }
            }
        }
        $hitRate = $totalShots > 0 ? round(($totalHits / $totalShots) * 100) : 0;

        $winner = $rankedStandings->first();
        $runnerUp = $rankedStandings->skip(1)->first();
        $third = $rankedStandings->skip(2)->first();

        $avgScore = $rankedStandings->count() > 0
            ? (int) round($rankedStandings->avg('total_score'))
            : 0;

        // Per-distance hit rate for header ribbon — ranked competitors only.
        $distanceStats = [];
        foreach ($distanceTables as $dt) {
            $shots = 0;
            $hits = 0;
            foreach ($dt['rows'] as $row) {
                if (! isset($rankedNames[$row['name']])) {
                    continue;
                }
                foreach ($row['cells'] as $c) {
                    if ($c['state'] === 'hit' || $c['state'] === 'miss') {
                        $shots++;
                        if ($c['state'] === 'hit') {
                            $hits++;
                        }
                    }
                }
            }
            $distanceStats[] = [
                'distance_meters' => $dt['distance_meters'],
                'label' => $dt['label'] ?? ($dt['distance_meters'].'m'),
                'multiplier' => $dt['distance_multiplier'],
                'gong_count' => count($dt['gongs']),
                'shots' => $shots,
                'hits' => $hits,
                'hit_rate' => $shots > 0 ? round(($hits / $shots) * 100) : 0,
            ];
        }

        // ─── Royal Flushes per distance ───
        // Single-source-of-truth lives in RoyalFlushHighlightsService so the
        // same numbers feed the Match Hub UI panel and this PDF. The service
        // returns empty arrays for non-RF matches and our view layer hides
        // the section when has_any is false.
        $highlights = app(RoyalFlushHighlightsService::class)->build($match);
        $royalFlushesByDistance = $highlights['flushes_by_distance'];
        $royalFlushShootersByDistance = $highlights['shooters_by_distance'];
        $perfectHandShooters = $highlights['perfect_hand_shooters'];

        // ─── Match-wide "cool facts" ───
        // Small set of compact, human-readable highlights. All of these are
        // defensively built: if the underlying data is missing/empty we just
        // drop the fact instead of emitting an empty line. The blade hides
        // the whole section when the array is empty.
        $matchFacts = $this->buildMatchFacts(
            $match,
            $rankedStandings,
            $heatmap,
            $distanceStats,
            $heatmapColumns,
            $royalFlushesByDistance,
            $royalFlushShootersByDistance,
            $perfectHandShooters,
        );

        // PRS-specific Score Sheet grid — built from prs_stage_results +
        // prs_shot_scores so the Full Match Report can render the same
        // per-stage gong-dot layout the on-screen Scoreboard uses,
        // without the standard heatmap's distance-grouped columns and
        // multiplier chrome (PRS scoring is just 1 hit = 1 point, no
        // multipliers anywhere). Only computed when the match actually
        // has PRS data on file; standard / RF matches keep using the
        // existing $heatmap unchanged.
        $prsScoreSheet = null;
        if ($match->isPrs() && PrsStageResult::where('match_id', $match->id)->exists()) {
            $prsScoreSheet = $this->buildPrsScoreSheetData($match, $standings);
        }

        return array_merge($base, [
            'heatmap' => $heatmap,
            'heatmapColumns' => $heatmapColumns,
            'distanceStats' => $distanceStats,
            'prsScoreSheet' => $prsScoreSheet,
            'podium' => [
                'first' => $winner,
                'second' => $runnerUp,
                'third' => $third,
            ],
            'statCards' => [
                'totalShooters' => $rankedStandings->count(),
                'totalShots' => $totalShots,
                'totalHits' => $totalHits,
                'hitRate' => $hitRate,
                'avgScore' => $avgScore,
                'winnerScore' => $winner?->total_score ?? 0,
            ],
            'royalFlushesByDistance' => $royalFlushesByDistance,
            'royalFlushShootersByDistance' => $royalFlushShootersByDistance,
            'perfectHandShooters' => $perfectHandShooters,
            'matchFacts' => $matchFacts,
        ]);
    }

    /**
     * Build the PRS-specific Score Sheet data shape that mirrors the
     * on-screen Scoreboard "Score Sheet" tab.
     *
     * Differences from the standard heatmap:
     *   - Columns are grouped by STAGE (target_set) rather than distance —
     *     PRS matches are stage-based, not distance-based.
     *   - No multipliers anywhere (PRS = 1 hit, 1 point).
     *   - Per-stage TIME column derived from prs_stage_results.
     *   - Cell states preserve `not_taken` separately from `none` so the
     *     Score Sheet can show amber dots for declared no-takes vs grey
     *     dots for shots that were never recorded.
     *   - Aggregate Hits / Misses / Not-Taken / Total Time columns on
     *     the right edge of the row.
     *
     * @return array{
     *   stages: array<int, array{stage_id:int,label:string,short_label:string,gong_count:int,is_tiebreaker:bool}>,
     *   rows: array<int, array<string, mixed>>,
     * }
     */
    private function buildPrsScoreSheetData(ShootingMatch $match, \Illuminate\Support\Collection $standings): array
    {
        $targetSets = $match->targetSets()
            ->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();

        // Stage spine — one entry per target_set in display order.
        $stages = $targetSets->map(fn ($ts) => [
            'stage_id' => (int) $ts->id,
            'label' => $ts->label ?? ('Stage ' . ($ts->sort_order ?? '?')),
            // Short label for the column header — strip the "Stage N — "
            // prefix so the suffix (e.g. "Tiebreaker") is what leads.
            'short_label' => $this->prsShortStageLabel($ts->label, (int) ($ts->sort_order ?? 0)),
            'gong_count' => $ts->gongs->count(),
            'is_tiebreaker' => (bool) ($ts->is_tiebreaker ?? false),
        ])->values()->all();

        // Index per-stage results (hits / misses / time) by shooter+stage
        // for O(1) lookup as we walk the standings.
        $stageResults = PrsStageResult::where('match_id', $match->id)
            ->get()
            ->groupBy('shooter_id');

        // Per-shot states (hit / miss / not_taken) by shooter+stage+shot_number.
        // Unlike `prsScoresAsScoreCollection` we KEEP not_taken so the
        // Score Sheet can render an amber dot for a declared no-take.
        $shotStates = [];
        $shots = PrsShotScore::where('match_id', $match->id)
            ->get(['shooter_id', 'stage_id', 'shot_number', 'result']);
        foreach ($shots as $s) {
            $result = $s->result instanceof \BackedEnum ? $s->result->value : (string) $s->result;
            $shotStates[(int) $s->shooter_id][(int) $s->stage_id][(int) $s->shot_number] = $result;
        }

        $rows = [];
        foreach ($standings as $standing) {
            $sid = (int) $standing->shooter_id;
            $perShooterStages = [];
            $totalNotTaken = 0;
            $totalTime = 0.0;

            foreach ($stages as $stage) {
                $stageId = $stage['stage_id'];
                $gongCount = $stage['gong_count'];

                $cells = [];
                $hits = 0;
                $misses = 0;
                $notTaken = 0;
                for ($n = 1; $n <= $gongCount; $n++) {
                    $state = $shotStates[$sid][$stageId][$n] ?? null;
                    if ($state === 'hit') {
                        $cells[] = 'hit';
                        $hits++;
                    } elseif ($state === 'miss') {
                        $cells[] = 'miss';
                        $misses++;
                    } elseif ($state === 'not_taken') {
                        $cells[] = 'not_taken';
                        $notTaken++;
                    } else {
                        $cells[] = 'none';
                    }
                }

                // If there's a stage-result row but no shots are recorded
                // (e.g. fixture import that only knows aggregates), fall
                // back to the aggregate counts so the row totals still
                // reflect reality even though the cells stay grey.
                $stageResult = ($stageResults->get($sid, collect())->firstWhere('stage_id', $stageId));
                $time = $stageResult?->official_time_seconds !== null
                    ? (float) $stageResult->official_time_seconds
                    : null;
                if ($stageResult && $hits === 0 && $misses === 0 && $notTaken === 0) {
                    $hits = (int) $stageResult->hits;
                    $misses = (int) $stageResult->misses;
                }

                $totalNotTaken += $notTaken;
                if ($time !== null) {
                    $totalTime += $time;
                }

                $perShooterStages[$stageId] = [
                    'cells' => $cells,
                    'hits' => $hits,
                    'misses' => $misses,
                    'not_taken' => $notTaken,
                    'time' => $time,
                ];
            }

            $rows[] = [
                'shooter_id' => $sid,
                'rank' => $standing->rank,
                'name' => $standing->name,
                'display_name' => Str::before($standing->name, ' — ') ?: $standing->name,
                'caliber' => $this->caliberFromShooterName($standing->name),
                'squad' => $standing->squad,
                'status' => $standing->status,
                'total_hits' => (int) ($standing->hits ?? 0),
                'total_misses' => (int) ($standing->misses ?? 0),
                'total_not_taken' => $totalNotTaken,
                'total_time' => $totalTime > 0 ? round($totalTime, 1) : null,
                'stages' => $perShooterStages,
            ];
        }

        return [
            'stages' => $stages,
            'rows' => $rows,
        ];
    }

    /**
     * Strip the "Stage N — " prefix off a PRS stage label so the column
     * header in the Score Sheet leads with the meaningful suffix
     * ("Tiebreaker", a stage name, etc.). Falls back to "S{n}" when
     * there's nothing meaningful left.
     */
    private function prsShortStageLabel(?string $label, int $sortOrder): string
    {
        $label = trim((string) $label);
        if ($label === '') {
            return 'S' . max(1, $sortOrder);
        }
        // Match em-dash, en-dash, or hyphen used after the stage number.
        if (preg_match('/—\s*(.+)$/u', $label, $m) || preg_match('/–\s*(.+)$/u', $label, $m) || preg_match('/-\s*(.+)$/u', $label, $m)) {
            return trim($m[1]);
        }
        // No separator — return whatever's after "Stage N " if present.
        if (preg_match('/^Stage\s*\d+\s+(.+)$/i', $label, $m)) {
            return trim($m[1]);
        }
        return $label;
    }

    /**
     * Compact human-readable highlights that sit below the main heatmap on
     * the Full Match Report. Each fact is a plain string (optionally with a
     * short category tag) so the blade can render them as simple bullets
     * without needing per-fact templates.
     *
     * We intentionally skip facts whose underlying data is uninteresting
     * (e.g. "winning margin 0.0 pts" when there's only one shooter), which
     * keeps the section from padding itself with filler lines.
     */
    private function buildMatchFacts(
        ShootingMatch $match,
        \Illuminate\Support\Collection $rankedStandings,
        array $heatmap,
        array $distanceStats,
        array $heatmapColumns,
        array $royalFlushesByDistance,
        array $royalFlushShootersByDistance,
        array $perfectHandShooters,
    ): array {
        $facts = [];
        $isRf = (bool) ($match->royal_flush_enabled ?? false);

        // Winning margin — only meaningful with ≥2 ranked shooters.
        if ($rankedStandings->count() >= 2) {
            $winner = $rankedStandings->first();
            $runnerUp = $rankedStandings->skip(1)->first();
            $margin = (float) $winner->total_score - (float) $runnerUp->total_score;
            if ($margin > 0.001) {
                $winnerName = Str::before($winner->name, ' — ') ?: $winner->name;
                $facts[] = [
                    'tag' => 'Margin',
                    'text' => sprintf(
                        '%s took it by %d %s ahead of %s.',
                        $winnerName,
                        (int) round($margin),
                        $isRf ? 'points' : 'pts',
                        Str::before($runnerUp->name, ' — ') ?: $runnerUp->name,
                    ),
                ];
            } elseif (abs($margin) < 0.001) {
                $facts[] = [
                    'tag' => 'Dead heat',
                    'text' => 'The top two shooters finished on the same score — dead heat at the line.',
                ];
            }
        }

        // Perfect hands (every gong at every distance).
        if ($isRf && count($perfectHandShooters) > 0) {
            $names = implode(', ', $perfectHandShooters);
            $facts[] = [
                'tag' => 'Perfect Hand',
                'text' => count($perfectHandShooters) === 1
                    ? "{$names} shot a Perfect Hand — every gong at every distance, zero misses."
                    : "Perfect Hand shot by " . count($perfectHandShooters) . " shooters: {$names}.",
            ];
        }

        // Total Royal Flushes across the match.
        if ($isRf) {
            $totalFlushes = array_sum($royalFlushesByDistance);
            if ($totalFlushes > 0) {
                $facts[] = [
                    'tag' => 'Flushes',
                    'text' => $totalFlushes === 1
                        ? 'One Royal Flush was shot in this match.'
                        : "{$totalFlushes} Royal Flushes shot across the match.",
                ];
            } else {
                $facts[] = [
                    'tag' => 'Flushes',
                    'text' => 'No Royal Flushes this match — the steel held.',
                ];
            }
        }

        // Toughest and easiest distance by field hit rate.
        if (count($distanceStats) >= 2) {
            $sorted = collect($distanceStats)->sortBy('hit_rate')->values();
            $hardest = $sorted->first();
            $easiest = $sorted->last();
            if ($hardest && $easiest && $hardest['label'] !== $easiest['label']) {
                $facts[] = [
                    'tag' => 'Toughest',
                    'text' => sprintf(
                        '%s was the hardest distance (%d%% field hit rate); %s was the most forgiving at %d%%.',
                        $hardest['label'],
                        $hardest['hit_rate'],
                        $easiest['label'],
                        $easiest['hit_rate'],
                    ),
                ];
            }
        }

        // Most consistent shooter — highest hit rate among ranked field.
        $rankedSet = $rankedStandings->pluck('name')->flip();
        $mostConsistent = null;
        foreach ($heatmap as $row) {
            if (! isset($rankedSet[$row['name']])) {
                continue;
            }
            if ($row['total_shots'] < 4) { // need a real sample
                continue;
            }
            if ($mostConsistent === null || $row['hit_rate'] > $mostConsistent['hit_rate']) {
                $mostConsistent = $row;
            }
        }
        if ($mostConsistent !== null && $mostConsistent['hit_rate'] >= 70) {
            $winner = $rankedStandings->first();
            // Don't restate a fact about the winner if we already covered them.
            if (! $winner || $winner->name !== $mostConsistent['name']) {
                $facts[] = [
                    'tag' => 'Consistency',
                    'text' => sprintf(
                        '%s posted the highest hit rate in the field at %d%%.',
                        $mostConsistent['display_name'],
                        $mostConsistent['hit_rate'],
                    ),
                ];
            }
        }

        // Calibre diversity — gives a read on how varied the field was.
        $calibres = collect($heatmap)
            ->pluck('caliber')
            ->map(fn ($c) => trim((string) $c))
            ->filter()
            ->unique()
            ->values();
        if ($calibres->count() >= 3) {
            $facts[] = [
                'tag' => 'Field',
                'text' => sprintf(
                    '%d different calibres in the field — variety from %s.',
                    $calibres->count(),
                    $calibres->take(3)->implode(', ') . ($calibres->count() > 3 ? ', …' : ''),
                ),
            ];
        }

        // Most-dropped gong — which individual gong did the field struggle with.
        if (count($heatmapColumns) > 0) {
            $colStats = [];
            foreach ($heatmap as $row) {
                if (! isset($rankedSet[$row['name']])) {
                    continue;
                }
                foreach ($row['cells'] as $i => $cell) {
                    if ($cell['state'] === 'hit' || $cell['state'] === 'miss') {
                        $colStats[$i]['shots'] = ($colStats[$i]['shots'] ?? 0) + 1;
                        $colStats[$i]['hits'] = ($colStats[$i]['hits'] ?? 0) + (int) ($cell['state'] === 'hit');
                    }
                }
            }
            $worstCol = null;
            $worstRate = 101;
            foreach ($colStats as $i => $s) {
                if ($s['shots'] < 3) {
                    continue;
                }
                $rate = ($s['hits'] / $s['shots']) * 100;
                if ($rate < $worstRate) {
                    $worstRate = $rate;
                    $worstCol = $i;
                }
            }
            if ($worstCol !== null && $worstRate < 60) {
                $col = $heatmapColumns[$worstCol];
                $facts[] = [
                    'tag' => 'Nemesis',
                    'text' => sprintf(
                        'The %s G%d gong was the nemesis of the day — only %d%% of attempts landed.',
                        $col['distance_label'],
                        $col['gong_number'],
                        round($worstRate),
                    ),
                ];
            }
        }

        return $facts;
    }

    /**
     * Individual shooter report data — hero metrics, per-distance breakdown,
     * match insights, badges, and Royal Flush standing (if applicable).
     */
    private function buildShooterReportData(ShootingMatch $match, Shooter $shooter): array
    {
        $base = $this->buildPostMatchReportData($match);

        // Locate this shooter's standing row.
        $myStanding = collect($base['standings'])->firstWhere('shooter_id', $shooter->id);

        // Per-distance rows for just this shooter.
        $myDistances = [];
        foreach ($base['distanceTables'] as $dt) {
            $row = collect($dt['rows'])->firstWhere('name', $myStanding?->name);
            if (! $row) {
                continue;
            }
            $maxDistancePoints = $dt['distance_multiplier'] * collect($dt['gongs'])->sum('multiplier');
            $myDistances[] = [
                'distance_meters' => $dt['distance_meters'],
                'label' => $dt['label'] ?? ($dt['distance_meters'].'m'),
                'distance_multiplier' => $dt['distance_multiplier'],
                'gongs' => $dt['gongs'],
                'cells' => $row['cells'],
                'hits' => $row['hits'],
                'misses' => $row['misses'],
                'subtotal' => $row['subtotal'],
                'max_points' => round($maxDistancePoints, 2),
                'hit_rate' => ($row['hits'] + $row['misses']) > 0
                    ? round(($row['hits'] / ($row['hits'] + $row['misses'])) * 100)
                    : 0,
                'is_clean_sweep' => $row['hits'] > 0 && $row['misses'] === 0,
            ];
        }

        // Field context — how they did vs the field.
        // Ranked competitors only; a no-show accidentally recorded as 20 misses
        // would otherwise skew the average down and make every shooter look
        // better than they actually were relative to the real field.
        $standings = collect($base['standings']);
        $rankedStandings = $standings->filter(fn ($s) => $s->rank !== null)->values();
        $fieldAvg = $rankedStandings->avg('total_score') ?: 0;
        $fieldSize = $rankedStandings->count();
        $myRank = $myStanding?->rank ?? null;
        $myScore = $myStanding?->total_score ?? 0;

        // Insights (auto-computed, purely data-driven).
        //
        // Ordering / tie-breaking rules:
        //   - Best Distance: highest hit-rate, ties broken by the harder
        //     distance (higher distance_multiplier). A 100% sweep at 700m
        //     beats a 100% sweep at 400m.
        //   - Hardest Gong Cleared: highest weighted difficulty
        //     (distance_multiplier × gong_multiplier). Ties break by longer
        //     distance first, then higher gong multiplier.
        //   - Toughest Miss: highest-weighted gong the shooter missed. Only
        //     surfaced when the shooter dropped at least one shot. For a
        //     clean match we substitute "Match Margin" (how far ahead of the
        //     runner-up, or behind the leader) which is always meaningful and
        //     never redundant with the KPI row.
        //
        // The KPI row in the single-page report already shows "vs Field
        // Average" so we deliberately NO LONGER emit that as an insight tile
        // to avoid showing the same stat twice on JD's page.
        $insights = [];
        if (! empty($myDistances)) {
            $bestDistance = collect($myDistances)
                ->sortBy([
                    ['hit_rate', 'desc'],
                    ['distance_multiplier', 'desc'],
                ])
                ->first();
            if ($bestDistance && $bestDistance['hit_rate'] > 0) {
                $insights[] = [
                    'label' => 'Best Distance',
                    'value' => $bestDistance['label'].' — '.$bestDistance['hits'].'/'.($bestDistance['hits'] + $bestDistance['misses']),
                    'sub' => $bestDistance['hit_rate'].'% hit rate'
                        .($bestDistance['is_clean_sweep'] ? ' · clean sweep' : ''),
                ];
            }

            $cleanSweeps = collect($myDistances)->where('is_clean_sweep', true);
            if ($cleanSweeps->isNotEmpty()) {
                $insights[] = [
                    'label' => 'Clean Sweeps',
                    'value' => $cleanSweeps->count().' distance'.($cleanSweeps->count() === 1 ? '' : 's'),
                    'sub' => $cleanSweeps->pluck('label')->implode(' · '),
                ];
            }

            $hardestGongHit = null;
            $toughestMiss = null;
            foreach ($myDistances as $dist) {
                foreach ($dist['cells'] as $i => $cell) {
                    $gong = $dist['gongs'][$i] ?? null;
                    if (! $gong) {
                        continue;
                    }
                    $difficulty = (float) $dist['distance_multiplier'] * (float) $gong['multiplier'];
                    $distM = (float) $dist['distance_multiplier'];
                    $gongM = (float) $gong['multiplier'];

                    if ($cell['state'] === 'hit') {
                        // Tie-break on longer distance, then higher gong multiplier.
                        $better = $hardestGongHit === null
                            || $difficulty > $hardestGongHit['difficulty']
                            || ($difficulty === $hardestGongHit['difficulty']
                                && ($distM > $hardestGongHit['distance_multiplier']
                                    || ($distM === $hardestGongHit['distance_multiplier']
                                        && $gongM > $hardestGongHit['gong_multiplier'])));
                        if ($better) {
                            $hardestGongHit = [
                                'difficulty' => $difficulty,
                                'distance_multiplier' => $distM,
                                'gong_multiplier' => $gongM,
                                'label' => $dist['label'].' · G'.$gong['number'],
                                'points' => $cell['points'],
                            ];
                        }
                    } elseif ($cell['state'] === 'miss') {
                        $better = $toughestMiss === null
                            || $difficulty > $toughestMiss['difficulty']
                            || ($difficulty === $toughestMiss['difficulty']
                                && ($distM > $toughestMiss['distance_multiplier']
                                    || ($distM === $toughestMiss['distance_multiplier']
                                        && $gongM > $toughestMiss['gong_multiplier'])));
                        if ($better) {
                            $toughestMiss = [
                                'difficulty' => $difficulty,
                                'distance_multiplier' => $distM,
                                'gong_multiplier' => $gongM,
                                'label' => $dist['label'].' · G'.$gong['number'],
                                'points_forfeited' => $difficulty,
                            ];
                        }
                    }
                }
            }

            if ($hardestGongHit) {
                $insights[] = [
                    'label' => 'Hardest Gong Cleared',
                    'value' => $hardestGongHit['label'],
                    'sub' => '+'.number_format($hardestGongHit['points'], 1).' pts',
                ];
            }

            // Fourth tile: the single most informative secondary stat for THIS
            // shooter, avoiding overlap with KPI row cells.
            if ($toughestMiss) {
                // Shooter dropped at least one shot — show where the biggest
                // leak was. This is the stat people care about when they're
                // inside the top 10 but short of a clean match.
                $insights[] = [
                    'label' => 'Toughest Miss',
                    'value' => $toughestMiss['label'],
                    'sub' => '−'.number_format($toughestMiss['points_forfeited'], 1).' pts forfeited',
                ];
            } elseif ($fieldSize >= 2) {
                // Clean match. Show the gap to the next shooter (or to the
                // leader if this shooter IS the leader).
                if ($myRank === 1) {
                    $runnerUp = $standings->skip(1)->first();
                    $runnerScore = (float) ($runnerUp?->total_score ?? 0);
                    $gap = $myScore - $runnerScore;
                    $insights[] = [
                        'label' => 'Match Margin',
                        'value' => '+'.number_format($gap, 1).' pts',
                        'sub' => 'ahead of '.(
                            $runnerUp
                                ? (Str::before($runnerUp->name, ' — ') ?: $runnerUp->name)
                                : 'runner-up'
                        ),
                    ];
                } else {
                    $leader = $standings->first();
                    $leaderScore = (float) ($leader?->total_score ?? 0);
                    $gap = $leaderScore - $myScore;
                    $insights[] = [
                        'label' => 'Gap to Leader',
                        'value' => ($gap > 0 ? '−' : '').number_format($gap, 1).' pts',
                        'sub' => 'behind '.(
                            $leader
                                ? (Str::before($leader->name, ' — ') ?: $leader->name)
                                : 'leader'
                        ),
                    ];
                }
            } elseif ($totalShots = (($myStanding?->hits ?? 0) + ($myStanding?->misses ?? 0))) {
                // Solo-shooter edge case — still show something meaningful.
                $insights[] = [
                    'label' => 'Points per Shot',
                    'value' => number_format($myScore / max(1, $totalShots), 2),
                    'sub' => 'avg across '.$totalShots.' shots',
                ];
            }
        }

        // Badges earned in this match.
        $badges = [];
        if ($shooter->user_id) {
            $badges = UserAchievement::query()
                ->where('user_id', $shooter->user_id)
                ->where('match_id', $match->id)
                ->with('achievement')
                ->get()
                ->filter(fn ($ua) => $ua->achievement !== null)
                ->values()
                ->all();
        }

        // Royal Flush profile for this shooter (if RF-enabled).
        $myRf = null;
        if ($match->royal_flush_enabled) {
            $myRf = collect($base['rfLeaderboard'])->first(fn ($rf) => $rf->name === $myStanding?->name);
        }

        // Podium for field context (same derivation as the executive summary).
        $podium = [
            'first' => $standings->first(),
            'second' => $standings->skip(1)->first(),
            'third' => $standings->skip(2)->first(),
        ];

        return array_merge($base, [
            'shooter' => $shooter,
            'myStanding' => $myStanding,
            'myDistances' => $myDistances,
            'myRank' => $myRank,
            'myScore' => $myScore,
            'fieldSize' => $fieldSize,
            'fieldAvg' => $fieldAvg,
            'insights' => $insights,
            'badges' => $badges,
            'myRf' => $myRf,
            'podium' => $podium,
        ]);
    }

    private function buildPostMatchReportData(ShootingMatch $match): array
    {
        $match->load('organization');

        $targetSets = $match->targetSets()
            ->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();

        // PRS matches store per-shot results in `prs_shot_scores` and per-stage
        // aggregates (incl. stage time) in `prs_stage_results`, NOT in the
        // legacy `scores` table. Fall back to the standard `scores`-based
        // pipeline only when there are no PRS rows on file (i.e. truly
        // standard/RF, or a legacy PRS match that was scored gong-by-gong
        // before the PRS tables existed).
        $usePrsTables = $match->isPrs()
            && PrsStageResult::where('match_id', $match->id)->exists();

        if ($usePrsTables) {
            $standings = $this->prsStandingsForReport($match, $targetSets);
            $scores = $this->prsScoresAsScoreCollection($match, $targetSets);
        } else {
            $standings = (new MatchStandingsService)->standardStandings($match);
            $allGongIds = $targetSets->flatMap(fn ($ts) => $ts->gongs->pluck('id'));
            $scores = Score::query()
                ->whereIn('shooter_id', $standings->pluck('shooter_id'))
                ->whereIn('gong_id', $allGongIds)
                ->get()
                ->groupBy('shooter_id');
        }

        $shooterIds = $standings->pluck('shooter_id');

        // Preload user_id for every shooter in the match so report views can
        // decide whether to render a "Claim this result" chip per row.
        $shooterUserIds = Shooter::query()
            ->whereIn('id', $shooterIds)
            ->pluck('user_id', 'id')
            ->all();

        // Distance tables (tablet-summary shape): one table per target set, one row
        // per shooter (ordered by overall match rank), with tick/cross + points per gong.
        $distanceTables = [];
        foreach ($targetSets as $ts) {
            $mult = (float) ($ts->distance_multiplier ?? 1);
            $rows = [];
            foreach ($standings as $standing) {
                $shooterScores = $scores->get($standing->shooter_id, collect())->keyBy('gong_id');
                $cells = [];
                $rowHits = 0;
                $rowMisses = 0;
                $rowSubtotal = 0.0;
                foreach ($ts->gongs as $g) {
                    $score = $shooterScores->get($g->id);
                    if (! $score) {
                        $cells[] = ['state' => 'none', 'points' => null];

                        continue;
                    }
                    if ($score->is_hit) {
                        $pts = $mult * (float) $g->multiplier;
                        $cells[] = ['state' => 'hit', 'points' => $pts];
                        $rowHits++;
                        $rowSubtotal += $pts;
                    } else {
                        $cells[] = ['state' => 'miss', 'points' => null];
                        $rowMisses++;
                    }
                }
                $rows[] = [
                    'shooter_id' => (int) $standing->shooter_id,
                    'user_id' => $shooterUserIds[$standing->shooter_id] ?? null,
                    'rank' => $standing->rank,
                    'name' => $standing->name,
                    'caliber' => $this->caliberFromShooterName($standing->name),
                    'squad' => $standing->squad,
                    'status' => $standing->status,
                    'cells' => $cells,
                    'hits' => $rowHits,
                    'misses' => $rowMisses,
                    'subtotal' => round($rowSubtotal, 2),
                ];
            }
            $distanceTables[] = [
                'label' => $ts->label,
                'distance_meters' => $ts->distance_meters,
                'distance_multiplier' => $mult,
                'gongs' => $ts->gongs->map(fn ($g) => [
                    'number' => $g->number,
                    'label' => $g->label,
                    'multiplier' => (float) $g->multiplier,
                    'points_per_hit' => round($mult * (float) $g->multiplier, 2),
                ])->all(),
                'rows' => $rows,
            ];
        }

        // Royal Flush leaderboard (if enabled)
        $rfLeaderboard = collect();
        if ($match->royal_flush_enabled) {
            $rfTs = $match->targetSets()->orderByDesc('distance_meters')->with('gongs')->get();
            $gongToTs = [];
            foreach ($rfTs as $ts) {
                foreach ($ts->gongs as $g) {
                    $gongToTs[$g->id] = $ts->id;
                }
            }

            $hitsByShooterTs = [];
            foreach ($scores as $shooterId => $shooterScores) {
                foreach ($shooterScores as $s) {
                    if (! $s->is_hit) {
                        continue;
                    }
                    $tsId = $gongToTs[$s->gong_id] ?? null;
                    if ($tsId === null) {
                        continue;
                    }
                    $hitsByShooterTs[$shooterId][$tsId] = ($hitsByShooterTs[$shooterId][$tsId] ?? 0) + 1;
                }
            }

            $rfProfiles = [];
            foreach ($standings as $row) {
                $flushDistances = [];
                foreach ($rfTs as $ts) {
                    $gongCount = $ts->gongs->count();
                    $hitsAtTs = $hitsByShooterTs[$row->shooter_id][$ts->id] ?? 0;
                    if ($gongCount > 0 && $hitsAtTs >= $gongCount) {
                        $flushDistances[] = (int) $ts->distance_meters;
                    }
                }
                if (empty($flushDistances)) {
                    continue;
                }
                $rfProfiles[] = (object) [
                    'name' => $row->name,
                    'squad' => $row->squad,
                    'flush_count' => count($flushDistances),
                    'flush_distances' => $flushDistances,
                    'total_score' => $row->total_score,
                ];
            }

            usort($rfProfiles, function ($a, $b) {
                if ($a->flush_count !== $b->flush_count) {
                    return $b->flush_count <=> $a->flush_count;
                }
                $aMax = max($a->flush_distances);
                $bMax = max($b->flush_distances);
                if ($aMax !== $bMax) {
                    return $bMax <=> $aMax;
                }

                return $b->total_score <=> $a->total_score;
            });

            $rfLeaderboard = collect($rfProfiles);
        }

        // Side bet cascade (if enabled)
        $sideBetCascade = null;
        if ($match->side_bet_enabled) {
            $sideBetCascade = $this->buildSideBetCascadeForPdf($match);
        }

        return [
            'match' => $match,
            'standings' => $standings,
            'targetSets' => $targetSets,
            'distanceTables' => $distanceTables,
            'rfLeaderboard' => $rfLeaderboard,
            'sideBetCascade' => $sideBetCascade,
            'generatedAt' => now(),
        ];
    }

    /**
     * PRS standings shaped like MatchStandingsService::standardStandings.
     *
     * Sourced from `prs_stage_results` (per-stage hits/misses + stage time)
     * with PRS tiebreakers applied:
     *   1. raw hits (desc)
     *   2. tiebreaker-stage hits (desc)
     *   3. tiebreaker-stage time (asc; null pushed to the back)
     *   4. aggregate match time (asc)
     *
     * Returns plain objects matching the standard standings contract:
     *   shooter_id, name, squad, hits, misses, total_score, status, rank
     *
     * `total_score` for PRS is just the hit count (one point per hit) so the
     * podium and stat cards keep speaking the same numeric language as the
     * scoreboard. DQ / no-show shooters get rank=null and sit at the bottom.
     */
    private function prsStandingsForReport(ShootingMatch $match, \Illuminate\Support\Collection $targetSets): \Illuminate\Support\Collection
    {
        $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id as shooter_id', 'shooters.name', 'shooters.status', 'squads.name as squad')
            ->get();

        $allResults = PrsStageResult::where('match_id', $match->id)
            ->get()
            ->groupBy('shooter_id');

        $entries = $shooters->map(function ($s) use ($allResults, $tiebreakerStage) {
            $sid = (int) $s->shooter_id;
            $results = $allResults->get($sid, collect());

            $hits = (int) $results->sum('hits');
            $misses = (int) $results->sum('misses');
            $aggTime = (float) $results->whereNotNull('official_time_seconds')
                ->sum(fn ($r) => (float) $r->official_time_seconds);

            $tbHits = 0;
            $tbTime = null;
            if ($tiebreakerStage) {
                $tbResult = $results->firstWhere('stage_id', $tiebreakerStage->id);
                if ($tbResult) {
                    $tbHits = (int) $tbResult->hits;
                    $tbTime = $tbResult->official_time_seconds !== null
                        ? (float) $tbResult->official_time_seconds
                        : null;
                }
            }

            return (object) [
                'shooter_id' => $sid,
                'name' => $s->name,
                'squad' => $s->squad,
                'status' => $s->status ?? 'active',
                'hits' => $hits,
                'misses' => $misses,
                'total_score' => (float) $hits,
                'tb_hits' => $tbHits,
                'tb_time' => $tbTime,
                'agg_time' => $aggTime,
            ];
        });

        $ranked = $entries
            ->filter(fn ($e) => ! in_array($e->status, MatchStandingsService::NON_RANKED_STATUSES, true))
            ->sort(function ($a, $b) {
                if ($a->hits !== $b->hits) return $b->hits <=> $a->hits;
                if ($a->tb_hits !== $b->tb_hits) return $b->tb_hits <=> $a->tb_hits;
                $aTb = $a->tb_time ?? PHP_FLOAT_MAX;
                $bTb = $b->tb_time ?? PHP_FLOAT_MAX;
                if ($aTb !== $bTb) return $aTb <=> $bTb;
                return $a->agg_time <=> $b->agg_time;
            })
            ->values();

        $nonRanked = $entries
            ->filter(fn ($e) => in_array($e->status, MatchStandingsService::NON_RANKED_STATUSES, true))
            ->sortBy(fn ($e) => $e->status === 'dq' ? 0 : 1)
            ->values();

        $standings = collect();
        foreach ($ranked as $i => $row) {
            $standings->push((object) [
                'shooter_id' => $row->shooter_id,
                'name' => $row->name,
                'squad' => $row->squad,
                'hits' => $row->hits,
                'misses' => $row->misses,
                'total_score' => round($row->total_score, 2),
                'status' => $row->status,
                'rank' => $i + 1,
            ]);
        }
        foreach ($nonRanked as $row) {
            $standings->push((object) [
                'shooter_id' => $row->shooter_id,
                'name' => $row->name,
                'squad' => $row->squad,
                'hits' => $row->hits,
                'misses' => $row->misses,
                'total_score' => round($row->total_score, 2),
                'status' => $row->status,
                'rank' => null,
            ]);
        }

        return $standings;
    }

    /**
     * Re-shape PRS shot scores into the same `Collection<shooter_id, Collection<Score-like>>`
     * structure the report-builder expects from the legacy `scores` table.
     *
     * `prs_shot_scores` records every shot by `shot_number` (1..N within a
     * stage) rather than by `gong_id`. We map shot_number → gong_id by
     * ordering each stage's gongs by `number` (the same order the scoring
     * app and the scoreboard use), then synthesise lightweight stdClass
     * "score" objects exposing `gong_id` and `is_hit` so the cell-builder
     * loop in buildPostMatchReportData doesn't need to branch on scoring
     * type. Misses get `is_hit = false`; shots with result=not_taken (or
     * shots that simply weren't recorded) are omitted entirely so the
     * cell renders as an empty `none` slot — matching what the PRS
     * scoreboard shows on screen.
     */
    private function prsScoresAsScoreCollection(ShootingMatch $match, \Illuminate\Support\Collection $targetSets): \Illuminate\Support\Collection
    {
        // Build (stage_id, shot_number) → gong_id lookup. shot_number is 1-based.
        $shotToGong = [];
        foreach ($targetSets as $ts) {
            $i = 1;
            foreach ($ts->gongs as $g) {
                $shotToGong[$ts->id][$i] = $g->id;
                $i++;
            }
        }

        $shots = PrsShotScore::where('match_id', $match->id)
            ->orderBy('shot_number')
            ->get(['shooter_id', 'stage_id', 'shot_number', 'result']);

        return $shots->map(function ($s) use ($shotToGong) {
            $gongId = $shotToGong[$s->stage_id][$s->shot_number] ?? null;
            if ($gongId === null) {
                return null;
            }

            $result = $s->result instanceof \BackedEnum ? $s->result->value : (string) $s->result;
            if ($result === 'not_taken') {
                // Match scoreboard semantics: a not_taken shot leaves the cell empty
                // rather than reading as a recorded miss.
                return null;
            }

            return (object) [
                'shooter_id' => (int) $s->shooter_id,
                'gong_id' => (int) $gongId,
                'is_hit' => $result === 'hit',
            ];
        })
            ->filter()
            ->values()
            ->groupBy('shooter_id');
    }

    /**
     * Extract a caliber from a shooter name in the format "Firstname Lastname — 6x46".
     * Tolerates both em-dash and hyphen separators. Returns null if no suffix.
     */
    private function caliberFromShooterName(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        // Split on em-dash first, then on " - " (space-hyphen-space) to avoid eating hyphens inside names.
        foreach ([' — ', ' – ', ' - '] as $sep) {
            if (str_contains($name, $sep)) {
                $parts = explode($sep, $name, 2);
                $tail = trim($parts[1] ?? '');

                return $tail !== '' ? $tail : null;
            }
        }

        return null;
    }

    private function buildSideBetCascadeForPdf(ShootingMatch $match): array
    {
        $targetSets = $match->targetSets()->with('gongs')->get();

        // Group gongs by their "rank" (number/position within their target set).
        // Ranking rule: rank by biggest-gong hits first, cascade down through all gong sizes.
        $gongsByNumber = [];
        foreach ($targetSets as $ts) {
            foreach ($ts->gongs as $g) {
                $gongsByNumber[(int) $g->number][] = $g->id;
            }
        }
        ksort($gongsByNumber);

        $buyInShooterIds = $match->sideBetShooters()->pluck('shooters.id')->toArray();
        if (empty($buyInShooterIds)) {
            return [
                'participants' => collect(),
                'cascade_columns' => [],
            ];
        }

        $shooters = Shooter::whereIn('id', $buyInShooterIds)
            ->with('squad')
            ->get();

        // Count hits per shooter per gong-number bucket
        $hitsByShooter = [];
        foreach ($shooters as $shooter) {
            foreach ($gongsByNumber as $number => $gongIds) {
                $hits = Score::where('shooter_id', $shooter->id)
                    ->whereIn('gong_id', $gongIds)
                    ->where('is_hit', true)
                    ->count();
                $hitsByShooter[$shooter->id][$number] = $hits;
            }
        }

        // Rank: highest gong-number (smallest gong) first — but user's spec says:
        // "rank by biggest gong hits first, cascade down through all sizes".
        // "Biggest gong" = gong number 1 (or lowest number) traditionally.
        // We respect gong number ordering ascending (1 = biggest) for cascade.
        $cascadeColumns = array_keys($gongsByNumber);
        // Cascade from biggest (lowest number) to smallest (highest number)
        sort($cascadeColumns);

        $participants = $shooters->map(function ($s) use ($hitsByShooter, $cascadeColumns) {
            return (object) [
                'name' => $s->name,
                'squad' => $s->squad?->name ?? '',
                'cascade' => array_map(fn ($n) => $hitsByShooter[$s->id][$n] ?? 0, $cascadeColumns),
            ];
        });

        $participants = $participants->sort(function ($a, $b) {
            foreach ($a->cascade as $i => $aHits) {
                $bHits = $b->cascade[$i] ?? 0;
                if ($aHits !== $bHits) {
                    return $bHits <=> $aHits;
                }
            }

            return 0;
        })->values();

        return [
            'participants' => $participants,
            'cascade_columns' => $cascadeColumns,
        ];
    }

    private function buildPdfStandingsData(ShootingMatch $match): array
    {
        $match->load('organization');
        $divisionNames = $this->divisionLookup($match);

        $resolver = app(SponsorPlacementResolver::class);
        $sponsorAssignment = $resolver->resolve(PlacementKey::GlobalExports, $match->id);

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('scores', 'shooters.id', '=', 'scores.shooter_id')
            ->leftJoin('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->leftJoin('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id as shooter_id', 'shooters.name', 'squads.name as squad')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 1 THEN 1 END) as agg_hits')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 0 THEN 1 END) as agg_misses')
            ->selectRaw('COALESCE(SUM(CASE WHEN scores.is_hit = 1 THEN COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier ELSE 0 END), 0) as agg_total')
            ->groupBy('shooters.id', 'shooters.name', 'squads.name')
            ->orderByDesc('agg_total')
            ->get()
            ->each(fn ($s) => $s->division = $divisionNames[(int) $s->shooter_id] ?? '');

        return [
            'match' => $match,
            'shooters' => $shooters,
            'sponsorAssignment' => $sponsorAssignment,
        ];
    }

    private function buildPdfDetailedData(ShootingMatch $match): array
    {
        $match->load('organization');
        $divisionNames = $this->divisionLookup($match);

        $resolver = app(SponsorPlacementResolver::class);
        $sponsorAssignment = $resolver->resolve(PlacementKey::GlobalExports, $match->id);

        $targetSets = $match->targetSets()
            ->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();

        $allGongs = $targetSets->flatMap->gongs;

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id', 'shooters.name', 'squads.name as squad_name')
            ->get();

        $allScores = Score::query()
            ->whereIn('shooter_id', $shooters->pluck('id'))
            ->whereIn('gong_id', $allGongs->pluck('id'))
            ->get()
            ->groupBy('shooter_id');

        $rows = $shooters->map(function ($shooter) use ($allScores, $targetSets, $divisionNames) {
            $scores = $allScores->get($shooter->id, collect())->keyBy('gong_id');
            $totalScore = 0;
            $totalHits = 0;
            $totalMisses = 0;
            $stageData = [];

            foreach ($targetSets as $ts) {
                $distMult = (float) ($ts->distance_multiplier ?? 1);
                $distSubtotal = 0;
                $gongResults = [];

                foreach ($ts->gongs as $g) {
                    $score = $scores->get($g->id);
                    if (! $score) {
                        $gongResults[] = '-';
                    } elseif ($score->is_hit) {
                        $gongResults[] = 'H';
                        $points = round($distMult * $g->multiplier, 2);
                        $distSubtotal += $points;
                        $totalScore += $points;
                        $totalHits++;
                    } else {
                        $gongResults[] = 'M';
                        $totalMisses++;
                    }
                }
                $stageData[] = ['results' => $gongResults, 'subtotal' => round($distSubtotal, 2)];
            }

            return [
                'name' => $shooter->name,
                'squad' => $shooter->squad_name,
                'division' => $divisionNames[$shooter->id] ?? '',
                'stages' => $stageData,
                'hits' => $totalHits,
                'misses' => $totalMisses,
                'total' => round($totalScore, 2),
            ];
        })->sortByDesc('total')->values();

        return [
            'match' => $match,
            'targetSets' => $targetSets,
            'rows' => $rows,
            'sponsorAssignment' => $sponsorAssignment,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function authorizeExport(ShootingMatch $match): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        if ($user->isAdmin()) {
            return;
        }

        if ($match->organization_id && $user->isOrgMatchDirector($match->organization)) {
            return;
        }

        abort(403, 'Only match directors and organization owners can download results.');
    }

    /**
     * Guard against URL tampering on org-scoped export routes.
     *
     * The org route group is prefixed with `/org/{organization}/...` so
     * Laravel injects an Organization as the first controller arg. When
     * the admin group or the public scoreboard routes (which don't have
     * that prefix) call the same method, Laravel's ControllerDispatcher
     * has nothing to bind it to — and because `?Organization` without a
     * `= null` default isn't treated as "defaulted" by the dispatcher,
     * it falls through to the service container and injects an EMPTY
     * Organization instance (not null). We treat an empty/unsaved
     * Organization the same as null: no org context, no check.
     *
     * When an organization IS bound (has a primary key), make sure the
     * match actually belongs to it. Without this, a match director of
     * Org A could craft a URL like /org/a/matches/42/export/... where
     * match 42 belongs to Org B and slip past authorizeExport (since
     * Org A membership lets them through the org.admin middleware).
     */
    private function ensureOrgMatch(?Organization $organization, ShootingMatch $match): void
    {
        if ($organization === null || ! $organization->exists) {
            return;
        }

        abort_unless(
            $match->organization_id === $organization->id,
            404,
            'Match does not belong to this organization.',
        );
    }

    private function divisionLookup(ShootingMatch $match): array
    {
        return DB::table('shooters')
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('match_divisions', 'shooters.match_division_id', '=', 'match_divisions.id')
            ->where('squads.match_id', $match->id)
            ->pluck('match_divisions.name', 'shooters.id')
            ->toArray();
    }

    private function streamCsv(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer) {
            $out = fopen('php://output', 'w');
            $writer($out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
