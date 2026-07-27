<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationStatus;
use App\Models\NotificationType;
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
     * @return bool True if successful, false otherwise.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [], string $typeCode = NotificationType::SYSTEM): bool
    {
        $typeMap = [
            NotificationType::WELCOME => NotificationType::ID_WELCOME,
            NotificationType::SYSTEM => NotificationType::ID_SYSTEM,
            NotificationType::ALERT => NotificationType::ID_ALERT,
        ];

        $notification = \App\Models\Notification::create([
            'user_id' => $user->id,
            'notification_type_id' => $typeMap[$typeCode] ?? NotificationType::ID_SYSTEM,
            'notification_status_id' => NotificationStatus::ID_UNREAD,
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
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $messaging = Firebase::messaging();

            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification);

            if (! empty($data)) {
                $message = $message->withData($data);
            }

            $messaging->send($message);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to send push notification: '.$e->getMessage(), [
                'token' => $token,
                'title' => $title,
                'exception' => $e,
            ]);

            return false;
        }
    }
}
