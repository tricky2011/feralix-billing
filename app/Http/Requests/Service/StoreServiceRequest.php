<?php

namespace App\Http\Requests\Service;

class StoreServiceRequest extends ServicePayloadRequest
{
    protected function currentServiceId(): ?int
    {
        return null;
    }
}
