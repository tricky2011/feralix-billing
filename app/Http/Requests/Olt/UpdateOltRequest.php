<?php

namespace App\Http\Requests\Olt;

use App\Models\Olt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOltRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');
        $isActive = $this->input('is_active');

        if ($status === null && $isActive !== null) {
            $status = match (true) {
                $isActive === 'true', $isActive === '1', $isActive === 1, $isActive === true => 'active',
                $isActive === 'false', $isActive === '0', $isActive === 0, $isActive === false => 'inactive',
                default => null,
            };
        }

        $name = $this->filled('name') ? trim((string) $this->input('name')) : null;
        $code = $this->filled('code') ? trim((string) $this->input('code')) : null;
        $host = $this->filled('host') ? trim((string) $this->input('host')) : null;
        $brand = $this->filled('brand') ? trim((string) $this->input('brand')) : null;

        $this->merge([
            'name' => $name ?? ($this->filled('olt_name') ? trim((string) $this->input('olt_name')) : null),
            'code' => $code ?? ($this->filled('olt_code') ? trim((string) $this->input('olt_code')) : null),
            'host' => $host ?? ($this->filled('mgmt_ip') ? trim((string) $this->input('mgmt_ip')) : null),
            'brand' => $brand ?? ($this->filled('vendor_name') ? trim((string) $this->input('vendor_name')) : null),
            'model' => $this->filled('model') ? trim((string) $this->input('model')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'status' => $status !== null ? strtolower(trim((string) $status)) : null,
            'location_id' => $this->filled('location_id') ? (int) $this->input('location_id') : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Olt $olt */
        $olt = $this->route('olt');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', Rule::unique('olts', 'code')->ignore($olt->id)],
            'host' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'pon_ports' => ['nullable', 'integer', 'min:0'],
            'location_id' => ['nullable', 'integer', 'exists:network_locations,id'],
            'status' => ['required', 'in:active,inactive'],
            'description' => ['nullable', 'string'],
        ];
    }
}
