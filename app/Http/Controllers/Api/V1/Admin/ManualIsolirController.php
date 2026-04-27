<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Isolir\ManualIsolirActionRequest;
use App\Http\Resources\ServiceIsolationResource;
use App\Services\Audit\ActivityLogger;
use App\Services\Provisioning\IsolirService;
use Illuminate\Http\JsonResponse;

class ManualIsolirController extends Controller
{
    public function __construct(
        private readonly IsolirService $isolirService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function isolir(ManualIsolirActionRequest $request): JsonResponse
    {
        $serviceIsolation = $this->isolirService->isolir($request->validated());

        $this->activityLogger->record(
            $request->user(),
            'isolir.manual',
            'isolations',
            sprintf(
                'Manual isolir executed for service %s on router #%d.',
                $serviceIsolation->service?->service_code ?? ('#'.$serviceIsolation->service_id),
                (int) $serviceIsolation->router_id,
            ),
            $request,
        );

        return $this->successResponse(
            'Manual isolir request queued successfully.',
            new ServiceIsolationResource($serviceIsolation),
        );
    }

    public function release(ManualIsolirActionRequest $request): JsonResponse
    {
        $serviceIsolation = $this->isolirService->release($request->validated());

        $this->activityLogger->record(
            $request->user(),
            'release.manual',
            'isolations',
            sprintf(
                'Manual release executed for service %s on router #%d.',
                $serviceIsolation->service?->service_code ?? ('#'.$serviceIsolation->service_id),
                (int) $serviceIsolation->router_id,
            ),
            $request,
        );

        return $this->successResponse(
            'Manual release request queued successfully.',
            new ServiceIsolationResource($serviceIsolation),
        );
    }
}
