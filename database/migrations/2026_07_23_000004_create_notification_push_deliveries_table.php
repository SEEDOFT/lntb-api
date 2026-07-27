<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('notification_id')->index();
            $table->unsignedBigInteger('user_fcm_token_id')->index();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['notification_id', 'user_fcm_token_id'],
                'notification_push_delivery_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_push_deliveries');
    }
};
