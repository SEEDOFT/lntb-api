<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

final class NotificationService
{
    /**
     * Send a push notification to a specific user.
     *
     * @param User $user
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool True if successful, false otherwise.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [], string $typeCode = \App\Models\NotificationType::SYSTEM): bool
    {
        $typeMap = [
            \App\Models\NotificationType::WELCOME => \App\Models\NotificationType::ID_WELCOME,
            \App\Models\NotificationType::SYSTEM => \App\Models\NotificationType::ID_SYSTEM,
            \App\Models\NotificationType::ALERT => \App\Models\NotificationType::ID_ALERT,
        ];

        $notification = \App\Models\Notification::create([
            'user_id' => $user->id,
            'notification_type_id' => $typeMap[$typeCode] ?? \App\Models\NotificationType::ID_SYSTEM,
            'notification_status_id' => \App\Models\NotificationStatus::ID_UNREAD,
            'title' => $title,
            'body' => $body,
            'data' => empty($data) ? null : $data,
        ]);

        if (empty($user->fcm_token)) {
            return true; // Saved to DB, but no FCM token to push to.
        }

        return $this->sendToToken($user->fcm_token, $title, $body, $data);
    }

    /**
     * Send a push notification to a specific FCM token.
     *
     * @param string $token
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $messaging = Firebase::messaging();

            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification);

            if (!empty($data)) {
                $message = $message->withData($data);
            }

            $messaging->send($message);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to send push notification: ' . $e->getMessage(), [
                'token' => $token,
                'title' => $title,
                'exception' => $e
            ]);
            return false;
        }
    }
}
