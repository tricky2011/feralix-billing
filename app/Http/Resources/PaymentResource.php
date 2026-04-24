<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'amount_paid' => $this->amount_paid,
            'payment_method' => $this->payment_method,
            'paid_at' => $this->paid_at?->toISOString(),
            'reference_no' => $this->reference_no,
            'created_by' => $this->created_by,
            'notes' => $this->notes,
            'invoice' => $this->whenLoaded('invoice', function (): array {
                return [
                    'id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'billing_period' => $this->invoice->billing_period,
                    'due_date' => $this->invoice->due_date?->toDateString(),
                    'total_amount' => $this->invoice->total_amount,
                    'payment_status' => $this->invoice->payment_status?->value,
                    'remaining_amount' => $this->invoice->remainingAmount(),
                ];
            }),
            'cashflow_transaction' => $this->whenLoaded('cashflowTransaction', function (): ?array {
                if ($this->cashflowTransaction === null) {
                    return null;
                }

                return (new CashflowTransactionResource($this->cashflowTransaction))->toArray(request());
            }),
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
                    'router_id' => $this->service->router_id,
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
