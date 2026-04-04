<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public static function send(User $user, string $title, string $body, string $url = '/', array $extra = []): int
    {
        $subscriptions = $user->pushSubscriptions;
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        if (!class_exists(\Minishlink\WebPush\WebPush::class)) {
            Log::debug('PushNotificationService: web-push library not installed, skipping push');
            return 0;
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject' => config('app.url'),
                    'publicKey' => config('services.webpush.public_key', ''),
                    'privateKey' => config('services.webpush.private_key', ''),
                ],
            ];

            $webPush = new \Minishlink\WebPush\WebPush($auth);

            $payload = json_encode(array_merge([
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'icon' => '/icons/icon-192.png',
            ], $extra));

            $sent = 0;
            foreach ($subscriptions as $sub) {
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?? 'aesgcm',
                ]);

                $webPush->queueNotification($subscription, $payload);
                $sent++;
            }

            $results = $webPush->flush();
            $expired = [];

            if ($results) {
                foreach ($results as $report) {
                    if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                        $expired[] = $report->getEndpoint();
                    }
                }
            }

            if (!empty($expired)) {
                $user->pushSubscriptions()->whereIn('endpoint', $expired)->delete();
            }

            return $sent;
        } catch (\Throwable $e) {
            Log::warning('PushNotificationService failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    public static function sendToUsers($users, string $title, string $body, string $url = '/', array $extra = []): int
    {
        $total = 0;
        foreach ($users as $user) {
            $total += static::send($user, $title, $body, $url, $extra);
        }
        return $total;
    }
}
