<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceIsolationSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'service_id' => $this['service_id'],
            'customer_id' => $this['customer_id'],
            'router_id' => $this['router_id'],
            'service_code' => $this['service_code'],
            'customer_name' => $this['customer_name'],
            'router_code' => $this['router_code'],
            'access_mode' => $this['access_mode'],
            'isolation_method' => $this['isolation_method'],
            'target_type' => $this['target_type'],
            'target_identifier' => $this['target_identifier'],
            'target_subnet' => $this['target_subnet'],
            'address_list_name' => $this['address_list_name'],
            'target_payload' => $this['target_payload'],
            'open_isolation' => $this['open_isolation'],
            'router_operation_status' => $this['router_operation_status'],
        ];
    }
}
