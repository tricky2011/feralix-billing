<?php

namespace App\Contracts\Hotspot;

use App\Data\Hotspot\HotspotRadiusAccountingRequest;
use App\Data\Hotspot\HotspotRadiusAccountingResult;
use App\Data\Hotspot\HotspotRadiusAuthorizeRequest;
use App\Data\Hotspot\HotspotRadiusAuthorizeResult;

interface HotspotRadiusProvider
{
    public function name(): string;

    public function authorize(HotspotRadiusAuthorizeRequest $request): HotspotRadiusAuthorizeResult;

    public function account(HotspotRadiusAccountingRequest $request): HotspotRadiusAccountingResult;
}
