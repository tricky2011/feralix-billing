<?php

namespace App\Http\Requests\Odc;

use Illuminate\Foundation\Http\FormRequest;

class IndexOdcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
            'location_id' => $this->filled('location_id') ? (int) $this->input('location_id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'location_id' => ['nullable', 'integer', 'exists:network_locations,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
