<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_fcm_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('device_key', 128)->unique();
            $table->string('fcm_token', 255)->nullable()->unique();
            $table->string('platform', 50)->nullable();
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        DB::table('users')
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('user_fcm_tokens')->insertOrIgnore([
                        'user_id' => $user->id,
                        'device_key' => "legacy:user:{$user->id}",
                        'fcm_token' => $user->fcm_token,
                        'platform' => null,
                        'device_name' => 'Legacy device',
                        'app_version' => null,
                        'last_used_at' => now(),
                        'revoked_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_fcm_tokens');
    }
};
