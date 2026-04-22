<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = (new InvoiceResource($this->resource))->toArray($request);

        return [
            ...$base,
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
                            'router_role' => $this->service->router->router_role,
                            'mgmt_ip' => $this->service->router->mgmt_ip,
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
            'service_isolations' => $this->whenLoaded('serviceIsolations', function (): array {
                return $this->serviceIsolations
                    ->map(fn ($isolation): array => [
                        'id' => $isolation->id,
                        'router_id' => $isolation->router_id,
                        'isolation_type' => $isolation->isolation_type?->value,
                        'status' => $isolation->status?->value,
                        'target_subnet' => $isolation->target_subnet,
                        'isolated_at' => $isolation->isolated_at?->toISOString(),
                        'released_at' => $isolation->released_at?->toISOString(),
                        'router' => $isolation->relationLoaded('router') && $isolation->router !== null
                            ? [
                                'id' => $isolation->router->id,
                                'router_code' => $isolation->router->router_code,
                                'router_name' => $isolation->router->router_name,
                            ]
                            : null,
                    ])
                    ->all();
            }),
            'cashflow_transactions' => CashflowTransactionResource::collection($this->whenLoaded('cashflowTransactions')),
            'lifecycle' => [
                'issued_at' => $this->issued_at?->toISOString(),
                'paid_at' => $this->paid_at?->toISOString(),
                'overdue_marked_at' => $this->overdue_marked_at?->toISOString(),
                'status_changed_at' => $this->status_changed_at?->toISOString(),
                'whatsapp_sent_at' => $this->whatsapp_sent_at?->toISOString(),
                'whatsapp_recipient' => $this->whatsapp_recipient,
                'can_edit' => $this->payments->isEmpty() && $this->serviceIsolations->isEmpty(),
                'can_delete' => $this->payments->isEmpty() && $this->serviceIsolations->isEmpty(),
            ],
        ];
    }
}
