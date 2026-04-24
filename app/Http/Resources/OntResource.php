<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OntResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'olt_id' => $this->olt_id,
            'ont_sn' => $this->ont_sn,
            'ont_name' => $this->ont_name,
            'pon_port' => $this->pon_port,
            'onu_id' => $this->onu_id,
            'ssid_name' => $this->ssid_name,
            'has_wifi_password' => $this->wifi_password !== null,
            'optical_status' => $this->optical_status,
            'optical_info' => $this->optical_info,
            'rx_power' => $this->rx_power !== null ? (float) $this->rx_power : null,
            'tx_power' => $this->tx_power !== null ? (float) $this->tx_power : null,
            'genieacs_device_id' => $this->genieacs_device_id,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'last_inform_at' => $this->last_inform_at?->toISOString(),
            'genieacs_last_synced_at' => $this->genieacs_last_synced_at?->toISOString(),
            'olt' => $this->whenLoaded('olt', function (): array {
                return [
                    'id' => $this->olt->id,
                    'olt_code' => $this->olt->olt_code,
                    'olt_name' => $this->olt->olt_name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
