<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_controls', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('device_control_status_id')->index();
            $table->string('control_type', 100);
            $table->json('control_data')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamps();

            $table->index(
                ['device_id', 'device_control_status_id', 'requested_at'],
                'device_controls_device_status_requested_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_controls');
    }
};
