<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $welcomeTypeId = DB::table('notification_types')
            ->where('code', 'welcome')
            ->value('id');
        $deletedStatusId = DB::table('notification_statuses')
            ->where('code', 'deleted')
            ->value('id');

        if ($welcomeTypeId === null || $deletedStatusId === null) {
            return;
        }

        $userIds = DB::table('notifications')
            ->where('notification_type_id', $welcomeTypeId)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $notifications = DB::table('notifications')
                ->where('notification_type_id', $welcomeTypeId)
                ->where('user_id', $userId)
                ->orderByRaw('CASE WHEN deduplication_key IS NULL THEN 1 ELSE 0 END')
                ->orderBy('id')
                ->get(['id', 'deduplication_key']);

            $canonical = $notifications->first();

            if ($canonical === null) {
                continue;
            }

            DB::table('notifications')
                ->where('id', $canonical->id)
                ->update([
                    'deduplication_key' => "welcome:user:{$userId}",
                    'updated_at' => now(),
                ]);

            $duplicateIds = $notifications
                ->skip(1)
                ->pluck('id');

            if ($duplicateIds->isNotEmpty()) {
                DB::table('notifications')
                    ->whereIn('id', $duplicateIds)
                    ->update([
                        'notification_status_id' => $deletedStatusId,
                        'deduplication_key' => null,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('notifications')
            ->where('deduplication_key', 'like', 'welcome:user:%')
            ->update([
                'deduplication_key' => null,
                'updated_at' => now(),
            ]);
    }
};
