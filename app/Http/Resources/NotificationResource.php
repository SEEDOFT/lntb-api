<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
final class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'type' => $this->whenLoaded('type', fn () => [
                'code' => $this->type->code,
                'name' => $this->type->name,
            ]),
            'status' => $this->whenLoaded('status', fn () => [
                'code' => $this->status->code,
                'name' => $this->status->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
