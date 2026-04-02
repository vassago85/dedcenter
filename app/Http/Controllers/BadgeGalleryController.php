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

        'podium-gold'        => ['icon' => 'trophy',       'tier' => 'elite',     'earnChip' => 'Stackable'],
        'podium-silver'      => ['icon' => 'medal',        'tier' => 'elite',     'earnChip' => 'Stackable'],
        'podium-bronze'      => ['icon' => 'award',        'tier' => 'elite',     'earnChip' => 'Stackable'],
        'first-win'          => ['icon' => 'crown',        'tier' => 'elite',     'earnChip' => 'Earned once'],

        'first-full-send'    => ['icon' => 'flag',         'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'first-podium'       => ['icon' => 'podium',       'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'first-impact-chain' => ['icon' => 'git-branch',   'tier' => 'milestone', 'earnChip' => 'Earned once'],

        'prs-full-send'      => ['icon' => 'rocket',       'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'no-drop-stage'      => ['icon' => 'flame',        'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'impact-chain'       => ['icon' => 'link-2',       'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'high-efficiency'    => ['icon' => 'gauge',        'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'first-blood'        => ['icon' => 'zap',          'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'iron-shooter'       => ['icon' => 'shield',       'tier' => 'milestone', 'earnChip' => 'Earned once'],
        'complete-shooter'   => ['icon' => 'circle-check', 'tier' => 'milestone', 'earnChip' => 'Earned once'],

        // ── Royal Flush ──
        'winning-hand'       => ['icon' => 'spade',        'tier' => 'featured',  'earnChip' => 'Awarded per match'],

        'royal-flush'        => ['icon' => 'crown',        'tier' => 'elite',     'earnChip' => 'Earned once'],
        'flush-collector'    => ['icon' => 'layers',       'tier' => 'elite',     'earnChip' => 'Earned once'],
        'small-gong-sniper'  => ['icon' => 'target',       'tier' => 'elite',     'earnChip' => 'Stackable'],

        'first-flush'        => ['icon' => 'sparkles',     'tier' => 'milestone', 'earnChip' => 'Earned once'],

        'flush-400'          => ['icon' => 'dist-400',     'tier' => 'earned',    'earnChip' => 'Stackable'],
        'flush-500'          => ['icon' => 'dist-500',     'tier' => 'earned',    'earnChip' => 'Stackable'],
        'flush-600'          => ['icon' => 'dist-600',     'tier' => 'earned',    'earnChip' => 'Stackable'],
        'flush-700'          => ['icon' => 'dist-700',     'tier' => 'earned',    'earnChip' => 'Stackable'],
    ];

    public const SECTION_ICONS = [
        'prs'         => 'target',
        'royal_flush' => 'crown',
    ];

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
        ]);
    }
}
