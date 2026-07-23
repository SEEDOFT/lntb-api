<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Farm;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final class FarmController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $farms = Farm::query()
            ->where('owner_user_id', $request->user()->id)
            ->with('status')
            ->orderBy('name')
            ->get()
            ->map(fn (Farm $farm): array => $this->farm($farm));

        return ApiResponse::success('Farms retrieved successfully.', $farms);
    }

    public function show(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        return ApiResponse::success('Farm retrieved successfully.', $this->farm($farm->load('status')));
    }

    public function dashboard(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $metrics = DB::table('sensor_readings as readings')
            ->join('sensor_types as types', 'types.id', '=', 'readings.sensor_type_id')
            ->where('readings.farm_id', $farm->id)
            ->whereIn('readings.id', DB::table('sensor_readings')->selectRaw('MAX(id)')->where('farm_id', $farm->id)->groupBy('sensor_type_id'))
            ->get()
            ->map(fn ($row): array => [
                'code' => $row->code,
                'value' => (float) $row->value,
                'unit' => $row->unit,
                'status' => $row->status_code,
                'recorded_at' => $row->recorded_at,
            ]);

        return ApiResponse::success('Farm dashboard retrieved successfully.', [
            'farm' => $this->farm($farm->load('status')),
            'metrics' => $metrics,
            'open_task_count' => DB::table('farm_tasks')->where('farm_id', $farm->id)->where('task_status_id', $this->lookupId('task_statuses', 'open'))->count(),
            'online_device_count' => DB::table('farm_devices')->where('farm_id', $farm->id)->count(),
            'latest_alert' => null,
        ]);
    }

    public function tasks(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $rows = DB::table('farm_tasks as tasks')
            ->join('task_statuses as statuses', 'statuses.id', '=', 'tasks.task_status_id')
            ->join('task_sources as sources', 'sources.id', '=', 'tasks.task_source_id')
            ->where('tasks.farm_id', $farm->id)
            ->orderByRaw('tasks.due_at IS NULL, tasks.due_at')
            ->select('tasks.*', 'statuses.code as status_code', 'sources.code as source_code')
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id, 'title' => $row->title, 'description' => $row->description,
                'status' => ['code' => $row->status_code], 'source' => ['code' => $row->source_code],
                'due_at' => $row->due_at,
            ]);

        return ApiResponse::success('Farm tasks retrieved successfully.', $rows);
    }

    public function storeTask(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:2000'], 'due_at' => ['nullable', 'date']]);
        $id = DB::table('farm_tasks')->insertGetId([
            'farm_id' => $farm->id, 'created_by_user_id' => $request->user()->id,
            'task_source_id' => $this->lookupId('task_sources', 'manual'),
            'task_status_id' => $this->lookupId('task_statuses', 'open'),
            'title' => $data['title'], 'description' => $data['description'] ?? null,
            'due_at' => $data['due_at'] ?? null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::success('Task created successfully.', ['id' => $id], 201);
    }

    public function updateTask(Request $request, Farm $farm, int $task): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $data = $request->validate(['action' => ['required', 'in:complete,dismiss']]);
        $code = $data['action'] === 'complete' ? 'completed' : 'dismissed';
        DB::table('farm_tasks')->where('id', $task)->where('farm_id', $farm->id)->update([
            'task_status_id' => $this->lookupId('task_statuses', $code),
            $code === 'completed' ? 'completed_at' : 'dismissed_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Task updated successfully.');
    }

    public function telemetry(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $rows = DB::table('sensor_readings as readings')
            ->join('sensor_types as types', 'types.id', '=', 'readings.sensor_type_id')
            ->where('readings.farm_id', $farm->id)
            ->latest('readings.recorded_at')->limit(100)
            ->get()
            ->map(fn ($row): array => ['code' => $row->code, 'value' => (float) $row->value, 'unit' => $row->unit, 'status' => $row->status_code, 'recorded_at' => $row->recorded_at]);

        return ApiResponse::success('Telemetry retrieved successfully.', $rows);
    }

    public function usage(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        return ApiResponse::success('Usage retrieved successfully.', DB::table('usage_records')->where('farm_id', $farm->id)->latest('recorded_on')->paginate(31)->items());
    }

    public function irrigation(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $settings = DB::table('irrigation_settings')
            ->where('farm_id', $farm->id)
            ->first();

        return ApiResponse::success('Irrigation status retrieved successfully.', [
            'mode' => $settings?->mode_code ?? 'manual',
            'moisture_threshold' => $settings?->moisture_threshold !== null
                ? (float) $settings->moisture_threshold
                : null,
            'last_triggered_at' => $settings?->last_triggered_at,
        ]);
    }

    public function ripeness(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $rows = DB::table('ripeness_results as results')->join('ripeness_stages as stages', 'stages.id', '=', 'results.ripeness_stage_id')->where('results.farm_id', $farm->id)->latest('captured_at')->get()->map(fn ($row): array => [
            'id' => $row->id, 'stage' => ['code' => $row->code], 'confidence' => (float) ($row->confidence ?? 0),
            'image_url' => $row->image_path ? Storage::url($row->image_path) : null, 'model_version' => $row->model_version,
            'recommendation' => $row->recommendation, 'captured_at' => $row->captured_at,
        ]);
        return ApiResponse::success('Ripeness results retrieved successfully.', $rows);
    }

    public function logs(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $rows = DB::table('farm_logs as logs')->join('farm_log_types as types', 'types.id', '=', 'logs.farm_log_type_id')->where('logs.farm_id', $farm->id)->latest('recorded_at')->select('logs.*', 'types.code as type_code')->get()->map(fn ($row): array => [
            'id' => $row->id, 'type' => ['code' => $row->type_code], 'title' => $row->title, 'notes' => $row->notes, 'recorded_at' => $row->recorded_at,
        ]);
        return ApiResponse::success('Farm logs retrieved successfully.', $rows);
    }

    public function storeLog(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $data = $request->validate(['type' => ['required', 'string'], 'title' => ['required', 'string', 'max:180'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $id = DB::table('farm_logs')->insertGetId(['farm_id' => $farm->id, 'user_id' => $request->user()->id, 'farm_log_type_id' => $this->lookupId('farm_log_types', $data['type']), 'title' => $data['title'], 'notes' => $data['notes'] ?? null, 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return ApiResponse::success('Farm log created successfully.', ['id' => $id], 201);
    }

    public function harvests(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        return ApiResponse::success('Harvest records retrieved successfully.', DB::table('harvest_records')->where('farm_id', $farm->id)->latest('harvested_at')->get());
    }

    public function storeHarvest(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0.001'], 'unit' => ['required', 'in:kg,basket'], 'grade' => ['nullable', 'string', 'max:50'], 'damaged_quantity' => ['nullable', 'numeric', 'min:0']]);
        $id = DB::table('harvest_records')->insertGetId(['farm_id' => $farm->id, 'user_id' => $request->user()->id, ...$data, 'harvested_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return ApiResponse::success('Harvest recorded successfully.', ['id' => $id], 201);
    }

    public function assistant(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $question = $request->validate(['question' => ['required', 'string', 'max:1000']])['question'];
        $endpoint = config('services.farm_assistant.endpoint');
        if (! is_string($endpoint) || $endpoint === '') {
            throw new BusinessException('ASSISTANT_UNAVAILABLE', 'The farm assistant is not configured.', 503);
        }
        $response = Http::timeout(15)->post($endpoint, ['farm_id' => $farm->id, 'question' => $question]);
        if ($response->failed()) {
            throw new BusinessException('ASSISTANT_UNAVAILABLE', 'The farm assistant is temporarily unavailable.', 503);
        }
        $answer = (string) $response->json('answer', '');
        DB::table('assistant_messages')->insert(['farm_id' => $farm->id, 'user_id' => $request->user()->id, 'question' => $question, 'answer' => $answer, 'created_at' => now(), 'updated_at' => now()]);
        return ApiResponse::success('Assistant response generated.', ['answer' => $answer]);
    }

    private function authorizeFarm(Request $request, Farm $farm): void
    {
        if ($farm->owner_user_id !== $request->user()->id) {
            throw new BusinessException('FARM_ACCESS_DENIED', 'You are not authorized to access this farm.', 403);
        }
    }

    private function lookupId(string $table, string $code): int
    {
        $id = DB::table($table)->where('code', $code)->value('id');
        if ($id === null) {
            throw new BusinessException('LOOKUP_NOT_FOUND', 'Required lookup configuration is missing.', 500);
        }
        return (int) $id;
    }

    private function farm(Farm $farm): array
    {
        $cycle = DB::table('crop_cycles')->where('farm_id', $farm->id)->where('crop_cycle_status_id', $this->lookupId('crop_cycle_statuses', 'active'))->latest('started_on')->first();
        return ['id' => $farm->id, 'name' => $farm->name, 'location' => $farm->location, 'status' => ['code' => $farm->status?->code ?? 'inactive'], 'current_crop_cycle' => $cycle ? ['id' => $cycle->id, 'crop_name' => $cycle->crop_name] : null];
    }
}
