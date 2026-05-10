<?php

namespace App\Http\Requests\NetworkLocation;

use App\Models\NetworkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNetworkLocationRequest extends FormRequest
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
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
        ]);
    }

    public function rules(): array
    {
        /** @var NetworkLocation $networkLocation */
        $networkLocation = $this->route('networkLocation');

        return [
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['nullable', 'string', 'max:100', Rule::unique('network_locations', 'code')->ignore($networkLocation->id)],
            'address'   => ['nullable', 'string'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string'],
            'status'    => ['required', 'in:active,inactive'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
        ];
    }
}
