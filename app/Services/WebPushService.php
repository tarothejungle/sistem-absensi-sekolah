<?php

namespace App\Services;

use App\Models\PushSubscription as StoredSubscription;
use App\Support\PushEndpointValidator;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    public function __construct(private PushEndpointValidator $endpointValidator) {}

    public function send(StoredSubscription $storedSubscription, array $payload): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $endpoint = $this->endpointValidator->validate($storedSubscription->endpoint);
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('pwa.vapid_subject'),
                    'publicKey' => config('pwa.vapid_public_key'),
                    'privateKey' => config('pwa.vapid_private_key'),
                ],
            ], [
                'TTL' => max(60, (int) config('pwa.push_ttl', 300)),
                'urgency' => 'normal',
            ], 15);

            $subscription = Subscription::create([
                'endpoint' => $endpoint,
                'publicKey' => $storedSubscription->public_key,
                'authToken' => $storedSubscription->auth_token,
                'contentEncoding' => $storedSubscription->content_encoding,
            ]);

            $report = $webPush->sendOneNotification(
                $subscription,
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            if ($report->isSubscriptionExpired()) {
                $storedSubscription->delete();

                return false;
            }

            if (! $report->isSuccess()) {
                Log::warning('Web Push delivery failed.', [
                    'subscription_id' => $storedSubscription->id,
                    'reason' => $report->getReason(),
                ]);

                return false;
            }

            $storedSubscription->forceFill(['last_used_at' => now()])->save();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function isConfigured(): bool
    {
        return (bool) config('pwa.push_enabled')
            && filled(config('pwa.vapid_subject'))
            && filled(config('pwa.vapid_public_key'))
            && filled(config('pwa.vapid_private_key'));
    }
}
