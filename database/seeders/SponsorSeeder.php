<?php

namespace Database\Seeders;

use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use App\Models\Setting;
use App\Models\Sponsor;
use App\Models\SponsorAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SponsorSeeder extends Seeder
{
    public function run(): void
    {
        $sponsor = Sponsor::firstOrCreate(
            ['slug' => 'precision-optics-sa'],
            [
                'name' => 'Precision Optics SA',
                'website_url' => 'https://precisionoptics.co.za',
                'short_description' => 'Premium optics for precision shooting',
                'active' => true,
                'assignable_by_match_director' => true,
            ]
        );

        SponsorAssignment::firstOrCreate(
            [
                'scope_type' => SponsorScope::Platform,
                'scope_id' => null,
                'placement_key' => PlacementKey::GlobalLeaderboard,
                'sponsor_id' => $sponsor->id,
            ],
            [
                'label_override' => 'Powered by',
                'active' => true,
                'display_order' => 0,
            ]
        );

        SponsorAssignment::firstOrCreate(
            [
                'scope_type' => SponsorScope::Platform,
                'scope_id' => null,
                'placement_key' => PlacementKey::GlobalMatchbook,
                'sponsor_id' => $sponsor->id,
            ],
            [
                'label_override' => 'Presented by',
                'active' => true,
                'display_order' => 0,
            ]
        );

        Setting::set('sponsor_info_overview', 'DeadCenter pairs live scoring with MatchBook Pro digital match books. This page outlines how brands appear across the platform and at events.');
        Setting::set('sponsor_info_visibility', 'Placements include leaderboard headers, results and scoring surfaces, exports, and match book cover/footer/inside pages.');
        Setting::set('sponsor_info_matchbook_section', 'Sponsors can be featured on the generated PDF/HTML match book: cover branding, inside-cover placements, footer strips, and a dedicated sponsors spread.');
        Setting::set('sponsor_info_reach', 'Reach spans registered shooters, match-day visitors on public leaderboards, and downloadable match materials shared before and after the event.');
        Setting::set('sponsor_info_tiers', "Tier 1: Match Presence\nTier 2: Results & Leaderboard Visibility\nTier 3: Sponsored Match Book + Match Visibility\nTier 4: Platform / Series Visibility\n\nPricing available on request.");
        Setting::set('sponsor_info_custom_packages', 'Custom packages can combine digital surfaces with co-branded match books, stage naming, and MD-supplied acknowledgements.');
        Setting::set('sponsor_info_contact', 'For a tailored proposal, contact the platform administrator.');

        if (! Setting::get('sponsor_info_access_token')) {
            Setting::set('sponsor_info_access_token', Str::random(48));
        }
    }
}
