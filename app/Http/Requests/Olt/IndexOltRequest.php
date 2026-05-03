<?php

namespace App\Http\Requests\Olt;

use Illuminate\Foundation\Http\FormRequest;

class IndexOltRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');

        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'location_id' => $this->filled('location_id') ? (int) $this->input('location_id') : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))): null,
            'is_active' => match (true) {
                $isActive === 'true', $isActive === '1', $isActive === 1, $isActive === true => true,
                $isActive === 'false', $isActive === '0', $isActive === 0, $isActive === false => false,
                default => $isActive,
            },
        ]);

        if (! $this->filled('status') && $this->has('is_active') && $this->input('is_active') !== null) {
            $this->merge([
                'status' => (bool) $this->input('is_active') ? 'active' : 'inactive',
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'location_id' => ['nullable', 'integer', 'exists:network_locations,id'],
            'status' => ['nullable', 'in:active,inactive'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
