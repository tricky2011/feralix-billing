<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\AdminPanelRequest;

class SendInvoiceWhatsappRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'message' => $this->filled('message') ? trim((string) $this->input('message')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string'],
        ];
    }
}
