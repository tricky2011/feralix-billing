<?php

namespace App\Http\Requests\HotspotProfile;

use App\Models\HotspotProfile;

class UpdateHotspotProfileRequest extends HotspotProfilePayloadRequest
{
    protected function currentProfileId(): ?int
    {
        /** @var HotspotProfile $hotspotProfile */
        $hotspotProfile = $this->route('hotspotProfile');

        return $hotspotProfile->id;
    }
}
