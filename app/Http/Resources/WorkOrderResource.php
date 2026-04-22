<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'router_id' => $this->router_id,
            'olt_id' => $this->olt_id,
            'ont_id' => $this->ont_id,
            'wo_number' => $this->wo_number,
            'wo_type' => $this->wo_type?->value,
            'assigned_technician_id' => $this->assigned_technician_id,
            'planned_monitor_vid' => $this->planned_monitor_vid,
            'planned_internet_vid' => $this->planned_internet_vid,
            'planned_subnet_cidr' => $this->planned_subnet_cidr,
            'status' => $this->status?->value,
            'work_notes' => $this->work_notes,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'customer' => $this->whenLoaded('customer', function (): ?array {
                if ($this->customer === null) {
                    return null;
                }

                return [
                    'id' => $this->customer->id,
                    'customer_code' => $this->customer->customer_code,
                    'full_name' => $this->customer->full_name,
                ];
            }),
            'service' => $this->whenLoaded('service', function (): ?array {
                if ($this->service === null) {
                    return null;
                }

                return [
                    'id' => $this->service->id,
                    'service_code' => $this->service->service_code,
                    'deleted_at' => $this->service->deleted_at?->toISOString(),
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
                    'olt_id' => $this->ont->olt_id,
                    'ont_sn' => $this->ont->ont_sn,
                    'ont_name' => $this->ont->ont_name,
                    'status' => $this->ont->status,
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
                    'is_active' => $this->assignedTechnician->is_active,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
