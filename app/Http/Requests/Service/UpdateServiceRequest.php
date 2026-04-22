<?php

namespace App\Http\Requests\Service;

use App\Models\Service;

class UpdateServiceRequest extends ServicePayloadRequest
{
    protected function currentServiceId(): ?int
    {
        /** @var Service $service */
        $service = $this->route('service');

        return $service->id;
    }
}
