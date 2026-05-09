<?php

namespace App\Http\Requests\Router;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class UpdateRouterRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $legacyIsActive = $this->input('is_active');

        $status = null;
        if ($this->has('status')) {
            $status = $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null;
        } elseif ($this->has('is_active')) {
            $status = match (true) {
                $legacyIsActive === true,
                $legacyIsActive === 1,
                $legacyIsActive === '1',
                $legacyIsActive === 'true' => 'active',
                $legacyIsActive === false,
                $legacyIsActive === 0,
                $legacyIsActive === '0',
                $legacyIsActive === 'false' => 'inactive',
                default => null,
            };
        }

        $payload = [
            'router_code' => $this->filled('router_code') ? trim((string) $this->input('router_code')) : null,
            'router_role' => $this->filled('router_role') ? trim((string) $this->input('router_role')) : null,
            'location_name' => $this->filled('location_name') ? trim((string) $this->input('location_name')) : null,
        ];

        if ($this->has('name') || $this->has('router_name')) {
            $payload['name'] = $this->filled('name')
                ? trim((string) $this->input('name'))
                : ($this->filled('router_name') ? trim((string) $this->input('router_name')) : null);
        }

        if ($this->has('host') || $this->has('mgmt_ip')) {
            $payload['host'] = $this->filled('host')
                ? trim((string) $this->input('host'))
                : ($this->filled('mgmt_ip') ? trim((string) $this->input('mgmt_ip')) : null);
        }

        if ($this->has('api_port')) {
            $payload['api_port'] = $this->filled('api_port') ? (int) $this->input('api_port') : null;
        }

        if ($this->has('timeout')) {
            $payload['timeout'] = $this->filled('timeout') ? (int) $this->input('timeout') : null;
        }

        if ($this->has('use_ssl')) {
            $useSsl = $this->input('use_ssl');
            $payload['use_ssl'] = match (true) {
                $useSsl === true, $useSsl === 1, $useSsl === '1', $useSsl === 'true' => true,
                $useSsl === false, $useSsl === 0, $useSsl === '0', $useSsl === 'false' => false,
                default => null,
            };
        }

        if ($this->has('status') || $this->has('is_active')) {
            $payload['status'] = $status;
        }

        if ($this->has('api_username')) {
            $payload['api_username'] = $this->filled('api_username') ? trim((string) $this->input('api_username')) : null;
        }

        if ($this->has('api_password')) {
            $payload['api_password'] = $this->input('api_password');
        }

        if ($this->has('acs_inform_url')) {
            $payload['acs_inform_url'] = $this->filled('acs_inform_url') ? trim((string) $this->input('acs_inform_url')) : null;
        }

        if ($this->has('acs_nbi_url')) {
            $payload['acs_nbi_url'] = $this->filled('acs_nbi_url') ? trim((string) $this->input('acs_nbi_url')) : null;
        }

        if ($this->has('acs_username')) {
            $payload['acs_username'] = $this->filled('acs_username') ? trim((string) $this->input('acs_username')) : null;
        }

        if ($this->has('acs_password')) {
            $payload['acs_password'] = $this->input('acs_password');
        }

        if ($this->has('description')) {
            $payload['description'] = $this->filled('description') ? trim((string) $this->input('description')) : null;
        }

        if ($this->has('ros_version')) {
            $payload['ros_version'] = $this->filled('ros_version') ? trim((string) $this->input('ros_version')) : null;
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'host' => ['sometimes', 'required', 'string', 'max:255'],
            'api_port' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
            'timeout' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'use_ssl' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'api_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'api_password' => ['sometimes', 'nullable', 'string'],
            'acs_inform_url' => ['sometimes', 'nullable', 'url'],
            'acs_nbi_url' => ['sometimes', 'nullable', 'url'],
            'acs_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'acs_password' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'router_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'router_role' => ['sometimes', 'nullable', 'string', 'max:50'],
            'location_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'ros_version' => ['sometimes', 'nullable', 'in:6,7'],
        ];
    }
}
