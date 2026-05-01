<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use Illuminate\View\View;

class BadgeGalleryController extends Controller
{
    /**
     * Prestige tiers: featured > elite > milestone > earned
     * Icons: Lucide name, custom name, or 'dist-XXX' for distance text rendering.
     */
    public const BADGE_CONFIG = [
        // ── PRS ──
        'deadcenter'         => ['icon' => 'deadcenter',   'tier' => 'featured',  'earnChip' => 'Awarded per match'],

        'podium-gold'        => ['icon' => 'medal-1',      'tier' => 'elite',     'earnChip' => 'Stackable'],
        'podium-silver'      => ['icon' => 'medal-2',      'tier' => 'elite',     'earnChip' => 'Stackable'],
        'podium-bronze'      => ['icon' => 'medal-3',      'tier' => 'elite',     'earnChip' => 'Stackable'],
        'first-win'          => ['icon' => 'crown',        'tier' => 'elite',     'earnChip' => 'Earned once'],

        'first-full-send'    => ['icon' => 'flag',         'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'first-podium'       => ['icon' => 'podium',       'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'first-impact-chain' => ['icon' => 'git-branch',   'tier' => 'milestone', 'earnChip' => 'Earned once'],

        'prs-full-send'      => ['icon' => 'rocket',       'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'no-drop-stage'      => ['icon' => 'flame',        'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'impact-chain'       => ['icon' => 'link-2',       'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'high-efficiency'    => ['icon' => 'gauge',        'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'early-bird'         => ['icon' => 'sunrise',       'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'iron-shooter'       => ['icon' => 'shield',       'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'complete-shooter'   => ['icon' => 'circle-check', 'tier' => 'milestone', 'earnChip' => 'Earned once'],

        // ── Royal Flush ──
        'perfect-hand'       => ['icon' => 'chess-queen',  'tier' => 'featured',  'earnChip' => 'Awarded per match'],
        'winning-hand'       => ['icon' => 'spade',        'tier' => 'featured',  'earnChip' => 'Awarded per match'],

        'royal-flush'        => ['icon' => 'crown',        'tier' => 'elite',     'earnChip' => 'Earned once'],
        'flush-collector'    => ['icon' => 'layers',       'tier' => 'elite',     'earnChip' => 'Earned once'],
        'rf-podium-gold'     => ['icon' => 'medal-1',      'tier' => 'elite',     'earnChip' => 'Stackable'],
        'rf-podium-silver'   => ['icon' => 'medal-2',      'tier' => 'elite',     'earnChip' => 'Stackable'],
        'rf-podium-bronze'   => ['icon' => 'medal-3',      'tier' => 'elite',     'earnChip' => 'Stackable'],
        'small-gong-sniper'  => ['icon' => 'target',       'tier' => 'elite',     'earnChip' => 'Stackable'],

        'first-flush'        => ['icon' => 'sparkles',     'tier' => 'milestone', 'earnChip' => 'Earned once'],

        'flush-700'          => ['icon' => 'dist-700',     'tier' => 'featured',  'earnChip' => 'Stackable'],
        'flush-600'          => ['icon' => 'dist-600',     'tier' => 'elite',     'earnChip' => 'Stackable'],
        'flush-500'          => ['icon' => 'dist-500',     'tier' => 'milestone', 'earnChip' => 'Stackable'],
        'flush-400'          => ['icon' => 'dist-400',     'tier' => 'earned',    'earnChip' => 'Stackable'],
    ];

    public const SECTION_ICONS = [
        'prs'         => 'target',
        'royal_flush' => 'crown',
    ];

    /**
     * Plain-English earning criteria per badge slug. Surface this on the
     * scoreboard badge popover, the Badges Awarded tab, shooter profile and
     * the public badge gallery so shooters and match directors can always
     * see EXACTLY what a badge required. The short `Achievement::description`
     * is the headline; the entries below are the full "how to earn" detail.
     */
    public const DETAILED_CRITERIA = [
        // ── PRS stage-scoped ──
        'prs-full-send'      => 'Complete a stage with every shot on target and zero shots skipped or left untaken. Evaluated the moment the stage is scored.',
        'no-drop-stage'      => 'Drop exactly one impact on a stage while taking every shot — no skipped or untaken rounds. Awarded once per stage, lifetime.',
        'impact-chain'       => 'String together 5 or more consecutive hits on a single stage without a miss or skipped shot in between. The streak length is recorded on the badge.',
        'high-efficiency'    => 'Achieve 80 % or higher hit rate on the shots you actually took during a stage (hits ÷ shots taken). Not-taken shots do not count against you here.',

        // ── PRS match-scoped ──
        'iron-shooter'       => 'Maintain an 80 % or higher hit rate on every stage in the match. A single sub-80 % stage kills it — consistency is the whole point.',
        'complete-shooter'   => 'Finish the match with ZERO not-taken shots and an overall hit rate of 75 % or better. Rewards shooters who engage every target, even the hard ones.',
        'early-bird'         => 'Be the first shooter to register for a PRS match. One award per match.',
        'deadcenter'         => 'Record the fastest clean run (all hits, zero not-taken) on the compulsory timed tiebreaker stage. Exactly one winner per match, or nobody at all if nobody goes clean within par time.',

        // ── PRS lifetime firsts ──
        'first-full-send'    => 'Awarded automatically the first time you earn a Full Send. Kept forever.',
        'first-podium'       => 'Awarded the first time you finish 1st, 2nd, or 3rd overall at a PRS match.',
        'first-win'          => 'Awarded the first time you finish 1st overall at a PRS match.',
        'first-impact-chain' => 'Awarded the first time you build a 5+ consecutive-hit streak on any single stage.',

        // ── PRS repeatable ──
        'podium-gold'        => 'Finish 1st overall at a PRS match. Stacks — earn one every match you win.',
        'podium-silver'      => 'Finish 2nd overall at a PRS match. Stacks — each silver is kept on your profile.',
        'podium-bronze'      => 'Finish 3rd overall at a PRS match. Stacks — each bronze is kept on your profile.',

        // ── Royal Flush stage ──
        'royal-flush'        => 'Hit every gong at a given distance during the flush. Awarded per distance per match (e.g. you can flush both 400 m and 600 m in the same match).',
        'flush-400'          => 'Hit every gong at 400 m. Stackable — one per qualifying distance, every match.',
        'flush-500'          => 'Hit every gong at 500 m. Stackable — one per qualifying distance, every match.',
        'flush-600'          => 'Hit every gong at 600 m. Stackable — one per qualifying distance, every match.',
        'flush-700'          => 'Hit every gong at 700 m. Stackable — the rarest distance flush.',

        // ── Royal Flush match-scoped ──
        'flush-collector'    => 'Earn Royal Flushes at two or more different distances in the SAME match. Proves you can read wind at multiple ranges on the day.',
        'perfect-hand'       => 'Hit every gong at every distance in a single Royal Flush match — a flawless run from shortest to longest. Almost unheard of.',
        'winning-hand'       => 'Win the small-gong side bet: record the most small-gong hits across every distance in the match. One winner per match, or nobody if there is a tie with zero hits.',
        'small-gong-sniper'  => 'Hit the smallest gong at the furthest distance in the match. Stackable across matches and seasons.',
        'first-flush'        => 'Awarded the first time you ever achieve a Royal Flush at any distance.',

        // ── Royal Flush podium ──
        'rf-podium-gold'     => 'Finish 1st overall at a Royal Flush match. Stackable.',
        'rf-podium-silver'   => 'Finish 2nd overall at a Royal Flush match. Stackable.',
        'rf-podium-bronze'   => 'Finish 3rd overall at a Royal Flush match. Stackable.',
    ];

    public static function criteriaFor(?string $slug): ?string
    {
        if (! $slug) {
            return null;
        }

        return self::DETAILED_CRITERIA[$slug] ?? null;
    }

    public function __invoke(): View
    {
        $achievements = Achievement::query()
            ->where('is_active', true)
            ->orderBy('competition_type')
            ->orderByRaw("CASE category WHEN 'match_special' THEN 0 WHEN 'lifetime' THEN 1 WHEN 'repeatable' THEN 2 ELSE 3 END")
            ->orderBy('sort_order')
            ->get();

        return view('pages.badge-gallery', [
            'achievements' => $achievements,
            'badgeConfig' => self::BADGE_CONFIG,
            'sectionIcons' => self::SECTION_ICONS,
            'detailedCriteria' => self::DETAILED_CRITERIA,
        ]);
    }
}
