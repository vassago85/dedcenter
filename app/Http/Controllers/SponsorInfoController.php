<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class SponsorInfoController extends Controller
{
    public function show(string $token)
    {
        $storedToken = Setting::get('sponsor_info_access_token');

        abort_unless($storedToken && hash_equals($storedToken, $token), 404);

        $content = [
            'overview' => Setting::get('sponsor_info_overview', ''),
            'visibility' => Setting::get('sponsor_info_visibility', ''),
            'matchbook_section' => Setting::get('sponsor_info_matchbook_section', ''),
            'reach' => Setting::get('sponsor_info_reach', ''),
            'tiers' => Setting::get('sponsor_info_tiers', ''),
            'custom_packages' => Setting::get('sponsor_info_custom_packages', ''),
            'contact' => Setting::get('sponsor_info_contact', ''),
        ];

        return view('sponsor-info', compact('content'));
    }
}
