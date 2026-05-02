<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Http\Requests\AdminPanelRequest;
use App\Models\Olt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_code' => $this->filled('customer_code') ? Str::upper(trim((string) $this->input('customer_code'))) : null,
            'full_name' => $this->filled('full_name') ? trim((string) $this->input('full_name')) : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'customer_type' => $this->filled('customer_type') ? strtolower((string) $this->input('customer_type')) : CustomerType::Residential->value,
            'status' => $this->filled('status') ? strtolower((string) $this->input('status')) : CustomerStatus::Active->value,
            'ip_count' => $this->filled('ip_count') ? max(1, min(5, (int) $this->input('ip_count'))) : 1,
            'billing_day' => $this->filled('billing_day') ? max(1, min(28, (int) $this->input('billing_day'))) : 1,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_code' => ['required', 'string', 'max:30', 'unique:customers,customer_code'],
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'preferred_olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'customer_type' => ['required', Rule::enum(CustomerType::class)],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'ip_count' => ['nullable', 'integer', 'min:1', 'max:5'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'pppoe_username' => ['nullable', 'string', 'max:100'],
            'pppoe_password' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('preferred_olt_id') || ! $this->filled('location_id')) {
                return;
            }

            $olt = Olt::query()
                ->select(['id', 'location_id'])
                ->find($this->integer('preferred_olt_id'));

            if ($olt !== null && $olt->location_id !== null && (int) $olt->location_id !== $this->integer('location_id')) {
                $validator->errors()->add('preferred_olt_id', 'The selected OLT does not belong to the selected location.');
            }
        });
    }
}
