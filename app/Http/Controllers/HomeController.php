<?php

namespace App\Http\Controllers;

use App\Models\FeaturedItem;
use App\Models\Organization;
use App\Models\ShootingMatch;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        if (auth()->check()) {
            return auth()->user()->isOwner()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('dashboard');
        }

        $context = domain_context();

        if ($context === 'app') {
            return redirect()->route('login');
        }

        if ($context === 'md') {
            return view('md-home');
        }

        return view('shooter-home', $this->shooterData());
    }

    private function shooterData(): array
    {
        $featuredMatches = FeaturedItem::with('item')
            ->active()
            ->ofType('match')
            ->inPlacement('featured')
            ->ordered()
            ->take(6)
            ->get()
            ->pluck('item')
            ->filter();

        $featuredOrgs = FeaturedItem::with('item')
            ->active()
            ->ofType('organization')
            ->inPlacement('featured')
            ->ordered()
            ->take(6)
            ->get()
            ->pluck('item')
            ->filter();

        $upcomingMatches = ShootingMatch::where('status', 'active')
            ->where('date', '>=', now())
            ->orderBy('date')
            ->with('organization')
            ->take(6)
            ->get();

        $recentResults = ShootingMatch::where('status', 'completed')
            ->orderByDesc('date')
            ->with('organization')
            ->take(6)
            ->get();

        $popularMatches = ShootingMatch::where('status', 'active')
            ->where('date', '>=', now())
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
