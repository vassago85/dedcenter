<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Models\Achievement;
use App\Models\FeaturedItem;
use App\Models\Organization;
use App\Models\ShootingMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $context = domain_context();

        if ($context === 'app' && ! Auth::check()) {
            return redirect()->route('login');
        }

        if ($context === 'md') {
            return view('md-home');
        }

        return view('shooter-home', $this->shooterData());
    }

    private function shooterData(): array
    {
        $adminFeatured = FeaturedItem::with('item')
            ->active()
            ->ofType('match')
            ->inPlacement('featured')
            ->ordered()
            ->take(6)
            ->get()
            ->pluck('item')
            ->filter();

        $selfFeatured = ShootingMatch::where('featured_status', 'active')
            ->with('organization')
            ->orderBy('date')
            ->take(6)
            ->get();

        $featuredMatches = $adminFeatured->merge($selfFeatured)
            ->unique('id')
            ->take(6);

        $featuredOrgs = FeaturedItem::with('item')
            ->active()
            ->ofType('organization')
            ->inPlacement('featured')
            ->ordered()
            ->take(6)
            ->get()
            ->pluck('item')
            ->filter();

        $allOrganizations = Organization::active()
            ->withCount('matches')
            ->orderBy('name')
            ->get();

        $upcomingStatuses = [
            MatchStatus::PreRegistration->value,
            MatchStatus::RegistrationOpen->value,
            MatchStatus::RegistrationClosed->value,
            MatchStatus::SquaddingOpen->value,
            MatchStatus::Active->value,
        ];

        $upcomingMatches = ShootingMatch::whereIn('status', $upcomingStatuses)
            ->where('date', '>=', now()->startOfDay())
            ->orderBy('date')
            ->with('organization')
            ->take(6)
            ->get();

        $recentResults = ShootingMatch::where('status', MatchStatus::Completed)
            ->orderByDesc('date')
            ->with('organization')
            ->take(6)
            ->get();

        $popularMatches = ShootingMatch::whereIn('status', $upcomingStatuses)
            ->where('date', '>=', now()->startOfDay())
            ->withCount('registrations')
            ->orderByDesc('registrations_count')
            ->with('organization')
            ->take(6)
            ->get();

        $liveMatches = ShootingMatch::query()
            ->activeLiveToday()
            ->withCount('shooters')
            ->orderBy('date', 'desc')
            ->take(6)
            ->get();

        $showcaseBadges = Achievement::query()
            ->where('is_active', true)
            ->where('competition_type', 'prs')
            ->orderByRaw("CASE category WHEN 'match_special' THEN 0 WHEN 'lifetime' THEN 1 WHEN 'repeatable' THEN 2 ELSE 3 END")
            ->orderBy('sort_order')
            ->take(6)
            ->get();

        $activityStats = [
            'registrationsOpen' => ShootingMatch::where('status', MatchStatus::RegistrationOpen)->count(),
            'matchesCompletedSeason' => ShootingMatch::where('status', MatchStatus::Completed)
                ->whereYear('date', now()->year)
                ->count(),
            'activeShootersMonth' => DB::table('shooters')
                ->join('squads', 'squads.id', '=', 'shooters.squad_id')
                ->join('matches', 'matches.id', '=', 'squads.match_id')
                ->whereDate('matches.date', '>=', now()->subDays(30)->startOfDay())
                ->distinct('shooters.user_id')
                ->count('shooters.user_id'),
            'scoresUpdatedAt' => DB::table('scores')->max('updated_at'),
        ];

        return compact(
            'featuredMatches',
            'featuredOrgs',
            'allOrganizations',
            'upcomingMatches',
            'recentResults',
            'popularMatches',
            'liveMatches',
            'showcaseBadges',
            'activityStats',
        );
    }
}
