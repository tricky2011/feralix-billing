<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class MarkInvoiceOverdueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'force' => ['nullable', 'boolean'],
            'reference_date' => ['nullable', 'date'],
        ];
    }
}
