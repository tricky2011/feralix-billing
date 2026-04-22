<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceIsolationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'router_id' => $this->router_id,
            'invoice_id' => $this->invoice_id,
            'isolation_type' => $this->isolation_type?->value,
            'target_type' => $this->target_type?->value,
            'address_list_name' => $this->address_list_name,
            'target_subnet' => $this->target_subnet,
            'target_identifier' => $this->target_identifier,
            'target_payload' => $this->target_payload,
            'redirect_url' => $this->redirect_url,
            'status' => $this->status?->value,
            'isolated_at' => $this->isolated_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'created_by' => $this->created_by,
            'notes' => $this->notes,
            'service' => $this->whenLoaded('service', function (): ?array {
                if ($this->service === null) {
                    return null;
                }

                return [
                    'id' => $this->service->id,
                    'customer_id' => $this->service->customer_id,
                    'router_id' => $this->service->router_id,
                    'service_code' => $this->service->service_code,
                    'access_mode' => $this->service->access_mode?->value,
                    'pppoe_username' => $this->service->pppoe_username,
                    'pppoe_isolation_profile' => $this->service->pppoe_isolation_profile,
                    'static_ip_address' => $this->service->static_ip_address,
                    'static_mac_address' => $this->service->static_mac_address,
                    'static_queue_name' => $this->service->static_queue_name,
                    'subnet_cidr' => $this->service->subnet_cidr,
                    'address_list_name' => $this->service->address_list_name,
                    'billing_status' => $this->service->billing_status?->value,
                    'network_status' => $this->service->network_status?->value,
                    'overall_status' => $this->service->overall_status?->value,
                ];
            }),
            'router' => $this->whenLoaded('router', function (): ?array {
                if ($this->router === null) {
                    return null;
                }

                return [
                    'id' => $this->router->id,
                    'router_code' => $this->router->router_code,
                    'router_name' => $this->router->router_name,
                ];
            }),
            'router_operation_status' => $this->whenLoaded('service', function (): ?array {
                $operationStatus = $this->service?->routerOperationStatus;

                if ($operationStatus === null) {
                    return null;
                }

                return [
                    'id' => $operationStatus->id,
                    'access_mode' => $operationStatus->access_mode?->value,
                    'isolation_target_type' => $operationStatus->isolation_target_type?->value,
                    'pppoe_username' => $operationStatus->pppoe_username,
                    'static_ip_address' => $operationStatus->static_ip_address,
                    'queue_name' => $operationStatus->queue_name,
                    'queue_found' => $operationStatus->queue_found,
                    'address_list_name' => $operationStatus->address_list_name,
                    'address_list_found' => $operationStatus->address_list_found,
                    'isolation_detected_via' => $operationStatus->isolation_detected_via,
                    'last_synced_at' => $operationStatus->last_synced_at?->toISOString(),
                    'isolation_verified_at' => $operationStatus->isolation_verified_at?->toISOString(),
                ];
            }),
            'invoice' => $this->whenLoaded('invoice', function (): ?array {
                if ($this->invoice === null) {
                    return null;
                }

                return [
                    'id' => $this->invoice->id,
                    'service_id' => $this->invoice->service_id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'due_date' => $this->invoice->due_date?->toDateString(),
                    'payment_status' => $this->invoice->payment_status?->value,
                ];
            }),
            'creator' => $this->whenLoaded('creator', function (): ?array {
                if ($this->creator === null) {
                    return null;
                }

                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
