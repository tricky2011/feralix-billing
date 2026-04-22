<?php

namespace App\Services\Hotspot\Radius\Providers;

use App\Contracts\Hotspot\HotspotRadiusProvider;
use App\Data\Hotspot\HotspotRadiusAccountingRequest;
use App\Data\Hotspot\HotspotRadiusAccountingResult;
use App\Data\Hotspot\HotspotRadiusAuthorizeRequest;
use App\Data\Hotspot\HotspotRadiusAuthorizeResult;
use App\Services\Hotspot\Radius\HotspotRadiusService;

class StubHotspotRadiusProvider implements HotspotRadiusProvider
{
    public function __construct(private readonly HotspotRadiusService $hotspotRadiusService) {}

    public function name(): string
    {
        return 'stub';
    }

    public function authorize(HotspotRadiusAuthorizeRequest $request): HotspotRadiusAuthorizeResult
    {
        return $this->hotspotRadiusService->authorize($request);
    }

    public function account(HotspotRadiusAccountingRequest $request): HotspotRadiusAccountingResult
    {
        return $this->hotspotRadiusService->account($request);
    }
}
