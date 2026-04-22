<?php

namespace App\Http\Requests\Router;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');

        $this->merge([
            'router_code' => $this->filled('router_code') ? Str::upper(trim((string) $this->input('router_code'))) : null,
            'router_name' => $this->filled('router_name') ? trim((string) $this->input('router_name')) : null,
            'router_role' => $this->filled('router_role') ? trim((string) $this->input('router_role')) : null,
            'mgmt_ip' => $this->filled('mgmt_ip') ? trim((string) $this->input('mgmt_ip')) : null,
            'api_username' => $this->filled('api_username') ? trim((string) $this->input('api_username')) : null,
            'api_password' => $this->has('api_password') ? (string) $this->input('api_password') : null,
            'location_name' => $this->filled('location_name') ? trim((string) $this->input('location_name')) : null,
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
            'router_code' => ['required', 'string', 'max:30', 'unique:routers,router_code'],
            'router_name' => ['required', 'string', 'max:150'],
            'router_role' => ['required', 'string', 'max:50'],
            'mgmt_ip' => ['required', 'ip'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'api_username' => ['required', 'string', 'max:100'],
            'api_password' => ['required', 'string', 'max:255'],
            'location_name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
