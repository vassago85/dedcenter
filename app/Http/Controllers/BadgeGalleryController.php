<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use Illuminate\View\View;

class BadgeGalleryController extends Controller
{
    public const BADGE_CONFIG = [
        // PRS badges
        'deadcenter'         => ['icon' => 'deadcenter',   'earnChip' => 'Awarded per match'],
        'prs-full-send'      => ['icon' => 'rocket',       'earnChip' => 'Stackable'],
        'no-drop-stage'      => ['icon' => 'flame',        'earnChip' => 'Stackable'],
        'impact-chain'       => ['icon' => 'link-2',       'earnChip' => 'Stackable'],
        'high-efficiency'    => ['icon' => 'gauge',        'earnChip' => 'Stackable'],
        'first-blood'        => ['icon' => 'zap',          'earnChip' => 'Stackable'],
        'iron-shooter'       => ['icon' => 'shield',       'earnChip' => 'Stackable'],
        'complete-shooter'   => ['icon' => 'circle-check', 'earnChip' => 'Stackable'],
        'podium-gold'        => ['icon' => 'trophy',       'earnChip' => 'Stackable'],
        'podium-silver'      => ['icon' => 'medal',        'earnChip' => 'Stackable'],
        'podium-bronze'      => ['icon' => 'award',        'earnChip' => 'Stackable'],
        'first-full-send'    => ['icon' => 'flag',         'earnChip' => 'Earned once'],
        'first-podium'       => ['icon' => 'podium',       'earnChip' => 'Earned once'],
        'first-win'          => ['icon' => 'crown',        'earnChip' => 'Earned once'],
        'first-impact-chain' => ['icon' => 'git-branch',   'earnChip' => 'Earned once'],

        // Royal Flush badges
        'royal-flush'        => ['icon' => 'crown',        'earnChip' => 'Stackable'],
        'flush-400'          => ['icon' => 'target',       'earnChip' => 'Stackable'],
        'flush-500'          => ['icon' => 'target',       'earnChip' => 'Stackable'],
        'flush-600'          => ['icon' => 'target',       'earnChip' => 'Stackable'],
        'flush-700'          => ['icon' => 'target',       'earnChip' => 'Stackable'],
        'flush-collector'    => ['icon' => 'layers',       'earnChip' => 'Stackable'],
        'small-gong-sniper'  => ['icon' => 'target',       'earnChip' => 'Stackable'],
        'winning-hand'       => ['icon' => 'spade',        'earnChip' => 'Awarded per match'],
        'first-flush'        => ['icon' => 'sparkles',     'earnChip' => 'Earned once'],
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
