<?php

namespace App\Http\Controllers\Api\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateDatabaseSettingsRequest;
use App\Http\Resources\DatabaseSettingsResource;
use App\Services\Settings\DatabaseSettingsService;
use Illuminate\Http\JsonResponse;
use Throwable;

class DatabaseSettingController extends Controller
{
    public function __construct(private readonly DatabaseSettingsService $databaseSettingsService) {}

    public function show(): JsonResponse
    {
        return $this->successResponse(
            'Database settings retrieved successfully.',
            new DatabaseSettingsResource($this->databaseSettingsService->current()),
        );
    }

    public function update(UpdateDatabaseSettingsRequest $request): JsonResponse
    {
        $settings = $this->databaseSettingsService->update($request->validated());

        return $this->successResponse(
            'Database settings saved securely.',
            new DatabaseSettingsResource($settings),
        );
    }

    public function test(UpdateDatabaseSettingsRequest $request): JsonResponse
    {
        try {
            $result = $this->databaseSettingsService->testConnection($request->validated());
        } catch (Throwable $throwable) {
            return $this->successResponse('Database connection test failed.', [
                'connected' => false,
                'message' => $throwable->getMessage(),
            ]);
        }

        return $this->successResponse('Database connection test successful.', $result);
    }
}
