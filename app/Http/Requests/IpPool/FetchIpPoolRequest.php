<?php

namespace App\Http\Requests\IpPool;

use Illuminate\Foundation\Http\FormRequest;

class FetchIpPoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pool_name' => ['nullable', 'string', 'max:255'],
            'vlan_id' => ['nullable', 'integer', 'min:1', 'max:4094'],
            'available_only' => ['nullable', 'boolean'],
            'min_free_ips' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'vlan_id.min' => 'VLAN ID must be between 1 and 4094.',
            'vlan_id.max' => 'VLAN ID must be between 1 and 4094.',
            'min_free_ips.min' => 'Minimum free IPs must be at least 1.',
            'limit.max' => 'Limit cannot exceed 100.',
        ];
    }
}
