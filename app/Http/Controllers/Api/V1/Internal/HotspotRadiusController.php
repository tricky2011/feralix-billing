<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Data\Hotspot\HotspotRadiusAccountingRequest;
use App\Data\Hotspot\HotspotRadiusAuthorizeRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\HotspotRadius\AccountHotspotRadiusRequest;
use App\Http\Requests\HotspotRadius\AuthorizeHotspotRadiusRequest;
use App\Services\Hotspot\Radius\HotspotRadiusProviderResolver;
use Illuminate\Http\JsonResponse;

class HotspotRadiusController extends Controller
{
    public function __construct(private readonly HotspotRadiusProviderResolver $providerResolver) {}

    public function authorize(AuthorizeHotspotRadiusRequest $request): JsonResponse
    {
        $provider = $this->providerResolver->resolve($request->validated('provider'));
        $result = $provider->authorize(HotspotRadiusAuthorizeRequest::fromArray($request->validated()));

        return response()->json([
            'message' => 'Hotspot RADIUS authorization simulated successfully.',
            'provider' => $provider->name(),
            'data' => $result->toArray(),
        ]);
    }

    public function account(AccountHotspotRadiusRequest $request): JsonResponse
    {
        $provider = $this->providerResolver->resolve($request->validated('provider'));
        $result = $provider->account(HotspotRadiusAccountingRequest::fromArray($request->validated()));

        return response()->json([
            'message' => 'Hotspot RADIUS accounting simulated successfully.',
            'provider' => $provider->name(),
            'data' => $result->toArray(),
        ]);
    }
}
