<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceAccessMode;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Http\Requests\AdminPanelRequest;
use App\Models\Olt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerProvisioningRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_code' => $this->filled('customer_code') ? Str::upper(trim((string) $this->input('customer_code'))) : null,
            'full_name' => $this->filled('full_name') ? trim((string) $this->input('full_name')) : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'contact' => $this->filled('contact') ? trim((string) $this->input('contact')) : null,
            'email' => $this->filled('email') ? trim((string) $this->input('email')) : null,
            'customer_type' => $this->filled('customer_type') ? strtolower((string) $this->input('customer_type')) : CustomerType::Residential->value,
            'status' => $this->filled('status') ? strtolower((string) $this->input('status')) : CustomerStatus::Active->value,
            'install_date' => $this->filled('install_date') ? trim((string) $this->input('install_date')) : null,
            'latitude' => $this->filled('latitude') ? trim((string) $this->input('latitude')) : null,
            'longitude' => $this->filled('longitude') ? trim((string) $this->input('longitude')) : null,
            'pppoe_username' => $this->filled('pppoe_username') ? trim((string) $this->input('pppoe_username')) : null,
            'pppoe_password' => $this->filled('pppoe_password') ? trim((string) $this->input('pppoe_password')) : null,
            'pppoe_server' => $this->filled('pppoe_server') ? trim((string) $this->input('pppoe_server')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_code' => ['required', 'string', 'max:30', 'unique:customers,customer_code'],
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'contact' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'location_id' => ['nullable', 'integer', 'exists:network_locations,id'],
            'preferred_olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'install_date' => ['nullable', 'date'],
            'customer_type' => ['required', Rule::enum(CustomerType::class)],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'pppoe_username' => ['nullable', 'string', 'max:100'],
            'pppoe_password' => ['nullable', 'string', 'max:50'],
            'pppoe_server' => ['nullable', 'string', 'max:100'],
            'monitor_vid' => ['nullable', 'integer', 'between:1,4094'],
            'internet_vid' => ['nullable', 'integer', 'between:1,4094'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('preferred_olt_id') || ! $this->filled('location_id')) {
                return;
            }

            $olt = Olt::query()
                ->select(['id', 'location_id', 'network_location_id'])
                ->find($this->integer('preferred_olt_id'));

            if ($olt !== null) {
                $oltLocationId = $olt->network_location_id ?? $olt->location_id;
                if ($oltLocationId !== null && (int) $oltLocationId !== $this->integer('location_id')) {
                    $validator->errors()->add('preferred_olt_id', 'The selected OLT does not belong to the selected location.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama pelanggan harus diisi.',
            'pppoe_username.required' => 'Username PPPoE harus diisi.',
            'pppoe_password.required' => 'Password PPPoE harus diisi.',
        ];
    }
}
