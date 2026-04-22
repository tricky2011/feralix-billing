<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'package_id' => $this->package_id,
            'router_id' => $this->router_id,
            'olt_id' => $this->olt_id,
            'ont_id' => $this->ont_id,
            'vid_id' => $this->vid_id,
            'service_code' => $this->service_code,
            'monitor_vid' => $this->monitor_vid,
            'monitor_pppoe_username' => $this->monitor_pppoe_username,
            'monitor_pppoe_password' => $this->monitor_pppoe_password,
            'internet_vid' => $this->internet_vid,
            'access_mode' => $this->access_mode?->value,
            'pppoe_username' => $this->pppoe_username,
            'pppoe_password' => $this->pppoe_password,
            'pppoe_isolation_profile' => $this->pppoe_isolation_profile,
            'subnet_cidr' => $this->subnet_cidr,
            'gateway_ip' => $this->gateway_ip,
            'dhcp_pool_start' => $this->dhcp_pool_start,
            'dhcp_pool_end' => $this->dhcp_pool_end,
            'static_ip_address' => $this->static_ip_address,
            'static_mac_address' => $this->static_mac_address,
            'static_queue_name' => $this->static_queue_name,
            'ip_pool_count' => $this->ip_pool_count,
            'rate_limit_mbps' => $this->rate_limit_mbps,
            'isolation_method' => $this->isolation_method?->value,
            'address_list_name' => $this->address_list_name,
            'billing_status' => $this->billing_status?->value,
            'network_status' => $this->network_status?->value,
            'overall_status' => $this->overall_status?->value,
            'activation_date' => $this->activation_date?->toDateString(),
            'legacy_secret_migrated_at' => $this->legacy_secret_migrated_at?->toISOString(),
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customer', function (): array {
                return [
                    'id' => $this->customer->id,
                    'customer_code' => $this->customer->customer_code,
                    'full_name' => $this->customer->full_name,
                ];
            }),
            'package' => $this->whenLoaded('package', function (): array {
                return [
                    'id' => $this->package->id,
                    'package_name' => $this->package->package_name,
                    'monthly_price' => $this->package->monthly_price,
                ];
            }),
            'router' => $this->whenLoaded('router', function (): array {
                return [
                    'id' => $this->router->id,
                    'router_code' => $this->router->router_code,
                    'router_name' => $this->router->router_name,
                ];
            }),
            'olt' => $this->whenLoaded('olt', function (): ?array {
                if ($this->olt === null) {
                    return null;
                }

                return [
                    'id' => $this->olt->id,
                    'olt_code' => $this->olt->olt_code,
                    'olt_name' => $this->olt->olt_name,
                ];
            }),
            'ont' => $this->whenLoaded('ont', function (): ?array {
                if ($this->ont === null) {
                    return null;
                }

                return [
                    'id' => $this->ont->id,
                    'ont_sn' => $this->ont->ont_sn,
                    'ont_name' => $this->ont->ont_name,
                    'status' => $this->ont->status,
                ];
            }),
            'vid' => $this->whenLoaded('vid', function (): ?array {
                if ($this->vid === null) {
                    return null;
                }

                return [
                    'id' => $this->vid->id,
                    'router_id' => $this->vid->router_id,
                    'vid' => $this->vid->vid,
                    'status' => $this->vid->status?->value,
                    'subnet_cidr' => $this->vid->subnet_cidr,
                    'gateway_ip' => $this->vid->gateway_ip,
                ];
            }),
            'router_operation_status' => $this->whenLoaded('routerOperationStatus', function (): ?array {
                if ($this->routerOperationStatus === null) {
                    return null;
                }

                return [
                    'id' => $this->routerOperationStatus->id,
                    'router_id' => $this->routerOperationStatus->router_id,
                    'access_mode' => $this->routerOperationStatus->access_mode?->value,
                    'isolation_target_type' => $this->routerOperationStatus->isolation_target_type?->value,
                    'pppoe_username' => $this->routerOperationStatus->pppoe_username,
                    'static_ip_address' => $this->routerOperationStatus->static_ip_address,
                    'queue_name' => $this->routerOperationStatus->queue_name,
                    'queue_found' => $this->routerOperationStatus->queue_found,
                    'address_list_name' => $this->routerOperationStatus->address_list_name,
                    'address_list_found' => $this->routerOperationStatus->address_list_found,
                    'isolation_detected_via' => $this->routerOperationStatus->isolation_detected_via,
                    'last_synced_at' => $this->routerOperationStatus->last_synced_at?->toISOString(),
                    'isolation_verified_at' => $this->routerOperationStatus->isolation_verified_at?->toISOString(),
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
