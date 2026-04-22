<?php

namespace App\Http\Requests\HotspotProfile;

class StoreHotspotProfileRequest extends HotspotProfilePayloadRequest
{
    protected function currentProfileId(): ?int
    {
        return null;
    }
}
