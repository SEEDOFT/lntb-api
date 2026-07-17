<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_type_id')->index();
            $table->unsignedBigInteger('device_status_id')->index();
            $table->unsignedBigInteger('owner_user_id')->nullable()->index();
            $table->string('name', 120)->nullable();
            $table->string('serial_number', 100)->unique();
            $table->string('mac_address', 17)->unique();
            $table->string('claim_code_hash');
            $table->string('firmware_version', 50)->nullable();
            $table->timestamp('claim_code_used_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(
                ['owner_user_id', 'device_status_id'],
                'devices_owner_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
