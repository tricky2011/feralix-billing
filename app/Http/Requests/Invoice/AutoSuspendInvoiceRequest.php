<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\AdminPanelRequest;

class AutoSuspendInvoiceRequest extends AdminPanelRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'reference_date' => ['nullable', 'date'],
            'chunk' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
