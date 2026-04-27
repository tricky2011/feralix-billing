<?php

namespace App\Http\Requests\Odc;

use Illuminate\Foundation\Http\FormRequest;

class StoreOdcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'code' => $this->filled('code') ? trim((string) $this->input('code')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
            'location_id' => $this->filled('location_id') ? (int) $this->input('location_id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer', 'exists:network_locations,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:odcs,code'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'used_ports' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $capacity = $this->input('capacity');
            $usedPorts = $this->input('used_ports');

            if ($capacity !== null && $usedPorts !== null && (int) $usedPorts > (int) $capacity) {
                $validator->errors()->add('used_ports', 'The used ports field must be less than or equal to capacity.');
            }
        });
    }
}
