<?php

namespace App\Http\Requests\Ont;

use Illuminate\Foundation\Http\FormRequest;

class IndexOntRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'status' => $this->filled('status') ? trim((string) $this->input('status')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
