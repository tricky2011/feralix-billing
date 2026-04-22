<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashflowTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'invoice_id' => $this->invoice_id,
            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'router_id' => $this->router_id,
            'direction' => $this->direction,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount,
            'transacted_at' => $this->transacted_at?->toISOString(),
            'created_by' => $this->created_by,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
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
