<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceIsolation\IndexServiceIsolationRequest;
use App\Http\Requests\ServiceIsolation\MarkAppliedServiceIsolationRequest;
use App\Http\Requests\ServiceIsolation\ReleaseServiceIsolationRequest;
use App\Http\Requests\ServiceIsolation\StoreServiceIsolationRequest;
use App\Http\Resources\ServiceIsolationResource;
use App\Http\Resources\ServiceIsolationSuggestionResource;
use App\Models\ServiceIsolation;
use App\Services\Provisioning\ServiceIsolationHistoryService;
use App\Services\Provisioning\ServiceIsolationService;
use App\Services\Provisioning\ServiceIsolationSuggestionService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\ServiceIsolation\SuggestServiceIsolationRequest;

class ServiceIsolationController extends Controller
{
    public function __construct(
        private readonly ServiceIsolationService $serviceIsolationService,
        private readonly ServiceIsolationSuggestionService $serviceIsolationSuggestionService,
        private readonly ServiceIsolationHistoryService $serviceIsolationHistoryService,
    ) {}

    public function index(IndexServiceIsolationRequest $request)
    {
        $serviceIsolations = $this->serviceIsolationHistoryService->paginate($request->validated());

        return $this->paginatedResponse(
            $serviceIsolations,
            ServiceIsolationResource::class,
            'Service isolations retrieved successfully.',
            ['filters' => $request->validated()],
        );
    }

    public function suggestions(SuggestServiceIsolationRequest $request): JsonResponse
    {
        $suggestions = $this->serviceIsolationSuggestionService->suggest($request->validated());

        return $this->collectionResponse(
            $suggestions,
            ServiceIsolationSuggestionResource::class,
            'Service isolation suggestions generated successfully.',
        );
    }

    public function store(StoreServiceIsolationRequest $request): JsonResponse
    {
        $serviceIsolation = $this->serviceIsolationService->createIsolationRecord($request->validated());

        return $this->createdResponse(
            'Service isolation record created successfully.',
            new ServiceIsolationResource($serviceIsolation),
        );
    }

    public function markApplied(
        MarkAppliedServiceIsolationRequest $request,
        ServiceIsolation $serviceIsolation
    ): JsonResponse {
        $serviceIsolation = $this->serviceIsolationService->markApplied($serviceIsolation, $request->validated());

        return $this->successResponse(
            'Service isolation marked as applied successfully.',
            new ServiceIsolationResource($serviceIsolation),
        );
    }

    public function release(
        ReleaseServiceIsolationRequest $request,
        ServiceIsolation $serviceIsolation
    ): JsonResponse {
        $serviceIsolation = $this->serviceIsolationService->releaseIsolation($serviceIsolation, $request->validated());

        return $this->successResponse(
            'Service isolation released successfully.',
            new ServiceIsolationResource($serviceIsolation),
        );
    }
}
