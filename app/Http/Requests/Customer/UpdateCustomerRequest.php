<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Http\Requests\AdminPanelRequest;
use App\Models\Customer;
use App\Models\Olt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_code' => $this->filled('customer_code') ? Str::upper(trim((string) $this->input('customer_code'))) : null,
            'full_name' => $this->filled('full_name') ? trim((string) $this->input('full_name')) : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'customer_type' => $this->filled('customer_type') ? strtolower((string) $this->input('customer_type')) : null,
            'status' => $this->filled('status') ? strtolower((string) $this->input('status')) : null,
            'vid_id' => $this->filled('vid_id') ? (int) $this->input('vid_id') : null,
            'access_mode' => $this->filled('access_mode') ? strtolower((string) $this->input('access_mode')) : null,
            'package_id' => $this->filled('package_id') ? (int) $this->input('package_id') : null,
            'router_id' => $this->filled('router_id') ? (int) $this->input('router_id') : null,
            'location_id' => $this->filled('location_id') ? (int) $this->input('location_id') : null,
            'preferred_olt_id' => $this->filled('preferred_olt_id') ? (int) $this->input('preferred_olt_id') : null,
            'assigned_technician_id' => $this->filled('assigned_technician_id') ? (int) $this->input('assigned_technician_id') : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return [
            'customer_code' => ['required', 'string', 'max:30', Rule::unique('customers', 'customer_code')->ignore($customer->id)],
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
            'vid_id' => ['nullable', 'integer', 'exists:vids,id'],
            'access_mode' => ['nullable', 'string', Rule::in(['pppoe', 'vlan', 'static', 'hotspot'])],
            'pppoe_username' => ['nullable', 'string', 'max:100'],
            'pppoe_password' => ['nullable', 'string', 'max:100'],
            'static_ip_address' => ['nullable', 'string', 'max:45'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'email' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string'],
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
