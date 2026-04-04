<?php

namespace App\Jobs;

use App\Mail\ShooterMatchReport;
use App\Models\ShootingMatch;
use App\Notifications\ScoresPublishedNotification;
use App\Services\MatchReportService;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPostMatchNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public ShootingMatch $match,
    ) {}

    public function handle(MatchReportService $reportService): void
    {
        $match = $this->match->fresh();

        if (!$match || $match->status->value !== 'completed') {
            Log::info('SendPostMatchNotifications: match no longer completed, skipping', ['match_id' => $this->match->id]);
            return;
        }

        $shooters = $match->shooters()
            ->where('shooters.status', 'active')
            ->with('user')
            ->get();

        $users = $shooters->pluck('user')->filter()->unique('id');

        foreach ($users as $user) {
            if ($user->wantsNotification('scores_published')) {
                $user->notify(new ScoresPublishedNotification($match));

                try {
                    $data = (new ScoresPublishedNotification($match))->toArray($user);
                    PushNotificationService::send($user, $data['title'], $data['body'], $data['url']);
                } catch (\Throwable $e) {
                    Log::warning('Push notification failed', ['user' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $emailableShooters = $reportService->getEmailableShooters($match);

        foreach ($emailableShooters as $shooter) {
            if ($shooter->user && $shooter->user->wantsEmailNotification('scores_published')) {
                try {
                    $report = $reportService->generateReport($match, $shooter);
                    Mail::to($shooter->user->email)->queue(new ShooterMatchReport($report));
                } catch (\Throwable $e) {
                    Log::warning('Match report email failed', ['shooter' => $shooter->id, 'error' => $e->getMessage()]);
                }
            }
        }

        Log::info('SendPostMatchNotifications: completed', [
            'match_id' => $match->id,
            'notifications' => $users->count(),
            'reports' => $emailableShooters->count(),
        ]);
    }
}
