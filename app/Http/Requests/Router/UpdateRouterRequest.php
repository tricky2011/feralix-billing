<?php

namespace App\Http\Requests\Router;

use App\Http\Requests\AdminPanelRequest;
use App\Models\Router;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateRouterRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');
        $payload = [
            'router_code' => $this->filled('router_code') ? Str::upper(trim((string) $this->input('router_code'))) : null,
            'router_name' => $this->filled('router_name') ? trim((string) $this->input('router_name')) : null,
            'router_role' => $this->filled('router_role') ? trim((string) $this->input('router_role')) : null,
            'mgmt_ip' => $this->filled('mgmt_ip') ? trim((string) $this->input('mgmt_ip')) : null,
            'api_username' => $this->filled('api_username') ? trim((string) $this->input('api_username')) : null,
            'location_name' => $this->filled('location_name') ? trim((string) $this->input('location_name')) : null,
            'is_active' => match (true) {
                $isActive === 'true', $isActive === '1', $isActive === 1, $isActive === true => true,
                $isActive === 'false', $isActive === '0', $isActive === 0, $isActive === false => false,
                default => $isActive,
            },
        ];

        if ($this->has('api_password')) {
            $payload['api_password'] = (string) $this->input('api_password');
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        /** @var Router $router */
        $router = $this->route('router');

        return [
            'router_code' => ['required', 'string', 'max:30', Rule::unique('routers', 'router_code')->ignore($router->id)],
            'router_name' => ['required', 'string', 'max:150'],
            'router_role' => ['required', 'string', 'max:50'],
            'mgmt_ip' => ['required', 'ip'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'api_username' => ['required', 'string', 'max:100'],
            'api_password' => ['nullable', 'string', 'max:255'],
            'location_name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
