<?php

use App\Models\ShootingMatch;
use App\Models\Score;
use App\Models\StageTime;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public ShootingMatch $match;
    public ?int $activeDivision = null;
    public ?int $activeCategory = null;
    public ?string $activeAlrhaClass = null;
    public string $activeTab = 'main';
    public ?int $rfDistanceFilter = null;
    public ?int $expandedShooterId = null;
    // Team-scoreboard sub-filter: 'all' shows the Overall leaderboard, any
    // other value is a division-pairing category label like "Minor/Minor",
    // "Minor/Major" or "Major/Major" (Team::divisionCategoryLabel()).
    public string $teamCategoryView = 'all';

    public function toggleExpand(int $id): void
    {
        $this->expandedShooterId = $this->expandedShooterId === $id ? null : $id;
    }

    public function filterDivision(?int $id): void
    {
        $this->activeDivision = $id;
    }

    public function filterCategory(?int $id): void
    {
        $this->activeCategory = $id;
    }

    public function filterAlrhaClass(?string $class): void
    {
        $this->activeAlrhaClass = $class ?: null;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setTeamCategoryView(string $view): void
    {
        $this->teamCategoryView = $view;
    }

    public function with(): array
    {
        $isPrs = $this->match->isPrs();
        $isElr = $this->match->isElr();
        $isAlrha = $this->match->isAlrha();
        $usesElrPipeline = $this->match->usesElrPipeline();

        $elrStandingsById = collect();
        $alrhaData = null;
        if ($isAlrha) {
            $alrhaData = (new \App\Services\Scoring\AlrhaScoringService(
                new \App\Services\Scoring\ELRScoringService()
            ))->calculateStandings(
                $this->match,
                ['division' => $this->activeDivision ? (string) $this->activeDivision : null],
            );
            $alrhaById = [];
            foreach ($alrhaData['per_class'] ?? [] as $block) {
                foreach ($block['standings'] ?? [] as $row) {
                    $alrhaById[$row['id']] = $row;
                }
            }
            $elrStandingsById = collect($alrhaById);
        } elseif ($isElr) {
            $elrData = (new \App\Services\Scoring\ELRScoringService())->calculateStandings(
                $this->match,
                ['division' => $this->activeDivision ? (string) $this->activeDivision : null],
                completedOnly: false,
            );
            $elrStandingsById = collect($elrData['standings'] ?? [])->keyBy('id');
        }

        $usedDivisionIds = $this->match->shooters()->whereNotNull('match_division_id')->distinct()->pluck('match_division_id')->toArray();
        $divisions = $this->match->divisions()->whereIn('id', $usedDivisionIds)->orderBy('sort_order')->get();

        $usedCategoryIds = DB::table('match_category_shooter')
            ->whereIn('shooter_id', $this->match->shooters()->pluck('shooters.id'))
            ->distinct()
            ->pluck('match_category_id')
            ->toArray();
        $categories = $this->match->categories()->whereIn('id', $usedCategoryIds)->orderBy('sort_order')->get();

        $alrhaClasses = $isAlrha ? $this->match->alrhaClasses() : [];
        if ($isAlrha && $this->activeAlrhaClass) {
            $selectedClass = \App\Enums\AlrhaClass::tryFrom($this->activeAlrhaClass);
            if ($selectedClass) {
                $allowedSlugs = $selectedClass->categorySlugs();
                $categories = $categories->filter(
                    fn ($c) => in_array(strtolower((string) $c->slug), $allowedSlugs, true)
                )->values();
            }
        }

        $shooterTimes = [];
        $tbHits = [];
        $tbTimes = [];
        $totalTargets = 0;

        if ($isPrs) {
            $targetSets = $this->match->targetSets()->get();
            $targetSetIds = $targetSets->pluck('id');
            $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);
            $totalTargets = \App\Models\Gong::whereIn('target_set_id', $targetSetIds)->count();

            $shooterTimes = \App\Models\PrsStageResult::where('match_id', $this->match->id)
                ->whereNotNull('official_time_seconds')
                ->select('shooter_id', DB::raw('SUM(official_time_seconds) as total_time'))
                ->groupBy('shooter_id')
                ->pluck('total_time', 'shooter_id')
                ->toArray();

            if ($tiebreakerStage) {
                $tbResults = \App\Models\PrsStageResult::where('match_id', $this->match->id)
                    ->where('stage_id', $tiebreakerStage->id)
                    ->get();

                $tbHits = $tbResults->pluck('hits', 'shooter_id')
                    ->map(fn ($v) => (int) $v)
                    ->toArray();

                $tbTimes = $tbResults
                    ->filter(fn ($r) => $r->official_time_seconds !== null)
                    ->pluck('official_time_seconds', 'shooter_id')
                    ->map(fn ($v) => (float) $v)
                    ->toArray();
            }
        }

        $shooterQuery = $this->match->shooters()
            ->with(['squad', 'division', 'user:id,email,name']);

        if (!$isPrs && !$usesElrPipeline) {
            $shooterQuery->withCount([
                'scores as hits_count' => fn ($q) => $q->where('is_hit', true),
                'scores as misses_count' => fn ($q) => $q->where('is_hit', false),
            ]);
        }

        if ($isAlrha && $this->activeAlrhaClass) {
            $shooterQuery->where('shooters.alrha_class', $this->activeAlrhaClass);
        }

        if ($this->activeDivision) {
            $shooterQuery->where('shooters.match_division_id', $this->activeDivision);
        }

        if ($this->activeCategory) {
            $catShooterIds = DB::table('match_category_shooter')
                ->where('match_category_id', $this->activeCategory)
                ->pluck('shooter_id')->toArray();
            $shooterQuery->whereIn('shooters.id', $catShooterIds);
        }

        $prsHitsMap = [];
        $prsMissesMap = [];
        if ($isPrs) {
            $prsAgg = \App\Models\PrsStageResult::where('match_id', $this->match->id)
                ->select('shooter_id', DB::raw('SUM(hits) as total_hits'), DB::raw('SUM(misses) as total_misses'))
                ->groupBy('shooter_id')
                ->get();
            foreach ($prsAgg as $row) {
                $prsHitsMap[$row->shooter_id] = (int) $row->total_hits;
                $prsMissesMap[$row->shooter_id] = (int) $row->total_misses;
            }
        }

        $prsShots = collect();
        $prsTargetSets = collect();
        if ($isPrs) {
            foreach ($targetSets as $ts) {
                $ts->gongs_count = \App\Models\Gong::where('target_set_id', $ts->id)->count();
            }
            $prsTargetSets = $targetSets;
            $prsShots = \App\Models\PrsShotScore::where('match_id', $this->match->id)
                ->orderBy('shot_number')
                ->get()
                ->groupBy('shooter_id');
        }

        $shooters = $shooterQuery->get()
            ->map(function ($shooter) use ($isPrs, $usesElrPipeline, $elrStandingsById, $shooterTimes, $tbHits, $tbTimes, $totalTargets, $prsHitsMap, $prsMissesMap, $prsShots) {
                if ($isPrs) {
                    $shooter->hits_count = $prsHitsMap[$shooter->id] ?? 0;
                    $shooter->misses_count = $prsMissesMap[$shooter->id] ?? 0;
                    $shooter->display_score = $shooter->hits_count;
                    $shooter->display_time = (float) ($shooterTimes[$shooter->id] ?? 0);
                    $shooter->tb_hits = $tbHits[$shooter->id] ?? 0;
                    $shooter->tb_time = (float) ($tbTimes[$shooter->id] ?? 0);
                    $shooter->not_taken = $totalTargets - $shooter->hits_count - $shooter->misses_count;

                    $shooterShotList = $prsShots->get($shooter->id, collect());
                    $grid = [];
                    foreach ($shooterShotList as $shot) {
                        $result = $shot->result instanceof \BackedEnum ? $shot->result->value : (string) $shot->result;
                        $grid[$shot->stage_id][$shot->shot_number] = $result;
                    }
                    $shooter->shot_grid = $grid;
                } elseif ($usesElrPipeline) {
                    $row = $elrStandingsById->get($shooter->id, []);
                    $shooter->display_score = (float) ($row['total_points'] ?? 0);
                    $shooter->hits_count = (int) ($row['total_hits'] ?? 0);
                    $shooter->misses_count = max(0, (int) ($row['shots_fired'] ?? 0) - $shooter->hits_count);
                    $shooter->display_time = 0;
                    $shooter->elr_stages = $row['stages'] ?? [];
                    // ELR-native columns surfaced on the public scoreboard —
                    // the same fields the scoring-app ELR table shows so
                    // spectators see the sport's real prize columns, not a
                    // gong-count fallback.
                    $shooter->first_round_hits = (int) ($row['first_round_hits'] ?? 0);
                    $shooter->furthest_hit_m = (int) ($row['furthest_hit_m'] ?? 0);
                    $shooter->normalized_score = $row['normalized_score'] ?? null;
                } else {
                    $shooter->display_score = (float) $shooter->scores()
                        ->where('is_hit', true)
                        ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
                        ->leftJoin('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
                        ->sum(DB::raw('COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier'));
                    $shooter->display_time = 0;
                }
                return $shooter;
            });

        if ($isPrs) {
            $shooters = $shooters->sort(function ($a, $b) {
                if ($a->display_score !== $b->display_score) return $b->display_score <=> $a->display_score;
                if ($a->tb_hits !== $b->tb_hits) return $b->tb_hits <=> $a->tb_hits;

                // A missing timed-stage time must LOSE the tiebreaker, not win
                // it. tb_time / display_time are stored as 0 when no time was
                // recorded (0 also renders as "—"); a raw ascending compare
                // treated 0 as the fastest possible time, so a shooter whose
                // Stage 4 time never pulled through jumped ahead of shooters
                // who legitimately posted a time. Coalesce 0 → +∞ here so this
                // page matches the API scoreboard + Full Match Report ranking.
                $aTb = $a->tb_time > 0 ? $a->tb_time : PHP_FLOAT_MAX;
                $bTb = $b->tb_time > 0 ? $b->tb_time : PHP_FLOAT_MAX;
                if ($aTb !== $bTb) return $aTb <=> $bTb;

                $aAgg = $a->display_time > 0 ? $a->display_time : PHP_FLOAT_MAX;
                $bAgg = $b->display_time > 0 ? $b->display_time : PHP_FLOAT_MAX;
                return $aAgg <=> $bAgg;
            })->values();

            $maxPrsHits = (int) $shooters->max('hits_count');
            foreach ($shooters as $s) {
                $s->prs_points = $maxPrsHits > 0
                    ? round($s->hits_count / $maxPrsHits * 100, 2)
                    : 0.0;
            }
        } else {
            $shooters = $shooters->sortByDesc('display_score')->values();
        }

        // Pull DQs and no-shows out of the ranking sequence so they don't
        // occupy a podium slot just because they were sorted naturally to the
        // top/middle of the list. They are re-appended at the bottom with a
        // null display_rank so the template can label them DQ / N/S.
        $shooters = $shooters->partition(fn ($s) => ! in_array($s->status ?? 'active', ['dq', 'no_show'], true));
        [$rankedShooters, $nonRankedShooters] = [$shooters[0]->values(), $shooters[1]->values()];
        foreach ($rankedShooters as $i => $s) {
            $s->display_rank = $i + 1;
        }
        foreach ($nonRankedShooters as $s) {
            $s->display_rank = null;
        }
        $shooters = $rankedShooters->concat($nonRankedShooters)->values();

        $royalFlushEnabled = !$isPrs && (bool) $this->match->royal_flush_enabled;
        $royalFlushEntries = collect();
        $rfDistances = collect();

        if ($royalFlushEnabled) {
            $rfTargetSets = $this->match->targetSets()
                ->orderByDesc('distance_meters')
                ->with('gongs')
                ->get();

            $rfDistances = $rfTargetSets->pluck('distance_meters')->map(fn ($d) => (int) $d)->unique()->sortDesc()->values();

            $allShooterIds = $shooters->pluck('id')->toArray();
            $rfGongIds = $rfTargetSets->flatMap(fn ($ts) => $ts->gongs->pluck('id'))->toArray();

            $rfHits = \App\Models\Score::whereIn('gong_id', $rfGongIds)
                ->whereIn('shooter_id', $allShooterIds)
                ->where('is_hit', true)
                ->select('shooter_id', 'gong_id')
                ->get();

            $gongToTs = [];
            foreach ($rfTargetSets as $ts) {
                foreach ($ts->gongs as $g) {
                    $gongToTs[$g->id] = $ts->id;
                }
            }

            $hitCountByShooterTs = [];
            foreach ($rfHits as $hit) {
                $tsId = $gongToTs[$hit->gong_id] ?? null;
                if ($tsId === null) continue;
                $hitCountByShooterTs[$hit->shooter_id][$tsId] =
                    ($hitCountByShooterTs[$hit->shooter_id][$tsId] ?? 0) + 1;
            }

            $rfProfiles = [];
            foreach ($shooters as $s) {
                $flushDistances = [];
                foreach ($rfTargetSets as $ts) {
                    $gongCount = $ts->gongs->count();
                    $hitsAtTs = $hitCountByShooterTs[$s->id][$ts->id] ?? 0;
                    if ($gongCount > 0 && $hitsAtTs >= $gongCount) {
                        $flushDistances[] = (int) $ts->distance_meters;
                    }
                }
                $rfProfiles[] = [
                    'shooter' => $s,
                    'flush_count' => count($flushDistances),
                    'flush_distances' => $flushDistances,
                ];
            }

            if ($this->rfDistanceFilter) {
                $rfProfiles = array_filter($rfProfiles, fn ($p) => in_array($this->rfDistanceFilter, $p['flush_distances']));
            }

            usort($rfProfiles, function ($a, $b) {
                if ($a['flush_count'] !== $b['flush_count']) return $b['flush_count'] <=> $a['flush_count'];
                $aMax = !empty($a['flush_distances']) ? max($a['flush_distances']) : 0;
                $bMax = !empty($b['flush_distances']) ? max($b['flush_distances']) : 0;
                if ($aMax !== $bMax) return $bMax <=> $aMax;
                return $b['shooter']->display_score <=> $a['shooter']->display_score;
            });

            $royalFlushEntries = collect(array_values($rfProfiles))->map(fn ($p, $i) => (object) [
                'rank' => $i + 1,
                'name' => $p['shooter']->name,
                'user_id' => $p['shooter']->user_id,
                'squad_name' => $p['shooter']->squad?->name ?? '—',
                'flush_count' => $p['flush_count'],
                'flush_distances' => $p['flush_distances'],
                'total_score' => $p['shooter']->display_score,
            ]);
        }

        // Side-bet standings — surfaced publicly on the scoreboard so spectators
        // can see the overall side-bet winner/leader without needing MD access.
        // Uses the same cascading tiebreaker as the MD scoring app + side-bet
        // report (smallest gong first, then furthest distances, cascading down).
        $sideBetEnabled = $royalFlushEnabled && (bool) $this->match->side_bet_enabled;
        $sideBetStandings = collect();
        $sideBetGongLabels = [];
        $sideBetWinner = null;
        $sideBetTied = false;
        if ($sideBetEnabled) {
            $sb = (new \App\Services\SideBetStandingsService())->build($this->match);
            $sideBetStandings = collect($sb['entries'] ?? []);
            $sideBetGongLabels = $sb['gong_labels'] ?? [];
            if ($sideBetStandings->isNotEmpty()) {
                $sideBetWinner = $sideBetStandings->first();
                // A pure top tie means no outright winner — the service reports
                // this as a "Tied — matched on every gong" reason on the leader.
                $sideBetTied = str_starts_with((string) ($sideBetWinner['tiebreaker_reason'] ?? ''), 'Tied');
            }
        }

        $isStandard = !$isPrs && !$usesElrPipeline;
        $detailedData = collect();
        $targetSetDetails = collect();

        if ($isStandard) {
            $targetSetsForDetail = $this->match->targetSets()
                ->orderBy('sort_order')
                ->with(['gongs' => fn ($q) => $q->orderBy('number')])
                ->get();

            $targetSetDetails = $targetSetsForDetail;

            $allGongIds = $targetSetsForDetail->flatMap(fn ($ts) => $ts->gongs->pluck('id'));
            $allScores = Score::whereIn('gong_id', $allGongIds)
                ->whereIn('shooter_id', $shooters->pluck('id'))
                ->get()
                ->keyBy(fn ($s) => "{$s->shooter_id}-{$s->gong_id}");

            $detailedData = $shooters->map(function ($shooter) use ($targetSetsForDetail, $allScores) {
                $distances = [];
                $totalHits = 0;
                $totalMisses = 0;
                $totalScore = 0;

                foreach ($targetSetsForDetail as $ts) {
                    $distMult = (float) ($ts->distance_multiplier ?? 1);
                    $gongs = [];
                    $distHits = 0;
                    $distMisses = 0;
                    $distSubtotal = 0;

                    foreach ($ts->gongs as $gong) {
                        $score = $allScores->get("{$shooter->id}-{$gong->id}");
                        $points = round($distMult * $gong->multiplier, 2);
                        $isHit = $score ? (bool) $score->is_hit : null;

                        if ($score) {
                            if ($isHit) {
                                $distHits++;
                                $totalHits++;
                                $distSubtotal += $points;
                                $totalScore += $points;
                            } else {
                                $distMisses++;
                                $totalMisses++;
                            }
                        }

                        $gongs[] = (object) [
                            'gong_id' => $gong->id,
                            'number' => $gong->number,
                            'label' => $gong->label,
                            'multiplier' => $gong->multiplier,
                            'points' => $points,
                            'is_hit' => $isHit,
                        ];
                    }

                    $distances[$ts->id] = (object) [
                        'target_set_id' => $ts->id,
                        'label' => $ts->label,
                        'distance_meters' => $ts->distance_meters,
                        'hits' => $distHits,
                        'misses' => $distMisses,
                        'subtotal' => round($distSubtotal, 2),
                        'gongs' => $gongs,
                    ];
                }

                return (object) [
                    'shooter' => $shooter,
                    'distances' => $distances,
                    'total_hits' => $totalHits,
                    'total_misses' => $totalMisses,
                    'total_score' => round($totalScore, 2),
                ];
            })->sortByDesc('total_score')->values();
        }

        $matchBadges = collect();
        if ($isPrs || $royalFlushEnabled) {
            $competitionType = $isPrs ? 'prs' : 'royal_flush';
            $matchBadges = \App\Models\UserAchievement::where('match_id', $this->match->id)
                ->whereHas('achievement', fn ($q) => $q->where('competition_type', $competitionType))
                ->with([
                    'achievement',
                    'user:id,name',
                    'stage:id,label,stage_number,distance_meters,is_tiebreaker',
                    'shooter:id,name',
                ])
                ->orderBy('awarded_at')
                ->get();
        }

        // Custom registration field values visible on scoreboard
        $scoreboardFields = $this->match->customFields()
            ->where('show_on_scoreboard', true)
            ->orderBy('sort_order')
            ->get();

        $customFieldMap = [];
        if ($scoreboardFields->isNotEmpty()) {
            $registrations = \App\Models\MatchRegistration::where('match_id', $this->match->id)
                ->with(['customValues' => fn ($q) => $q->whereIn('match_custom_field_id', $scoreboardFields->pluck('id'))])
                ->get()
                ->keyBy('user_id');

            foreach ($shooters as $s) {
                if (! $s->user_id) continue;
                $reg = $registrations->get($s->user_id);
                if (! $reg) continue;
                $values = [];
                foreach ($reg->customValues as $cv) {
                    $field = $scoreboardFields->firstWhere('id', $cv->match_custom_field_id);
                    if ($field && $cv->value) {
                        $values[] = ['label' => $field->label, 'value' => $cv->value];
                    }
                }
                if (! empty($values)) {
                    $customFieldMap[$s->id] = $values;
                }
            }
        }

        $isTeamEvent = $this->match->isTeamEvent();
        $teamLeaderboard = collect();
        $teamCategories = collect();
        if ($isTeamEvent) {
            $teams = $this->match->teams()
                ->with(['shooters' => fn ($q) => $q->with(['squad', 'division'])])
                ->orderBy('sort_order')
                ->get();

            // For ELR matches build a per-shooter ELR points map (sum of hit
            // points_awarded) once and reuse it across teams — beats N+1
            // ELRScoringService calls when there are many teams.
            $elrPointsByShooter = collect();
            if ($isElr) {
                $shooterIds = $teams->flatMap(fn ($t) => $t->shooters->pluck('id'))->unique()->values();
                if ($shooterIds->isNotEmpty()) {
                    $elrPointsByShooter = \App\Models\ElrShot::query()
                        ->whereIn('shooter_id', $shooterIds)
                        ->where('result', \App\Enums\ElrShotResult::Hit->value)
                        ->select('shooter_id', \Illuminate\Support\Facades\DB::raw('SUM(points_awarded) as pts'))
                        ->groupBy('shooter_id')
                        ->pluck('pts', 'shooter_id')
                        ->map(fn ($v) => (float) $v);
                }
            }

            $teamLeaderboard = $teams->map(function ($team) use ($shooters, $isPrs, $isElr, $elrPointsByShooter) {
                $memberScores = $team->shooters->map(function ($member) use ($shooters, $isElr, $elrPointsByShooter) {
                    // ELR uses real ELR points (not the gong-based display_score
                    // accessor, which is always 0 for ELR matches). Standard/PRS
                    // keep using display_score so the team tab matches the per-
                    // shooter scoreboard on those scoring types.
                    if ($isElr) {
                        $score = (float) ($elrPointsByShooter[$member->id] ?? 0);
                    } else {
                        $scored = $shooters->firstWhere('id', $member->id);
                        $score = $scored?->display_score ?? 0;
                    }
                    return (object) [
                        'name' => $member->name,
                        'score' => $score,
                    ];
                })->sortByDesc('score')->values();

                return (object) [
                    'team' => $team,
                    'members' => $memberScores,
                    'total_score' => $memberScores->sum('score'),
                    'member_count' => $memberScores->count(),
                    'category' => $team->divisionCategoryLabel(),
                ];
            })->sortByDesc('total_score')->values();

            // Group teams by division-pairing category for the Peregrine-style
            // category breakdown shown above the leaderboard. Null category
            // (no divisions configured / Forster) collapses to a single
            // "All teams" group automatically because the view only renders
            // categories when this collection is non-empty.
            $teamCategories = $teamLeaderboard
                ->filter(fn ($e) => $e->category !== null)
                ->groupBy('category')
                ->map(fn ($group) => $group->sortByDesc('total_score')->values())
                ->sortKeys();
        }

        // Unclaimed-results count: shooters whose row hasn't been claimed by
        // a real DeadCenter account yet (walk-ins with no user_id AND
        // imported rows linked to an @import.invalid placeholder user) and
        // who aren't DQ/no-show. Drives the "Claim your result" banner +
        // per-row chips on all scoreboard tabs. See Shooter::isUnclaimedResult().
        $unclaimedCount = $shooters
            ->filter(fn ($s) => $s->isUnclaimedResult() && ! in_array($s->status ?? 'active', ['dq', 'no_show'], true))
            ->count();

        // Live stage-progress summary — shown on the header while the match
        // is Active so spectators and the MD can see at-a-glance how far
        // through the match we are. A stage is:
        //   - "underway"  → at least one active shooter has a result
        //   - "complete"  → every active (non-DQ/no-show) shooter has a result
        // Percentage is computed against the total shooter × stage grid so
        // partial stages still move the bar.
        $stageProgress = null;
        if ($this->match->status === \App\Enums\MatchStatus::Active) {
            $activeShooterIds = $shooters
                ->filter(fn ($s) => ! in_array($s->status ?? 'active', ['dq', 'no_show'], true))
                ->pluck('id');

            if ($isPrs && isset($targetSets) && $targetSets->count() > 0) {
                $totalStages = $targetSets->count();
                $expectedPerStage = $activeShooterIds->count();

                $scoredByStage = \App\Models\PrsStageResult::where('match_id', $this->match->id)
                    ->whereIn('shooter_id', $activeShooterIds)
                    ->select('stage_id', DB::raw('COUNT(DISTINCT shooter_id) as scored_count'))
                    ->groupBy('stage_id')
                    ->get();

                $stagesUnderway = $scoredByStage->count();
                $stagesComplete = $expectedPerStage > 0
                    ? $scoredByStage->where('scored_count', '>=', $expectedPerStage)->count()
                    : 0;

                $totalCells = $totalStages * max($expectedPerStage, 1);
                $filledCells = (int) $scoredByStage->sum('scored_count');
                $percent = $totalCells > 0 ? (int) round(($filledCells / $totalCells) * 100) : 0;

                $stageProgress = [
                    'total' => $totalStages,
                    'underway' => $stagesUnderway,
                    'complete' => $stagesComplete,
                    'percent' => $percent,
                ];
            } elseif ($isStandard) {
                $standardStages = $this->match->targetSets()->with('gongs:id,target_set_id')->get();
                $totalStages = $standardStages->count();

                if ($totalStages > 0 && $activeShooterIds->count() > 0) {
                    $gongIdByStage = $standardStages->mapWithKeys(fn ($ts) => [$ts->id => $ts->gongs->pluck('id')]);
                    $stageScoreCounts = [];
                    foreach ($standardStages as $ts) {
                        $gongIds = $gongIdByStage[$ts->id];
                        if ($gongIds->isEmpty()) continue;
                        $scoredShooters = \App\Models\Score::whereIn('gong_id', $gongIds)
                            ->whereIn('shooter_id', $activeShooterIds)
                            ->distinct()
                            ->count('shooter_id');
                        $stageScoreCounts[$ts->id] = $scoredShooters;
                    }

                    $stagesUnderway = count(array_filter($stageScoreCounts, fn ($c) => $c > 0));
                    $stagesComplete = count(array_filter($stageScoreCounts, fn ($c) => $c >= $activeShooterIds->count()));

                    $totalCells = $totalStages * $activeShooterIds->count();
                    $filledCells = array_sum($stageScoreCounts);
                    $percent = $totalCells > 0 ? (int) round(($filledCells / $totalCells) * 100) : 0;

                    $stageProgress = [
                        'total' => $totalStages,
                        'underway' => $stagesUnderway,
                        'complete' => $stagesComplete,
                        'percent' => $percent,
                    ];
                }
            }
        }

        // Type-specific dispatch key. Keep /scoreboard/{match} as the
        // single public URL and let a matching include under
        // resources/views/scoreboards/ own the sport's columns, filters
        // and tabs — see scoreboards/*.blade.php.
        $scoreboardVariant = match (true) {
            $isAlrha => 'alrha',
            $isElr => 'elr',
            $isPrs => 'prs',
            default => 'royal-flush',
        };

        return [
            'shooters' => $shooters,
            'isPrs' => $isPrs,
            'isStandard' => $isStandard,
            'scoreboardVariant' => $scoreboardVariant,
            'usesElrPipeline' => $usesElrPipeline,
            'alrhaData' => $alrhaData,
            'divisions' => $divisions,
            'categories' => $categories,
            'royalFlushEnabled' => $royalFlushEnabled,
            'rfDistances' => $rfDistances,
            'royalFlushEntries' => $royalFlushEntries,
            'sideBetEnabled' => $sideBetEnabled,
            'sideBetStandings' => $sideBetStandings,
            'sideBetGongLabels' => $sideBetGongLabels,
            'sideBetWinner' => $sideBetWinner,
            'sideBetTied' => $sideBetTied,
            'detailedData' => $detailedData,
            'targetSetDetails' => $targetSetDetails,
            'prsTargetSets' => $prsTargetSets,
            'matchBadges' => $matchBadges,
            'customFieldMap' => $customFieldMap,
            'isTeamEvent' => $isTeamEvent,
            'teamLeaderboard' => $teamLeaderboard,
            'teamCategories' => $teamCategories,
            'isElr' => $isElr,
            'isAlrha' => $isAlrha,
            'alrhaClasses' => $alrhaClasses,
            'unclaimedCount' => $unclaimedCount,
            'stageProgress' => $stageProgress,
        ];
    }
}; ?>

