<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\AdminPanelRequest;

class MarkInvoicePaidRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_method' => $this->filled('payment_method') ? strtolower(trim((string) $this->input('payment_method'))) : null,
            'reference_no' => $this->filled('reference_no') ? trim((string) $this->input('reference_no')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);

        $this->replace(
            collect($this->all())
                ->except(['created_by'])
                ->toArray()
        );
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
