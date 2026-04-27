<?php

namespace App\Http\Requests\Cashflow;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexCashflowRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->filled('type') ? strtolower(trim((string) $this->input('type'))) : null,
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['income', 'expense'])],
            'category_id' => ['nullable', 'integer', 'exists:cashflow_categories,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
