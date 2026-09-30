<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Retailer;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send in-app and FCM push notification.
     */
    public function sendPushNotification(User $user, string $title, string $body, string $type, ?array $payload = null): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'title'   => $title,
            'body'    => $body,
            'type'    => $type,
            'payload' => $payload,
            'is_read' => false,
        ]);

        // Attempt FCM dispatch if token available
        $fcmToken = $user->devices()->whereNotNull('fcm_token')->value('fcm_token');
        if ($fcmToken) {
            // Mock / Real FCM dispatch log
            Log::info("FCM dispatched to user {$user->id} [{$type}]: {$title}");
        }

        return $notification;
    }

    /**
     * Daily 09:00 AM Outstanding Reminder with Strict 24-Hour Anti-Spam Lock.
     */
    public function sendDailyOutstandingReminders(): int
    {
        $eligibleRetailers = Retailer::where('outstanding_paise', '>', 0)
            ->where(function ($q) {
                $q->whereNull('last_outstanding_alert_at')
                  ->orWhere('last_outstanding_alert_at', '<=', now()->subHours(24));
            })
            ->with('user')
            ->get();

        $sentCount = 0;

        foreach ($eligibleRetailers as $retailer) {
            $amountRupees = number_format($retailer->outstanding_paise / 100, 2);

            $this->sendPushNotification(
                $retailer->user,
                'Daily Outstanding Notice',
                "Your pending market float is ₹{$amountRupees}. Please clear your balance or raise a cash pickup request.",
                'OUTSTANDING_ALERT',
                [
                    'outstanding_paise' => $retailer->outstanding_paise,
                    'outstanding_rupees' => $amountRupees,
                    'action_url' => '/retailer/wallet',
                ]
            );

            $retailer->update(['last_outstanding_alert_at' => now()]);
            $sentCount++;
        }

        return $sentCount;
    }
}
