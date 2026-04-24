<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'ticket_number' => $this->ticket_number,
            'category' => $this->category,
            'priority' => $this->priority?->value,
            'description' => $this->description,
            'assigned_technician_id' => $this->assigned_technician_id,
            'assignment_mode' => $this->assignment_mode?->value,
            'status' => $this->status?->value,
            'customer' => $this->whenLoaded('customer', function (): array {
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
            'telegram_logs' => $this->whenLoaded('telegramLogs', function (): array {
                return $this->telegramLogs
                    ->map(fn ($telegramLog): array => [
                        'id' => $telegramLog->id,
                        'channel' => $telegramLog->channel,
                        'target' => $telegramLog->target,
                        'status' => $telegramLog->status?->value,
                        'message' => $telegramLog->message,
                        'processed_at' => $telegramLog->processed_at?->toISOString(),
                        'created_at' => $telegramLog->created_at?->toISOString(),
                    ])
                    ->all();
            }),
            'replies' => TicketReplyResource::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
