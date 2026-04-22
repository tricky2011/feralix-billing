<?php

namespace App\Http\Requests\Reseller;

class StoreResellerRequest extends ResellerPayloadRequest
{
    protected function currentResellerId(): ?int
    {
        return null;
    }
}
