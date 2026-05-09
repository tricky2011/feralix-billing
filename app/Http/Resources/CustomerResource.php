<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_code' => $this->customer_code,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'address' => $this->address,
            'location_id' => $this->location_id,
            'preferred_olt_id' => $this->preferred_olt_id,
            'assigned_technician_id' => $this->assigned_technician_id,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'customer_type' => $this->customer_type?->value,
            'status' => $this->status?->value,
            'services_count' => $this->when(isset($this->services_count), (int) $this->services_count),
            'active_services_count' => $this->when(isset($this->active_services_count), (int) $this->active_services_count),
            'install_date' => $this->install_date?->toDateString(),
            'location' => $this->whenLoaded('location', function (): ?array {
                if ($this->location === null) {
                    return null;
                }

                return [
                    'id' => $this->location->id,
                    'location_code' => $this->location->location_code,
                    'location_name' => $this->location->location_name,
                ];
            }),
            'preferred_olt' => $this->whenLoaded('preferredOlt', function (): ?array {
                if ($this->preferredOlt === null) {
                    return null;
                }

                return [
                    'id' => $this->preferredOlt->id,
                    'olt_code' => $this->preferredOlt->olt_code,
                    'olt_name' => $this->preferredOlt->olt_name,
                    'location_id' => $this->preferredOlt->location_id,
                    'location_name' => $this->preferredOlt->location_name,
                ];
            }),
            'assigned_technician' => $this->whenLoaded('assignedTechnician', function (): ?array {
                if ($this->assignedTechnician === null) {
                    return null;
                }

                return [
                    'id' => $this->assignedTechnician->id,
                    'technician_code' => $this->assignedTechnician->technician_code,
                    'full_name' => $this->assignedTechnician->full_name,
                    'is_active' => (bool) $this->assignedTechnician->is_active,
                ];
            }),
            'latest_active_service' => $this->whenLoaded('latestActiveService', function (): ?array {
                if ($this->latestActiveService === null) {
                    return null;
                }

                return [
                    'id' => $this->latestActiveService->id,
                    'service_code' => $this->latestActiveService->service_code,
                    'router_id' => $this->latestActiveService->router_id,
                    'olt_id' => $this->latestActiveService->olt_id,
                    'vid_id' => $this->latestActiveService->vid_id,
                    'internet_vid' => $this->latestActiveService->internet_vid,
                    'pppoe_username' => $this->latestActiveService->pppoe_username,
                    'pppoe_server' => $this->latestActiveService->pppoe_isolation_profile,
                    'subnet_cidr' => $this->latestActiveService->subnet_cidr,
                    'gateway_ip' => $this->latestActiveService->gateway_ip,
                    'dhcp_pool_start' => $this->latestActiveService->dhcp_pool_start,
                    'dhcp_pool_end' => $this->latestActiveService->dhcp_pool_end,
                    'billing_status' => $this->latestActiveService->billing_status?->value,
                    'network_status' => $this->latestActiveService->network_status?->value,
                    'overall_status' => $this->latestActiveService->overall_status?->value,
                    'activation_date' => $this->latestActiveService->activation_date?->toDateString(),
                    'router' => $this->latestActiveService->relationLoaded('router') && $this->latestActiveService->router !== null ? [
                        'id' => $this->latestActiveService->router->id,
                        'router_code' => $this->latestActiveService->router->router_code,
                        'router_name' => $this->latestActiveService->router->router_name,
                    ] : null,
                    'olt' => $this->latestActiveService->relationLoaded('olt') && $this->latestActiveService->olt !== null ? [
                        'id' => $this->latestActiveService->olt->id,
                        'olt_code' => $this->latestActiveService->olt->olt_code,
                        'olt_name' => $this->latestActiveService->olt->olt_name,
                    ] : null,
                    'package' => $this->latestActiveService->relationLoaded('package') && $this->latestActiveService->package !== null ? [
                        'id' => $this->latestActiveService->package->id,
                        'package_name' => $this->latestActiveService->package->package_name,
                        'monthly_price' => $this->latestActiveService->package->monthly_price,
                    ] : null,
                    'vid' => $this->latestActiveService->relationLoaded('vid') && $this->latestActiveService->vid !== null ? [
                        'id' => $this->latestActiveService->vid->id,
                        'vid' => $this->latestActiveService->vid->vid,
                        'status' => $this->latestActiveService->vid->status?->value,
                    ] : null,
                ];
            }),
            'services' => $this->whenLoaded('services', function (): array {
                return $this->services
                    ->map(function ($service): array {
                        return [
                            'id' => $service->id,
                            'service_code' => $service->service_code,
                            'router_id' => $service->router_id,
                            'olt_id' => $service->olt_id,
                            'ont_id' => $service->ont_id,
                            'vid_id' => $service->vid_id,
                            'internet_vid' => $service->internet_vid,
                            'subnet_cidr' => $service->subnet_cidr,
                            'gateway_ip' => $service->gateway_ip,
                            'dhcp_pool_start' => $service->dhcp_pool_start,
                            'dhcp_pool_end' => $service->dhcp_pool_end,
                            'billing_status' => $service->billing_status?->value,
                            'network_status' => $service->network_status?->value,
                            'overall_status' => $service->overall_status?->value,
                            'activation_date' => $service->activation_date?->toDateString(),
                            'router' => $service->relationLoaded('router') && $service->router !== null ? [
                                'id' => $service->router->id,
                                'router_code' => $service->router->router_code,
                                'router_name' => $service->router->router_name,
                            ] : null,
                            'olt' => $service->relationLoaded('olt') && $service->olt !== null ? [
                                'id' => $service->olt->id,
                                'olt_code' => $service->olt->olt_code,
                                'olt_name' => $service->olt->olt_name,
                            ] : null,
                            'ont' => $service->relationLoaded('ont') && $service->ont !== null ? [
                                'id' => $service->ont->id,
                                'ont_sn' => $service->ont->ont_sn,
                                'ont_name' => $service->ont->ont_name,
                                'status' => $service->ont->status,
                            ] : null,
                            'package' => $service->relationLoaded('package') && $service->package !== null ? [
                                'id' => $service->package->id,
                                'package_name' => $service->package->package_name,
                                'monthly_price' => $service->package->monthly_price,
                            ] : null,
                            'vid' => $service->relationLoaded('vid') && $service->vid !== null ? [
                                'id' => $service->vid->id,
                                'vid' => $service->vid->vid,
                                'status' => $service->vid->status?->value,
                            ] : null,
                            'created_at' => $service->created_at?->toISOString(),
                            'updated_at' => $service->updated_at?->toISOString(),
                            'deleted_at' => $service->deleted_at?->toISOString(),
                        ];
                    })
                    ->all();
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
