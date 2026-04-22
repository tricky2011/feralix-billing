<?php

namespace App\Http\Requests\Reseller;

use App\Models\Reseller;

class UpdateResellerRequest extends ResellerPayloadRequest
{
    protected function currentResellerId(): ?int
    {
        /** @var Reseller $reseller */
        $reseller = $this->route('reseller');

        return $reseller->id;
    }
}
