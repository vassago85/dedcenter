<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Models\MatchRegistration;
use App\Models\ShootingMatch;
use Illuminate\Http\Request;

class MemberMatchController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $myMatchIds = MatchRegistration::where('user_id', $userId)
            ->pluck('match_id');

        $fields = ['id', 'name', 'date', 'location', 'status', 'scoring_type', 'organization_id'];

        $live = ShootingMatch::whereIn('id', $myMatchIds)
            ->activeLiveToday()
            ->select($fields)
            ->withCount('shooters')
            ->with('organization:id,name')
            ->latest('date')
            ->get();

        $upcoming = ShootingMatch::whereIn('id', $myMatchIds)
            ->whereIn('status', [
                MatchStatus::PreRegistration,
                MatchStatus::RegistrationOpen,
                MatchStatus::RegistrationClosed,
                MatchStatus::SquaddingOpen,
                MatchStatus::SquaddingClosed,
                MatchStatus::Ready,
            ])
            ->select($fields)
            ->withCount('shooters')
            ->with('organization:id,name')
            ->orderBy('date')
            ->get();

        $recent = ShootingMatch::whereIn('id', $myMatchIds)
            ->where('status', MatchStatus::Completed)
            ->select($fields)
            ->withCount('shooters')
            ->with('organization:id,name')
            ->latest('date')
            ->take(10)
            ->get();

        $browse = ShootingMatch::whereNotIn('id', $myMatchIds)
            ->whereIn('status', [
                MatchStatus::PreRegistration,
                MatchStatus::RegistrationOpen,
            ])
            ->select($fields)
            ->withCount('shooters')
            ->with('organization:id,name')
            ->orderBy('date')
            ->get();

        $transform = fn ($match) => [
            'id' => $match->id,
            'name' => $match->name,
            'date' => $match->date?->toDateString(),
            'location' => $match->location,
            'status' => $match->status->value,
            'status_label' => $match->status->label(),
            'scoring_type' => $match->scoring_type ?? 'standard',
            'organization_name' => $match->organization?->name,
            'shooters_count' => $match->shooters_count,
        ];

        return response()->json([
            'live' => $live->map($transform)->values(),
            'upcoming' => $upcoming->map($transform)->values(),
            'recent' => $recent->map($transform)->values(),
            'browse' => $browse->map($transform)->values(),
        ]);
    }
}
