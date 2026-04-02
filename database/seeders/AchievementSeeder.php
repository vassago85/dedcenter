<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // ── Repeatable / Stacking ──
            ['slug' => 'prs-full-send',     'label' => 'Full Send',         'description' => 'Completed a stage with all impacts and no shots not taken.',                                                        'category' => 'repeatable', 'scope' => 'stage', 'is_repeatable' => true,  'sort_order' => 10],
            ['slug' => 'no-drop-stage',     'label' => 'No Drop Stage',     'description' => 'Dropped only one point on a PRS stage while completing all shots.',                                                  'category' => 'repeatable', 'scope' => 'stage', 'is_repeatable' => true,  'sort_order' => 20],
            ['slug' => 'impact-chain',      'label' => 'Impact Chain',      'description' => 'Built a streak of consecutive hits on a single stage.',                                                              'category' => 'repeatable', 'scope' => 'stage', 'is_repeatable' => true,  'sort_order' => 30],
            ['slug' => 'high-efficiency',   'label' => 'High Efficiency',   'description' => 'Maintained a very high hit percentage on shots taken.',                                                              'category' => 'repeatable', 'scope' => 'stage', 'is_repeatable' => true,  'sort_order' => 40],
            ['slug' => 'first-blood',       'label' => 'First Blood',       'description' => 'Finished scoring before anyone else at the match.',                                                                  'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true,  'sort_order' => 50],
            ['slug' => 'iron-shooter',      'label' => 'Iron Shooter',      'description' => 'Maintained 80% or higher hit rate on every stage in the match.',                                                     'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true,  'sort_order' => 60],
            ['slug' => 'complete-shooter',  'label' => 'Complete Shooter',  'description' => 'Completed the full PRS match without any shots not taken and finished on 75% or higher overall.',                     'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true,  'sort_order' => 70],
            ['slug' => 'podium-gold',       'label' => 'Podium Gold',       'description' => 'Won the PRS match.',                                                                                                 'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true,  'sort_order' => 80],
            ['slug' => 'podium-silver',     'label' => 'Podium Silver',     'description' => 'Finished second overall at a PRS match.',                                                                            'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true,  'sort_order' => 90],
            ['slug' => 'podium-bronze',     'label' => 'Podium Bronze',     'description' => 'Finished third overall at a PRS match.',                                                                             'category' => 'repeatable', 'scope' => 'match', 'is_repeatable' => true,  'sort_order' => 100],

            // ── Lifetime Milestones ──
            ['slug' => 'first-full-send',     'label' => 'First Full Send',     'description' => 'Earned your first Full Send.',                  'category' => 'lifetime', 'scope' => 'lifetime', 'is_repeatable' => false, 'sort_order' => 200],
            ['slug' => 'first-podium',        'label' => 'First Podium',        'description' => 'Reached the podium for the first time.',        'category' => 'lifetime', 'scope' => 'lifetime', 'is_repeatable' => false, 'sort_order' => 210],
            ['slug' => 'first-win',           'label' => 'First Win',           'description' => 'Won your first PRS match.',                     'category' => 'lifetime', 'scope' => 'lifetime', 'is_repeatable' => false, 'sort_order' => 220],
            ['slug' => 'first-impact-chain',  'label' => 'First Impact Chain',  'description' => 'Recorded your first major hit streak.',         'category' => 'lifetime', 'scope' => 'lifetime', 'is_repeatable' => false, 'sort_order' => 230],

            // ── Match Special ──
            ['slug' => 'deadcenter', 'label' => 'DeadCenter', 'description' => 'Recorded the fastest clean run on the compulsory timed tiebreaker stage. Awarded once per match, or not at all.', 'category' => 'match_special', 'scope' => 'match', 'is_repeatable' => false, 'sort_order' => 300],
        ];

        foreach ($badges as $badge) {
            Achievement::updateOrCreate(
                ['slug' => $badge['slug']],
                $badge,
            );
        }
    }
}
