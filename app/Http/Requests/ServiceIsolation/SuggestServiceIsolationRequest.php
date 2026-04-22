<?php

namespace App\Http\Requests\ServiceIsolation;

use App\Enums\ServiceIsolationTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuggestServiceIsolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'target_type' => $this->filled('target_type') ? strtolower(trim((string) $this->input('target_type'))) : null,
            'limit' => $this->filled('limit') ? (int) $this->input('limit') : 20,
        ]);
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'search' => ['nullable', 'string', 'max:150'],
            'target_type' => ['nullable', Rule::enum(ServiceIsolationTargetType::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
