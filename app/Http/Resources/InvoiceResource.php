<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'invoice_number' => $this->invoice_number,
            'billing_period' => $this->billing_period,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => $this->subtotal,
            'penalty_amount' => $this->penalty_amount,
            'total_amount' => $this->total_amount,
            'payment_status' => $this->payment_status?->value,
            'amount_paid' => $this->amountPaid(),
            'remaining_amount' => $this->remainingAmount(),
            'is_overdue' => $this->isOverdue(),
            'issued_at' => $this->issued_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'overdue_marked_at' => $this->overdue_marked_at?->toISOString(),
            'status_changed_at' => $this->status_changed_at?->toISOString(),
            'whatsapp_sent_at' => $this->whatsapp_sent_at?->toISOString(),
            'whatsapp_recipient' => $this->whatsapp_recipient,
            'customer' => $this->whenLoaded('customer', function (): array {
                return [
                    'id' => $this->customer->id,
                    'customer_code' => $this->customer->customer_code,
                    'full_name' => $this->customer->full_name,
                ];
            }),
            'service' => $this->whenLoaded('service', function (): array {
                return [
                    'id' => $this->service->id,
                    'service_code' => $this->service->service_code,
                    'billing_status' => $this->service->billing_status?->value,
                    'overall_status' => $this->service->overall_status?->value,
                    'router' => $this->service->relationLoaded('router') && $this->service->router !== null
                        ? [
                            'id' => $this->service->router->id,
                            'router_code' => $this->service->router->router_code,
                            'router_name' => $this->service->router->router_name,
                        ]
                        : null,
                    'package' => $this->service->relationLoaded('package') && $this->service->package !== null
                        ? [
                            'id' => $this->service->package->id,
                            'package_name' => $this->service->package->package_name,
                            'monthly_price' => $this->service->package->monthly_price,
                        ]
                        : null,
                ];
            }),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
