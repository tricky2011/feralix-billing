<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'category_id' => $this->category_id,
            'description' => $this->description,
            'source' => $this->source,
            'reference_type' => $this->reference_type !== null ? class_basename((string) $this->reference_type) : null,
            'reference_id' => $this->reference_id,
            'created_by' => $this->created_by,
            'can_update' => $this->source === 'manual',
            'can_delete' => $this->source === 'manual',
            'category' => $this->whenLoaded('category', function (): ?array {
                if ($this->category === null) {
                    return null;
                }

                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'type' => $this->category->type,
                ];
            }),
            'user' => $this->whenLoaded('user', function (): ?array {
                if ($this->user === null) {
                    return null;
                }

                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'username' => $this->user->username,
                ];
            }),
            'reference' => $this->whenLoaded('reference', function (): ?array {
                if ($this->reference === null) {
                    return null;
                }

                if ($this->reference instanceof Payment) {
                    return [
                        'type' => 'Payment',
                        'id' => $this->reference->id,
                        'invoice_id' => $this->reference->invoice_id,
                        'amount_paid' => $this->reference->amount_paid,
                        'payment_method' => $this->reference->payment_method,
                        'paid_at' => $this->reference->paid_at?->toISOString(),
                    ];
                }

                return [
                    'type' => class_basename(get_class($this->reference)),
                    'id' => $this->reference->id ?? null,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
