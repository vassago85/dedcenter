<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // ══════════════════════════════════════════
            // PRS Badges
            // ══════════════════════════════════════════

            // ── PRS Lifetime (earned once, kept forever) ──
            ['slug' => 'prs-full-send',     'label' => 'Full Send',         'description' => 'Completed a stage with all impacts and no shots not taken.',                                             'category' => 'lifetime', 'scope' => 'stage', 'is_repeatable' => false, 'sort_order' => 10,  'competition_type' => 'prs'],
            ['slug' => 'no-drop-stage',     'label' => 'No Drop Stage',     'description' => 'Dropped only one point on a PRS stage while completing all shots.',                                       'category' => 'lifetime', 'scope' => 'stage', 'is_repeatable' => false, 'sort_order' => 20,  'competition_type' => 'prs'],
            ['slug' => 'impact-chain',      'label' => 'Impact Chain',      'description' => 'Built a streak of consecutive hits on a single stage.',                                                   'category' => 'lifetime', 'scope' => 'stage', 'is_repeatable' => false, 'sort_order' => 30,  'competition_type' => 'prs'],
            ['slug' => 'high-efficiency',   'label' => 'High Efficiency',   'description' => 'Achieved 80% or higher hit rate on shots taken across the match.',                                        'category' => 'lifetime', 'scope' => 'stage', 'is_repeatable' => false, 'sort_order' => 40,  'competition_type' => 'prs'],
            ['slug' => 'early-bird',        'label' => 'Early Bird',        'description' => 'First shooter to register for a PRS match.',                                                               'category' => 'lifetime', 'scope' => 'match', 'is_repeatable' => false, 'sort_order' => 50,  'competition_type' => 'prs'],
            ['slug' => 'iron-shooter',      'label' => 'Iron Shooter',      'description' => 'Maintained 80% or higher hit rate on every stage in the match.',                                          'category' => 'lifetime', 'scope' => 'match', 'is_repeatable' => false, 'sort_order' => 60,  'competition_type' => 'prs'],
            ['slug' => 'complete-shooter',  'label' => 'Complete Shooter',  'description' => 'Completed the full PRS match without any shots not taken and finished on 75% or higher overall.',          'category' => 'lifetime', 'scope' => 'match', 'is_repeatable' => false, 'sort_order' => 70,  'competition_type' => 'prs'],

            // ── PRS Repeatable (podiums stack across matches) ──
            ['slug' => 'podium-gold',       'label' => 'Podium Gold',       'description' => 'Won the PRS match.',                                                                                      'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true,  'sort_order' => 80,  'competition_type' => 'prs'],
            ['slug' => 'podium-silver',     'label' => 'Podium Silver',     'description' => 'Finished second overall at a PRS match.',                                                                 'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true,  'sort_order' => 90,  'competition_type' => 'prs'],
            ['slug' => 'podium-bronze',     'label' => 'Podium Bronze',     'description' => 'Finished third overall at a PRS match.',                                                                  'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true,  'sort_order' => 100, 'competition_type' => 'prs'],

            // ── PRS First-time Milestones ──
            ['slug' => 'first-full-send',     'label' => 'First Full Send',     'description' => 'Earned your first Full Send.',                  'category' => 'lifetime', 'scope' => 'lifetime', 'is_repeatable' => false, 'sort_order' => 200, 'competition_type' => 'prs'],
            ['slug' => 'first-podium',        'label' => 'First Podium',        'description' => 'Reached the podium for the first time.',        'category' => 'lifetime', 'scope' => 'lifetime', 'is_repeatable' => false, 'sort_order' => 210, 'competition_type' => 'prs'],
            ['slug' => 'first-win',           'label' => 'First Win',           'description' => 'Won your first PRS match.',                     'category' => 'lifetime', 'scope' => 'lifetime', 'is_repeatable' => false, 'sort_order' => 220, 'competition_type' => 'prs'],
            ['slug' => 'first-impact-chain',  'label' => 'First Impact Chain',  'description' => 'Recorded your first major hit streak.',         'category' => 'lifetime', 'scope' => 'lifetime', 'is_repeatable' => false, 'sort_order' => 230, 'competition_type' => 'prs'],

            // ── PRS Match Special ──
            ['slug' => 'deadcenter', 'label' => 'DeadCenter', 'description' => 'Recorded the fastest clean run on the compulsory timed tiebreaker stage. Awarded once per match, or not at all.', 'category' => 'match_special', 'scope' => 'match', 'is_repeatable' => false, 'sort_order' => 300, 'competition_type' => 'prs'],

            // ══════════════════════════════════════════
            // Royal Flush Badges
            // ══════════════════════════════════════════

            // ── Royal Flush Lifetime (earned once, kept forever) ──
            ['slug' => 'royal-flush',       'label' => 'Royal Flush',       'description' => 'Achieved a Royal Flush by hitting every target at a distance.',  'category' => 'lifetime', 'scope' => 'stage', 'is_repeatable' => false, 'sort_order' => 10,  'competition_type' => 'royal_flush'],
            ['slug' => 'flush-collector',   'label' => 'Flush Collector',   'description' => 'Earned Royal Flushes at multiple distances in a single match.',  'category' => 'lifetime', 'scope' => 'match', 'is_repeatable' => false, 'sort_order' => 20,  'competition_type' => 'royal_flush'],

            // ── Royal Flush Repeatable (distance flushes + sniper stack per season) ──
            ['slug' => 'flush-400',         'label' => '400m Flush',        'description' => 'Hit every target at 400 metres.',                                'category' => 'repeatable', 'scope' => 'stage', 'is_repeatable' => true,  'sort_order' => 11,  'competition_type' => 'royal_flush'],
            ['slug' => 'flush-500',         'label' => '500m Flush',        'description' => 'Hit every target at 500 metres.',                                'category' => 'repeatable', 'scope' => 'stage', 'is_repeatable' => true,  'sort_order' => 12,  'competition_type' => 'royal_flush'],
            ['slug' => 'flush-600',         'label' => '600m Flush',        'description' => 'Hit every target at 600 metres.',                                'category' => 'repeatable', 'scope' => 'stage', 'is_repeatable' => true,  'sort_order' => 13,  'competition_type' => 'royal_flush'],
            ['slug' => 'flush-700',         'label' => '700m Flush',        'description' => 'Hit every target at 700 metres.',                                'category' => 'repeatable', 'scope' => 'stage', 'is_repeatable' => true,  'sort_order' => 14,  'competition_type' => 'royal_flush'],
            ['slug' => 'small-gong-sniper', 'label' => 'Small Gong Sniper', 'description' => 'Hit the smallest target at the furthest distance.',             'category' => 'repeatable', 'scope' => 'stage', 'is_repeatable' => true,  'sort_order' => 30,  'competition_type' => 'royal_flush'],

            // ── Royal Flush Lifetime ──
            ['slug' => 'first-flush', 'label' => 'First Flush', 'description' => 'Achieved your first Royal Flush.', 'category' => 'lifetime', 'scope' => 'lifetime', 'is_repeatable' => false, 'sort_order' => 200, 'competition_type' => 'royal_flush'],

            // ── Royal Flush Repeatable (podiums stack across matches) ──
            ['slug' => 'rf-podium-gold',   'label' => 'Podium Gold',   'description' => 'Won the Royal Flush match.',                  'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true, 'sort_order' => 80,  'competition_type' => 'royal_flush'],
            ['slug' => 'rf-podium-silver', 'label' => 'Podium Silver', 'description' => 'Finished second overall at a Royal Flush match.', 'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true, 'sort_order' => 90,  'competition_type' => 'royal_flush'],
            ['slug' => 'rf-podium-bronze', 'label' => 'Podium Bronze', 'description' => 'Finished third overall at a Royal Flush match.',  'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true, 'sort_order' => 100, 'competition_type' => 'royal_flush'],

            // ── Royal Flush Match Special ──
            // `perfect-hand` is repeatable by design: it stacks once per match for
            // each shooter who goes perfect. The match-level uniqueness is enforced
            // in AchievementService::evaluatePerfectHand() via hasMatchBadge(), the
            // same guard rf-podium-* uses. Sort 290 so it reads ABOVE winning-hand
            // on the preview page — it is the rarer achievement.
            ['slug' => 'perfect-hand', 'label' => 'Perfect Hand',  'description' => 'Hit every target at every distance — a flawless Royal Flush run. Almost unheard of.',                                    'category' => 'match_special', 'scope' => 'match', 'is_repeatable' => true,  'sort_order' => 290, 'competition_type' => 'royal_flush'],
            ['slug' => 'winning-hand', 'label' => 'Winning Hand', 'description' => 'Won the side bet by hitting the most small gongs across all distances. Awarded once per match, or not at all.', 'category' => 'match_special', 'scope' => 'match', 'is_repeatable' => false, 'sort_order' => 300, 'competition_type' => 'royal_flush'],
        ];

        foreach ($badges as $badge) {
            Achievement::updateOrCreate(
                ['slug' => $badge['slug']],
                $badge,
            );
        }

        Achievement::where('slug', 'first-blood')->update(['is_active' => false]);
    }
}
