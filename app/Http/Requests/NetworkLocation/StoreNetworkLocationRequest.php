<?php

namespace App\Http\Requests\NetworkLocation;

use Illuminate\Foundation\Http\FormRequest;

class StoreNetworkLocationRequest extends FormRequest
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
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'router_id' => $this->filled('router_id') ? (int) $this->input('router_id') : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:network_locations,code'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string'],
            'router_id' => ['required', 'integer', 'exists:routers,id'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
