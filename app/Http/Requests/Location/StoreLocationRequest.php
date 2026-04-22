<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');

        $this->merge([
            'location_code' => $this->filled('location_code') ? Str::upper(trim((string) $this->input('location_code'))) : null,
            'location_name' => $this->filled('location_name') ? trim((string) $this->input('location_name')) : null,
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'is_active' => match (true) {
                $isActive === 'true', $isActive === '1', $isActive === 1, $isActive === true => true,
                $isActive === 'false', $isActive === '0', $isActive === 0, $isActive === false => false,
                default => $isActive,
            },
        ]);
    }

    public function rules(): array
    {
        return [
            'location_code' => ['required', 'string', 'max:30', 'unique:locations,location_code'],
            'location_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
