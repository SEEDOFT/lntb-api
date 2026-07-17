<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_user_access', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('granted_by_user_id')->index();
            $table->unsignedBigInteger('device_access_status_id')->index();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['device_id', 'user_id'],
                'device_user_access_device_user_unique'
            );

            $table->index(
                ['device_id', 'device_access_status_id'],
                'device_user_access_device_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_user_access');
    }
};
