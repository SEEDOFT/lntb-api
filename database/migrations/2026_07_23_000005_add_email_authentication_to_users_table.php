<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->unique()->after('phone_number');
            $table->timestamp('email_verified_at')->nullable()->after('phone_verified_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('country_code', 5)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'email_verified_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('country_code', 5)->nullable(false)->change();
        });
    }
};
