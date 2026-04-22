<?php

namespace App\Services\Billing\Queries;

use App\Models\Invoice;

class InvoiceDetailQuery
{
    public function find(Invoice $invoice): Invoice
    {
        return Invoice::query()
            ->with([
                'customer:id,customer_code,full_name,phone,address',
                'service:id,customer_id,package_id,router_id,service_code,billing_status,overall_status,activation_date,address_list_name',
                'service.package:id,package_name,monthly_price',
                'service.router:id,router_code,router_name,router_role,mgmt_ip',
                'payments' => fn ($query) => $query
                    ->with([
                        'creator:id,name,email',
                        'customer:id,customer_code,full_name',
                        'service:id,service_code,billing_status,overall_status',
                        'cashflowTransaction',
                    ])
                    ->orderBy('paid_at'),
                'serviceIsolations' => fn ($query) => $query
                    ->with([
                        'router:id,router_code,router_name',
                        'creator:id,name,email',
                    ])
                    ->orderByDesc('id'),
                'cashflowTransactions' => fn ($query) => $query
                    ->with([
                        'creator:id,name,email',
                        'router:id,router_code,router_name',
                    ])
                    ->orderBy('transacted_at'),
            ])
            ->withSum('payments', 'amount_paid')
            ->findOrFail($invoice->id);
    }
}
