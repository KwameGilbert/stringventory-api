<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Exception;

class WebPushService
{
    private WebPush $webPush;

    public function __construct()
    {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject'    => $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@example.com',
                'publicKey'  => $_ENV['VAPID_PUBLIC_KEY'] ?? '',
                'privateKey' => $_ENV['VAPID_PRIVATE_KEY'] ?? '',
            ],
        ]);
    }

    /**
     * Send a push notification to all subscriptions for a user.
     * Automatically removes expired subscriptions (HTTP 410/404).
     */
    public function sendToUser(int $userId, array $payload): void
    {
        $subscriptions = PushSubscription::where('userId', $userId)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payloadJson = json_encode($payload);

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys'     => [
                        'p256dh' => $sub->p256dhKey,
                        'auth'   => $sub->authKey,
                    ],
                ]);

                $report = $this->webPush->sendOneNotification($subscription, $payloadJson);

                if ($report->isSubscriptionExpired()) {
                    $sub->delete();
                }
            } catch (Exception $e) {
                error_log('WebPush send error for userId ' . $userId . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Send a push notification to multiple users.
     */
    public function sendToUsers(array $userIds, array $payload): void
    {
        foreach ($userIds as $userId) {
            $this->sendToUser((int) $userId, $payload);
        }
    }
}
