<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewCustomerProvisioningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subnet_cidr' => $this->filled('subnet_cidr') ? trim((string) $this->input('subnet_cidr')) : null,
            'gateway_ip' => $this->filled('gateway_ip') ? trim((string) $this->input('gateway_ip')) : null,
            'dhcp_pool_start' => $this->filled('dhcp_pool_start') ? trim((string) $this->input('dhcp_pool_start')) : null,
            'dhcp_pool_end' => $this->filled('dhcp_pool_end') ? trim((string) $this->input('dhcp_pool_end')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'vid_id' => ['nullable', 'integer', 'exists:vids,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'exclude_service_id' => ['nullable', 'integer', 'exists:services,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'subnet_cidr' => ['nullable', 'string', 'max:50'],
            'gateway_ip' => ['nullable', 'ip'],
            'dhcp_pool_start' => ['nullable', 'ip'],
            'dhcp_pool_end' => ['nullable', 'ip'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('vid_id') && ! $this->filled('service_id') && ! $this->filled('subnet_cidr')) {
                $validator->errors()->add('source', 'Provide a vid_id, service_id, or subnet/network payload for preview.');
            }

            if ($this->filled('dhcp_pool_start') xor $this->filled('dhcp_pool_end')) {
                $validator->errors()->add('dhcp_pool_end', 'Both DHCP pool start and end are required when previewing a custom network.');
            }
        });
    }
}
