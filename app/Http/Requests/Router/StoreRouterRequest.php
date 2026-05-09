<?php

namespace App\Http\Requests\Router;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class StoreRouterRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $legacyIsActive = $this->input('is_active');
        $status = $this->filled('status')
            ? strtolower(trim((string) $this->input('status')))
            : null;

        if ($status === null && $legacyIsActive !== null) {
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

        $useSsl = $this->input('use_ssl');

        $this->merge([
            'name' => $this->filled('name')
                ? trim((string) $this->input('name'))
                : ($this->filled('router_name') ? trim((string) $this->input('router_name')) : null),
            'host' => $this->filled('host')
                ? trim((string) $this->input('host'))
                : ($this->filled('mgmt_ip') ? trim((string) $this->input('mgmt_ip')) : null),
            'api_port' => $this->filled('api_port') ? (int) $this->input('api_port') : null,
            'timeout' => $this->filled('timeout') ? (int) $this->input('timeout') : null,
            'use_ssl' => match (true) {
                $useSsl === true, $useSsl === 1, $useSsl === '1', $useSsl === 'true' => true,
                $useSsl === false, $useSsl === 0, $useSsl === '0', $useSsl === 'false' => false,
                default => false,
            },
            'status' => $status,
            'api_username' => $this->filled('api_username') ? trim((string) $this->input('api_username')) : null,
            'api_password' => $this->has('api_password') ? (string) $this->input('api_password') : null,
            'acs_inform_url' => $this->filled('acs_inform_url') ? trim((string) $this->input('acs_inform_url')) : null,
            'acs_nbi_url' => $this->filled('acs_nbi_url') ? trim((string) $this->input('acs_nbi_url')) : null,
            'acs_username' => $this->filled('acs_username') ? trim((string) $this->input('acs_username')) : null,
            'acs_password' => $this->has('acs_password') ? (string) $this->input('acs_password') : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            // Legacy fields accepted for backward compatibility.
            'router_code' => $this->filled('router_code') ? trim((string) $this->input('router_code')) : null,
            'router_role' => $this->filled('router_role') ? trim((string) $this->input('router_role')) : null,
            'location_name' => $this->filled('location_name') ? trim((string) $this->input('location_name')) : null,
            'ros_version' => $this->filled('ros_version') ? trim((string) $this->input('ros_version')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
            'use_ssl' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'api_username' => ['nullable', 'string', 'max:255'],
            'api_password' => ['nullable', 'string'],
            'acs_inform_url' => ['nullable', 'url'],
            'acs_nbi_url' => ['nullable', 'url'],
            'acs_username' => ['nullable', 'string', 'max:255'],
            'acs_password' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'router_code' => ['nullable', 'string', 'max:30'],
            'router_role' => ['nullable', 'string', 'max:50'],
            'location_name' => ['nullable', 'string', 'max:150'],
            'ros_version' => ['nullable', 'in:6,7'],
        ];
    }
}
