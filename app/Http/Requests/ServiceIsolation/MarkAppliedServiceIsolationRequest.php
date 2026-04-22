<?php

namespace App\Http\Requests\ServiceIsolation;

use Illuminate\Foundation\Http\FormRequest;

class MarkAppliedServiceIsolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'isolated_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
