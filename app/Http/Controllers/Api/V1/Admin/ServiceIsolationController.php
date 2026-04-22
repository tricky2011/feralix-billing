<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceIsolation\MarkAppliedServiceIsolationRequest;
use App\Http\Requests\ServiceIsolation\ReleaseServiceIsolationRequest;
use App\Http\Requests\ServiceIsolation\StoreServiceIsolationRequest;
use App\Http\Resources\ServiceIsolationResource;
use App\Http\Resources\ServiceIsolationSuggestionResource;
use App\Models\ServiceIsolation;
use App\Services\Provisioning\ServiceIsolationService;
use App\Services\Provisioning\ServiceIsolationSuggestionService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\ServiceIsolation\SuggestServiceIsolationRequest;

class ServiceIsolationController extends Controller
{
    public function __construct(
        private readonly ServiceIsolationService $serviceIsolationService,
        private readonly ServiceIsolationSuggestionService $serviceIsolationSuggestionService,
    ) {}

    public function suggestions(SuggestServiceIsolationRequest $request): JsonResponse
    {
        $suggestions = $this->serviceIsolationSuggestionService->suggest($request->validated());

        return response()->json([
            'data' => ServiceIsolationSuggestionResource::collection($suggestions),
        ]);
    }

    public function store(StoreServiceIsolationRequest $request): JsonResponse
    {
        $serviceIsolation = $this->serviceIsolationService->createIsolationRecord($request->validated());

        return response()->json([
            'message' => 'Service isolation record created successfully.',
            'data' => new ServiceIsolationResource($serviceIsolation),
        ], 201);
    }

    public function markApplied(
        MarkAppliedServiceIsolationRequest $request,
        ServiceIsolation $serviceIsolation
    ): JsonResponse {
        $serviceIsolation = $this->serviceIsolationService->markApplied($serviceIsolation, $request->validated());

        return response()->json([
            'message' => 'Service isolation marked as applied successfully.',
            'data' => new ServiceIsolationResource($serviceIsolation),
        ]);
    }

    public function release(
        ReleaseServiceIsolationRequest $request,
        ServiceIsolation $serviceIsolation
    ): JsonResponse {
        $serviceIsolation = $this->serviceIsolationService->releaseIsolation($serviceIsolation, $request->validated());

        return response()->json([
            'message' => 'Service isolation released successfully.',
            'data' => new ServiceIsolationResource($serviceIsolation),
        ]);
    }
}
