<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use Illuminate\View\View;

class BadgeGalleryController extends Controller
{
    /**
     * Static emoji map — keep in sync with resources/views/pages/scoreboard.blade.php badge tabs.
     */
    public const BADGE_ICONS = [
        'deadcenter' => '🎯',
        'prs-full-send' => '💥',
        'no-drop-stage' => '🔥',
        'impact-chain' => '⛓️',
        'high-efficiency' => '🎖️',
        'first-blood' => '🩸',
        'iron-shooter' => '🛡️',
        'complete-shooter' => '✅',
        'podium-gold' => '🥇',
        'podium-silver' => '🥈',
        'podium-bronze' => '🥉',
        'first-full-send' => '⭐',
        'first-podium' => '⭐',
        'first-win' => '⭐',
        'first-impact-chain' => '⭐',
        'royal-flush' => '👑',
        'flush-collector' => '🏆',
        'small-gong-sniper' => '🎯',
        'winning-hand' => '🃏',
        'first-flush' => '⭐',
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
            'badgeIcons' => self::BADGE_ICONS,
        ]);
    }
}
