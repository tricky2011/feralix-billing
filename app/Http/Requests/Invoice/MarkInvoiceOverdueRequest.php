<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\AdminPanelRequest;

class MarkInvoiceOverdueRequest extends AdminPanelRequest
{
    public function rules(): array
    {
        return [
            'force' => ['nullable', 'boolean'],
            'reference_date' => ['nullable', 'date'],
        ];
    }
}
