<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\NotificationStatus;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $deletedStatusId = NotificationStatus::query()
            ->where('code', NotificationStatus::DELETED)
            ->valueOrFail('id');

        $page = Notification::query()
            ->with(['type:id,code,name', 'status:id,code,name'])
            ->where('user_id', $request->user()->id)
            ->where('notification_status_id', '!=', $deletedStatusId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::success('Notifications retrieved successfully.', NotificationResource::collection($page->getCollection())->resolve($request), meta: [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'unread_count' => $this->notifications->unreadCount((int) $request->user()->id),
        ]);
    }

    public function update(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([NotificationStatus::READ, NotificationStatus::UNREAD, NotificationStatus::DELETED])],
        ]);

        $statusId = NotificationStatus::query()
            ->where('code', $validated['status'])
            ->valueOrFail('id');

        $notification->forceFill([
            'notification_status_id' => $statusId,
        ])->save();

        return ApiResponse::success(
            'Notification updated successfully.',
            (new NotificationResource($notification->load(['type:id,code,name', 'status:id,code,name'])))->resolve($request),
            meta: [
                'unread_count' => $this->notifications->unreadCount((int) $request->user()->id),
            ],
        );
    }
}
