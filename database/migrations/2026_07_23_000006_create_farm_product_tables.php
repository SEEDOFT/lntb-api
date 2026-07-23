<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->lookup('farm_statuses', ['active', 'inactive', 'archived']);
        $this->lookup('crop_cycle_statuses', ['planned', 'active', 'completed', 'cancelled']);
        $this->lookup('task_sources', ['manual', 'sensor', 'ripeness', 'system']);
        $this->lookup('task_statuses', ['open', 'completed', 'dismissed']);
        $this->lookup('sensor_types', ['soil_moisture', 'temperature', 'humidity', 'light']);
        $this->lookup('ripeness_stages', ['unripe', 'turning', 'ripe', 'overripe', 'unknown']);
        $this->lookup('farm_log_types', ['note', 'planting', 'irrigation', 'fertilizer', 'harvest']);

        Schema::create('farms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('owner_user_id')->index();
            $table->unsignedBigInteger('farm_status_id')->index();
            $table->string('name', 120);
            $table->string('location', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('farm_devices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farm_id')->index();
            $table->unsignedBigInteger('device_id')->unique();
            $table->timestamps();
        });
        Schema::create('crop_cycles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farm_id')->index();
            $table->unsignedBigInteger('crop_cycle_status_id')->index();
            $table->string('crop_name', 120);
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->timestamps();
        });
        Schema::create('sensor_readings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farm_id')->index();
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('sensor_type_id')->index();
            $table->decimal('value', 14, 4);
            $table->string('unit', 20);
            $table->string('status_code', 30)->default('normal')->index();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
        Schema::create('farm_tasks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farm_id')->index();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('task_source_id')->index();
            $table->unsignedBigInteger('task_status_id')->index();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('irrigation_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farm_id')->unique();
            $table->decimal('moisture_threshold', 8, 2)->nullable();
            $table->string('mode_code', 30)->default('manual');
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
        Schema::create('usage_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farm_id')->index();
            $table->date('recorded_on')->index();
            $table->decimal('water_cubic_meters', 14, 4)->default(0);
            $table->decimal('electricity_kwh', 14, 4)->default(0);
            $table->decimal('water_rate_usd', 12, 4)->default(0);
            $table->decimal('electricity_rate_usd', 12, 4)->default(0);
            $table->decimal('total_cost_usd', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['farm_id', 'recorded_on']);
        });
        Schema::create('ripeness_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farm_id')->index();
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('ripeness_stage_id')->index();
            $table->string('image_path', 500)->nullable();
            $table->decimal('confidence', 7, 6)->nullable();
            $table->string('model_version', 100)->nullable();
            $table->string('recommendation', 500)->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('farm_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farm_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('farm_log_type_id')->index();
            $table->string('title', 180);
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
        Schema::create('harvest_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farm_id')->index();
            $table->unsignedBigInteger('crop_cycle_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('quantity', 14, 3);
            $table->string('unit', 20)->default('kg');
            $table->string('grade', 50)->nullable();
            $table->decimal('damaged_quantity', 14, 3)->nullable();
            $table->timestamp('harvested_at')->index();
            $table->timestamps();
        });
        Schema::create('assistant_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farm_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['assistant_messages', 'harvest_records', 'farm_logs', 'ripeness_results', 'usage_records', 'irrigation_settings', 'farm_tasks', 'sensor_readings', 'crop_cycles', 'farm_devices', 'farms', 'farm_log_types', 'ripeness_stages', 'sensor_types', 'task_statuses', 'task_sources', 'crop_cycle_statuses', 'farm_statuses'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function lookup(string $tableName, array $codes): void
    {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->timestamps();
        });
        foreach ($codes as $code) {
            DB::table($tableName)->insert([
                'code' => $code,
                'name' => ucwords(str_replace('_', ' ', $code)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
