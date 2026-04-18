<?php

namespace App\Http\Controllers;

use App\Enums\PlacementKey;
use App\Models\ElrShot;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use App\Services\PdfDocumentRenderer;
use App\Services\Scoring\ELRScoringService;
use App\Services\SponsorPlacementResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatchExportController extends Controller
{
    public function standings(ShootingMatch $match): StreamedResponse
    {
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

    public function detailed(ShootingMatch $match): StreamedResponse
    {
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
                    if (!$score) {
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
        $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);

        $shooterTimes = StageTime::whereIn('target_set_id', $targetSetIds)
            ->get()->groupBy('shooter_id')
            ->map(fn ($t) => (float) $t->sum('time_seconds'))->toArray();

        $tbHits = [];
        $tbTimes = [];
        if ($tiebreakerStage) {
            $tbGongIds = DB::table('gongs')->where('target_set_id', $tiebreakerStage->id)->pluck('id');
            $tbHits = Score::whereIn('gong_id', $tbGongIds)->where('is_hit', true)
                ->select('shooter_id', DB::raw('COUNT(*) as c'))->groupBy('shooter_id')
                ->pluck('c', 'shooter_id')->map(fn ($v) => (int) $v)->toArray();
            $tbTimes = StageTime::where('target_set_id', $tiebreakerStage->id)
                ->pluck('time_seconds', 'shooter_id')->map(fn ($v) => (float) $v)->toArray();
        }

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('scores', 'shooters.id', '=', 'scores.shooter_id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id as shooter_id', 'shooters.name', 'squads.name as squad')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 1 THEN 1 END) as agg_hits')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 0 THEN 1 END) as agg_misses')
            ->groupBy('shooters.id', 'shooters.name', 'squads.name')
            ->get();

        $entries = $shooters->map(function ($s) use ($shooterTimes, $tbHits, $tbTimes, $divisionNames, $totalTargets) {
            $sid = (int) $s->shooter_id;
            return [
                'sid' => $sid, 'name' => $s->name, 'squad' => $s->squad,
                'division' => $divisionNames[$sid] ?? '',
                'hits' => (int) $s->agg_hits, 'misses' => (int) $s->agg_misses,
                'not_taken' => $totalTargets - (int) $s->agg_hits - (int) $s->agg_misses,
                'total_time' => round($shooterTimes[$sid] ?? 0.0, 2),
                'tb_hits' => $tbHits[$sid] ?? 0,
                'tb_time' => round($tbTimes[$sid] ?? 0.0, 2),
            ];
        })->sort(function ($a, $b) {
            if ($a['hits'] !== $b['hits']) return $b['hits'] <=> $a['hits'];
            if ($a['tb_hits'] !== $b['tb_hits']) return $b['tb_hits'] <=> $a['tb_hits'];
            if ($a['tb_time'] !== $b['tb_time']) return $a['tb_time'] <=> $b['tb_time'];
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
        $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);
        $allGongs = $targetSets->flatMap->gongs;

        $shooterTimesByStage = StageTime::whereIn('target_set_id', $targetSetIds)
            ->get()->groupBy('shooter_id');

        $tbHits = [];
        $tbTimes = [];
        if ($tiebreakerStage) {
            $tbGongIds = DB::table('gongs')->where('target_set_id', $tiebreakerStage->id)->pluck('id');
            $tbHits = Score::whereIn('gong_id', $tbGongIds)->where('is_hit', true)
                ->select('shooter_id', DB::raw('COUNT(*) as c'))->groupBy('shooter_id')
                ->pluck('c', 'shooter_id')->map(fn ($v) => (int) $v)->toArray();
            $tbTimes = StageTime::where('target_set_id', $tiebreakerStage->id)
                ->pluck('time_seconds', 'shooter_id')->map(fn ($v) => (float) $v)->toArray();
        }

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id', 'shooters.name', 'squads.name as squad_name')
            ->get();

        $allScores = Score::whereIn('shooter_id', $shooters->pluck('id'))
            ->whereIn('gong_id', $allGongs->pluck('id'))
            ->get()->groupBy('shooter_id');

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

        $rows = $shooters->map(function ($shooter) use ($allScores, $targetSets, $shooterTimesByStage, $divisionNames, $totalTargets, $tbHits, $tbTimes) {
            $scores = $allScores->get($shooter->id, collect())->keyBy('gong_id');
            $times = $shooterTimesByStage->get($shooter->id, collect())->keyBy('target_set_id');
            $totalHits = 0;
            $totalMisses = 0;
            $totalTime = 0;
            $cells = [];

            foreach ($targetSets as $ts) {
                foreach ($ts->gongs as $g) {
                    $score = $scores->get($g->id);
                    if (!$score) {
                        $cells[] = '-';
                    } elseif ($score->is_hit) {
                        $cells[] = 'H';
                        $totalHits++;
                    } else {
                        $cells[] = 'M';
                        $totalMisses++;
                    }
                }
                $stageTime = $times->get($ts->id)?->time_seconds ?? 0;
                $totalTime += $stageTime;
                $cells[] = round((float) $stageTime, 2);
            }

            $sid = $shooter->id;
            return [
                'name' => $shooter->name, 'squad' => $shooter->squad_name,
                'division' => $divisionNames[$sid] ?? '',
                'cells' => $cells,
                'hits' => $totalHits, 'misses' => $totalMisses,
                'not_taken' => $totalTargets - $totalHits - $totalMisses,
                'total_time' => round($totalTime, 2),
                'tb_hits' => $tbHits[$sid] ?? 0,
                'tb_time' => round($tbTimes[$sid] ?? 0.0, 2),
            ];
        })->sort(function ($a, $b) {
            if ($a['hits'] !== $b['hits']) return $b['hits'] <=> $a['hits'];
            if ($a['tb_hits'] !== $b['tb_hits']) return $b['tb_hits'] <=> $a['tb_hits'];
            if ($a['tb_time'] !== $b['tb_time']) return $a['tb_time'] <=> $b['tb_time'];
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

    // ── ELR ─────────────────────────────────────────────────────

    private function elrStandings(ShootingMatch $match, $out): void
    {
        $data = (new ELRScoringService())->calculateStandings($match);

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
        $data = (new ELRScoringService())->calculateStandings($match);
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

    public function pdfStandings(ShootingMatch $match, PdfDocumentRenderer $renderer)
    {
        $this->authorizeExport($match);
        $slug = Str::slug($match->name);
        $data = $this->buildPdfStandingsData($match);

        return $renderer->stream('exports.pdf-standings', $data, "{$slug}-standings.pdf");
    }

    public function pdfDetailed(ShootingMatch $match, PdfDocumentRenderer $renderer)
    {
        $this->authorizeExport($match);
        $slug = Str::slug($match->name);
        $data = $this->buildPdfDetailedData($match);

        return $renderer->stream('exports.pdf-detailed', $data, "{$slug}-detailed.pdf");
    }

    /**
     * Executive summary PDF — single page A4 landscape, all shooters on a
     * filled-square heatmap, podium, stat cards, branded header.
     *
     * Replaces the legacy pdfPostMatchReport entry point (same route, new output).
     */
    public function pdfPostMatchReport(ShootingMatch $match, PdfDocumentRenderer $renderer)
    {
        return $this->pdfExecutiveSummary($match, $renderer);
    }

    /**
     * Royal Flush results report — A4 portrait HTML page.
     *
     * Light/magazine aesthetic, every shot rendered as a hit/miss cell grouped
     * by distance with per-distance multipliers shown in column headers.
     * Same data shape as the executive summary (re-uses buildExecutiveSummaryData).
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

    public function pdfExecutiveSummary(ShootingMatch $match, PdfDocumentRenderer $renderer)
    {
        $this->authorizeExport($match);
        $slug = Str::slug($match->name);
        $data = $this->buildExecutiveSummaryData($match);

        // A4 landscape — wider grid for heatmap.
        return $renderer->stream(
            'exports.pdf-executive-summary',
            $data,
            "{$slug}-executive-summary.pdf",
            ['width' => 297.0, 'height' => 210.0],
        );
    }

    /**
     * Individual shooter report — 3-page A4 portrait: hero, stages, badges + insights.
     */
    public function pdfShooterReport(ShootingMatch $match, Shooter $shooter, PdfDocumentRenderer $renderer)
    {
        $this->authorizeExport($match);

        // Guard: shooter must belong to this match (via squad).
        abort_unless(
            $shooter->squad && $shooter->squad->match_id === $match->id,
            404,
            'Shooter does not belong to this match.',
        );

        $slug = Str::slug($match->name . '-' . $shooter->name);
        $data = $this->buildShooterReportData($match, $shooter);

        return $renderer->stream('exports.pdf-shooter-report', $data, "{$slug}-shooter-report.pdf");
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

        // Build heatmap matrix: one entry per shooter, cells = flat list of hit/miss/none per gong, same column order across all shooters.
        $heatmapColumns = [];
        foreach ($distanceTables as $dt) {
            foreach ($dt['gongs'] as $g) {
                $heatmapColumns[] = [
                    'distance_meters' => $dt['distance_meters'],
                    'distance_label' => $dt['label'] ?? ($dt['distance_meters'] . 'm'),
                    'distance_multiplier' => $dt['distance_multiplier'],
                    'gong_number' => $g['number'],
                    'gong_multiplier' => $g['multiplier'],
                    'points_per_hit' => $g['points_per_hit'],
                ];
            }
        }

        // Index distance rows by shooter for fast lookup.
        $shooterRows = [];
        foreach ($distanceTables as $dt) {
            foreach ($dt['rows'] as $row) {
                $shooterRows[$row['name']][$dt['distance_meters']] = $row;
            }
        }

        $heatmap = [];
        foreach ($standings as $standing) {
            $cells = [];
            foreach ($distanceTables as $dt) {
                $row = $shooterRows[$standing->name][$dt['distance_meters']] ?? null;
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
                'display_name' => \Illuminate\Support\Str::before($standing->name, ' — ') ?: $standing->name,
                'caliber' => $this->caliberFromShooterName($standing->name),
                'squad' => $standing->squad,
                'status' => $standing->status,
                'total_score' => $standing->total_score,
                'total_hits' => $standing->hits ?? 0,
                'total_shots' => ($standing->hits ?? 0) + ($standing->misses ?? 0),
                'hit_rate' => (($standing->hits ?? 0) + ($standing->misses ?? 0)) > 0
                    ? round((($standing->hits ?? 0) / (($standing->hits ?? 0) + ($standing->misses ?? 0))) * 100)
                    : 0,
                'cells' => $cells,
            ];
        }

        // Stat cards
        $totalShots = 0;
        $totalHits = 0;
        foreach ($heatmap as $row) {
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

        $winner = $standings->first();
        $runnerUp = $standings->skip(1)->first();
        $third = $standings->skip(2)->first();

        $avgScore = $standings->count() > 0
            ? round($standings->avg('total_score'), 1)
            : 0;

        // Per-distance hit rate for header ribbon
        $distanceStats = [];
        foreach ($distanceTables as $dt) {
            $shots = 0;
            $hits = 0;
            foreach ($dt['rows'] as $row) {
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
                'label' => $dt['label'] ?? ($dt['distance_meters'] . 'm'),
                'multiplier' => $dt['distance_multiplier'],
                'gong_count' => count($dt['gongs']),
                'shots' => $shots,
                'hits' => $hits,
                'hit_rate' => $shots > 0 ? round(($hits / $shots) * 100) : 0,
            ];
        }

        return array_merge($base, [
            'heatmap' => $heatmap,
            'heatmapColumns' => $heatmapColumns,
            'distanceStats' => $distanceStats,
            'podium' => [
                'first' => $winner,
                'second' => $runnerUp,
                'third' => $third,
            ],
            'statCards' => [
                'totalShooters' => $standings->count(),
                'totalShots' => $totalShots,
                'totalHits' => $totalHits,
                'hitRate' => $hitRate,
                'avgScore' => $avgScore,
                'winnerScore' => $winner?->total_score ?? 0,
            ],
        ]);
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
                'label' => $dt['label'] ?? ($dt['distance_meters'] . 'm'),
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

        // Insights (auto-computed, purely data-driven).
        $insights = [];
        if (! empty($myDistances)) {
            $bestDistance = collect($myDistances)->sortByDesc('hit_rate')->first();
            if ($bestDistance && $bestDistance['hit_rate'] > 0) {
                $insights[] = [
                    'label' => 'Best Distance',
                    'value' => $bestDistance['label'] . ' — ' . $bestDistance['hits'] . '/' . ($bestDistance['hits'] + $bestDistance['misses']),
                    'sub' => $bestDistance['hit_rate'] . '% hit rate',
                ];
            }

            $cleanSweeps = collect($myDistances)->where('is_clean_sweep', true);
            if ($cleanSweeps->isNotEmpty()) {
                $insights[] = [
                    'label' => 'Clean Sweeps',
                    'value' => $cleanSweeps->count() . ' distance' . ($cleanSweeps->count() === 1 ? '' : 's'),
                    'sub' => $cleanSweeps->pluck('label')->implode(' · '),
                ];
            }

            $hardestGongHit = null;
            foreach ($myDistances as $dist) {
                foreach ($dist['cells'] as $i => $cell) {
                    if ($cell['state'] !== 'hit') {
                        continue;
                    }
                    $gong = $dist['gongs'][$i] ?? null;
                    if (! $gong) {
                        continue;
                    }
                    $difficulty = $dist['distance_multiplier'] * $gong['multiplier'];
                    if ($hardestGongHit === null || $difficulty > $hardestGongHit['difficulty']) {
                        $hardestGongHit = [
                            'difficulty' => $difficulty,
                            'label' => $dist['label'] . ' · G' . $gong['number'],
                            'points' => $cell['points'],
                        ];
                    }
                }
            }
            if ($hardestGongHit) {
                $insights[] = [
                    'label' => 'Hardest Gong Cleared',
                    'value' => $hardestGongHit['label'],
                    'sub' => '+' . number_format($hardestGongHit['points'], 1) . ' pts',
                ];
            }
        }

        // Field context — how they did vs the field.
        $standings = collect($base['standings']);
        $fieldAvg = $standings->avg('total_score') ?: 0;
        $fieldSize = $standings->count();
        $myRank = $myStanding?->rank ?? null;
        $myScore = $myStanding?->total_score ?? 0;
        $vsAvg = $myScore - $fieldAvg;

        $insights[] = [
            'label' => 'vs Field Average',
            'value' => ($vsAvg >= 0 ? '+' : '') . number_format($vsAvg, 1),
            'sub' => 'Field avg ' . number_format($fieldAvg, 1),
        ];

        // Badges earned in this match.
        $badges = [];
        if ($shooter->user_id) {
            $badges = \App\Models\UserAchievement::query()
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
        $standingsService = new \App\Services\MatchStandingsService();

        $standings = $standingsService->standardStandings($match);

        $targetSets = $match->targetSets()
            ->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();

        $allGongIds = $targetSets->flatMap(fn ($ts) => $ts->gongs->pluck('id'));

        $shooterIds = $standings->pluck('shooter_id');
        $scores = Score::query()
            ->whereIn('shooter_id', $shooterIds)
            ->whereIn('gong_id', $allGongIds)
            ->get()
            ->groupBy('shooter_id');

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
