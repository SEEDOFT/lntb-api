<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('deduplication_key')->nullable()->unique()->after('user_id');
            $table->timestamp('push_sent_at')->nullable()->after('data');
            $table->timestamp('push_failed_at')->nullable()->after('push_sent_at');
            $table->string('push_failure_message', 500)->nullable()->after('push_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropUnique(['deduplication_key']);
            $table->dropColumn([
                'deduplication_key',
                'push_sent_at',
                'push_failed_at',
                'push_failure_message',
            ]);
        });
    }
};
