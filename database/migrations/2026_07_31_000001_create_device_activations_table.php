<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_activations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('intended_user_id')->index();
            $table->uuid('public_reference')->unique();
            $table->string('token_hash', 64)->unique();
            $table->string('prepared_by_identifier', 120);
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['device_id', 'revoked_at', 'consumed_at'],
                'device_activations_current_idx',
            );
        });

        Schema::create('device_activation_audits', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_activation_id')->nullable()->index();
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event_code', 50)->index();
            $table->string('actor_identifier', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_activation_audits');
        Schema::dropIfExists('device_activations');
    }
};
