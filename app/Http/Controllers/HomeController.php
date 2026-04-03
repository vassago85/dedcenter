<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Models\FeaturedItem;
use App\Models\Organization;
use App\Models\ShootingMatch;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $context = domain_context();

        if ($context === 'app' && ! auth()->check()) {
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

        return compact(
            'featuredMatches',
            'featuredOrgs',
            'upcomingMatches',
            'recentResults',
            'popularMatches',
            'liveMatches',
        );
    }
}
