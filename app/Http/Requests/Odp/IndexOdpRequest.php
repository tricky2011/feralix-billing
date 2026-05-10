<?php

namespace App\Http\Requests\Odp;

use Illuminate\Foundation\Http\FormRequest;

class IndexOdpRequest extends FormRequest
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
            'odc_id' => $this->filled('odc_id') ? (int) $this->input('odc_id') : null,
            'olt_id' => $this->filled('olt_id') ? (int) $this->input('olt_id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'location_id' => ['nullable', 'integer', 'exists:network_locations,id'],
            'odc_id' => ['nullable', 'integer', 'exists:odcs,id'],
            'olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