<div wire:poll.5s class="scoreboard-page min-h-screen min-w-0 max-w-full overflow-x-hidden bg-app text-primary px-3 py-4 sm:px-6 lg:p-10">
    @php
        $scoreboardUser = auth()->user();
        $scoreboardIsMd = $scoreboardUser && $match->organization_id && $scoreboardUser->isOrgMatchDirector($match->organization);
        $scoreboardIsAdmin = $scoreboardUser && $scoreboardUser->isAdmin();
        $scoreboardCanManage = $scoreboardIsMd || $scoreboardIsAdmin;
        // Prefer org-scoped routes when the user is an MD (or owner) of that org;
        // fall back to admin routes for platform admins without the org pivot.
        $scoreboardTabOrg = $scoreboardIsMd ? $match->organization : null;
        // Respect the MD's "hide scores" toggle on the PUBLIC scoreboard too —
        // previously only /live and the API honoured scores_published, so the
        // main scoreboard silently leaked hidden results. Managers still see a
        // full preview so they can review before publishing.
        $scoresVisibleToViewer = $match->scoresArePublic() || $scoreboardCanManage;
        $scoresHiddenPreview = $scoreboardCanManage && ! $match->scoresArePublic();
    @endphp

    @if($scoreboardCanManage)
        <div class="mb-4 print:hidden">
            <x-match-hub-tabs :match="$match" :organization="$scoreboardTabOrg" />
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <h1 class="max-w-full text-2xl font-black leading-tight tracking-tight break-words sm:text-4xl lg:text-5xl">{{ $match->name }}</h1>
                @if($isPrs)
                    <span class="shrink-0 rounded bg-amber-600 px-2 py-1 text-xs font-bold uppercase">PRS</span>
                @endif
                @if($isAlrha)
                    <span class="shrink-0 rounded bg-emerald-700 px-2 py-1 text-xs font-bold uppercase">ALRHA</span>
                @endif
                @if($match->status === \App\Enums\MatchStatus::Active)
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-red-500/10 px-2.5 py-1 sm:px-3">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span>
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-red-500 sm:text-xs">Live</span>
                    </span>
                @endif
            </div>
            <p class="mt-2 text-sm text-muted sm:mt-1 sm:text-lg">
                {{ $match->date?->format('d M Y') }}
                @if($match->location) &mdash; {{ $match->location }} @endif
            </p>
            <p class="mt-0.5 text-xs text-muted/50">Last updated: {{ now()->format('H:i:s') }}</p>

            @if($stageProgress)
                {{-- Live match progress. "Complete" = every active shooter
                     has a result for that stage; "underway" = at least one
                     shooter has scored it. The bar is a true fill-rate of
                     the shooter × stage grid, so partial stages still move
                     the needle. --}}
                <div class="mt-3 inline-flex min-w-0 max-w-full flex-col gap-1.5 rounded-xl border border-border bg-surface/60 px-3 py-2 sm:min-w-[18rem]">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                        <span class="font-bold uppercase tracking-wider text-muted">Match Progress</span>
                        <span class="tabular-nums font-semibold text-primary">
                            {{ $stageProgress['complete'] }} / {{ $stageProgress['total'] }} stages complete
                        </span>
                        @if($stageProgress['underway'] > $stageProgress['complete'])
                            <span class="text-muted">&bull;</span>
                            <span class="tabular-nums text-amber-400">
                                {{ $stageProgress['underway'] - $stageProgress['complete'] }} in progress
                            </span>
                        @endif
                        <span class="ml-auto tabular-nums font-bold text-accent">{{ $stageProgress['percent'] }}%</span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                        <div class="h-full rounded-full bg-gradient-to-r from-red-500 to-accent transition-all duration-500"
                             style="width: {{ $stageProgress['percent'] }}%"></div>
                    </div>
                </div>
            @endif
            <x-powered-by-block feature="results" :match-id="$match->id" variant="inline" />
            @php
                $user = auth()->user();
                $canExport = $user
                    ? ($user->isAdmin() || ($match->organization_id && $user->isOrgMatchDirector($match->organization)))
                    : false;
            @endphp
            @if($match->status === \App\Enums\MatchStatus::Completed && ($match->scoresArePublic() || $canExport))
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('scoreboard.matches.full-match-report', $match) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600/40 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold text-emerald-300 transition-colors hover:border-emerald-500 hover:text-emerald-100">
                        <x-icon name="document-check" class="h-3.5 w-3.5" />
                        Full Match Report
                    </a>
                    @if($canExport)
                        <a href="{{ route('scoreboard.export.standings', $match) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                            <x-icon name="download" class="h-3.5 w-3.5" />
                            Standings CSV
                        </a>
                        <a href="{{ route('scoreboard.export.detailed', $match) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                            <x-icon name="download" class="h-3.5 w-3.5" />
                            Full Results CSV
                        </a>
                    @endif
                </div>
            @endif
            @if($match->royal_flush_enabled && $canExport)
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('scoreboard.export.rf-shots', $match) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                        <x-icon name="download" class="h-3.5 w-3.5" />
                        RF Shots CSV (1/0)
                    </a>
                </div>
            @endif
            @if($match->isElr() && $canExport)
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <a href="{{ route('scoreboard.export.elr-shots', $match) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                        <x-icon name="download" class="h-3.5 w-3.5" />
                        ELR Shots Template (1/0)
                    </a>
                    @if($match->scoresArePublic())
                        <a href="{{ route('scoreboard.export.elr-rankings', $match) }}?view=overall"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                            <x-icon name="download" class="h-3.5 w-3.5" />
                            Rankings CSV
                        </a>
                        <a href="{{ route('scoreboard.export.pdf-elr-rankings', $match) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                            <x-icon name="download" class="h-3.5 w-3.5" />
                            Rankings PDF
                        </a>
                    @endif
                    <span class="text-[11px] text-muted">Fill engaged gongs with 1 = impact, 0 = miss. “—” = gong this division doesn’t shoot.</span>
                </div>
            @endif
        </div>
        <x-app-logo
            size="lg"
            class="shrink-0 self-start opacity-60 max-sm:origin-top-left max-sm:scale-[0.82]"
        />
    </div>

    @if(! $scoresVisibleToViewer)
        {{-- Public "results pending" state. Scores stay private until the MD
             publishes; the entrant list is fine to show. MD/admin bypass this
             and get a full live preview (see the @else branch). --}}
        <div class="mx-auto max-w-2xl">
            <div class="rounded-2xl border border-border bg-surface/60 px-6 py-10 text-center sm:py-14">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-2 ring-1 ring-border">
                    <x-icon name="eye-off" class="h-7 w-7 text-muted" />
                </div>
                <h2 class="text-xl font-black text-primary sm:text-2xl">Results not published yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-muted sm:text-base">
                    @if($match->status === \App\Enums\MatchStatus::Completed)
                        This match is complete. The match director is finalising scores — results will appear here as soon as they’re published.
                    @else
                        Scoring is still underway. Standings will appear here once the match director publishes them.
                    @endif
                </p>
                @if($shooters->isNotEmpty())
                    <div class="mt-6 text-left">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wider text-muted/70">Entered ({{ $shooters->count() }})</p>
                        <div class="grid max-h-72 grid-cols-1 gap-1.5 overflow-y-auto rounded-xl border border-border bg-app/40 p-3 sm:grid-cols-2">
                            @foreach($shooters as $s)
                                <div class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm">
                                    <span class="truncate text-secondary">{{ $s->name }}</span>
                                    <span class="shrink-0 text-xs text-muted/70">{{ $s->squad?->name ?? '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                <p class="mt-6 text-xs text-muted/60">This page updates automatically — keep it open.</p>
            </div>
        </div>
    @else
        @if($scoresHiddenPreview)
            <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/8 px-4 py-3 print:hidden">
                <x-icon name="eye-off" class="mt-0.5 h-5 w-5 shrink-0 text-amber-300" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-amber-200">Preview — scores are hidden from the public</p>
                    <p class="mt-0.5 text-xs text-amber-100/75">You can see full standings because you manage this match. Spectators see a “results not published yet” screen until you publish from the Scoring tab.</p>
                </div>
            </div>
        @endif

    {{-- Filter bars (CLASS / DIV / CAT) live inside each type include so
         each sport owns the filter row it needs. CLASS is ALRHA-only;
         PRS / ELR / Royal Flush render DIV + CAT. --}}

    {{-- Claim-your-result banner — shown on every tab whenever there are
         imported/unclaimed shooters AND the match is live or complete. This
         is the primary path for members to link a result to their account
         until we move registrations fully through DeadCenter. --}}
    @if($unclaimedCount > 0 && ($match->status === \App\Enums\MatchStatus::Completed || $match->status === \App\Enums\MatchStatus::Active))
        <div class="mb-4 flex flex-col gap-3 rounded-2xl border border-accent/30 bg-gradient-to-r from-accent/10 to-accent/5 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-5 sm:px-5 sm:py-4">
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-accent/15 ring-1 ring-accent/30 sm:h-10 sm:w-10">
                    <x-icon name="user-plus" class="h-4 w-4 text-accent sm:h-5 sm:w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-primary sm:text-base">
                        Shoot this match? <span class="text-accent">Claim your result.</span>
                    </p>
                    <p class="mt-0.5 text-xs text-muted sm:text-sm">
                        Find your name below and tap <span class="font-semibold text-primary">Claim</span>. After the match director approves it, the result and any badges appear on your profile.
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2 self-start sm:self-center">
                <div class="flex items-center gap-2 rounded-full bg-app/60 px-3 py-1 ring-1 ring-border">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-muted sm:text-xs">
                        {{ $unclaimedCount }} {{ $unclaimedCount === 1 ? 'result' : 'results' }} awaiting {{ $unclaimedCount === 1 ? 'a claim' : 'claims' }}
                    </span>
                </div>
                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.shooter-claims') }}"
                           class="inline-flex items-center gap-1 rounded-full bg-accent px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-primary hover:bg-accent-hover sm:text-xs">
                            Review claims <x-icon name="chevron-right" class="h-3 w-3" />
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    @endif

    {{-- Dispatch to the sport's scoreboard body. Each include owns
         its own filter chips (CLASS/DIV/CAT), tab bar and tables so
         Royal Flush, PRS, ELR and ALRHA can diverge without dragging
         each other. Shared chrome (page header, publish gate, claim
         banner, footer) stays here in the dispatcher. --}}
    @include('scoreboards.' . $scoreboardVariant)


    @endif {{-- /$scoresVisibleToViewer --}}

    <div class="mt-6 flex flex-col gap-2 text-xs text-muted/60 sm:flex-row sm:items-center sm:justify-between sm:text-sm">
        <span class="min-w-0 leading-snug">
            Auto-refreshes every 5 seconds
            @if($isPrs) &bull; Ranked by total hits, then tiebreaker stage hits, then tiebreaker stage time @endif
        </span>
        <span class="shrink-0">&copy; {{ date('Y') }} DeadCenter</span>
    </div>
</div>
