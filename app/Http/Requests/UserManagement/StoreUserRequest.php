<?php

namespace App\Http\Requests\UserManagement;

use App\Enums\UserRole;
use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');

        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'username' => $this->filled('username') ? strtolower(trim((string) $this->input('username'))) : null,
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'role' => $this->filled('role') ? trim((string) $this->input('role')) : null,
            'is_active' => match (true) {
                $isActive === null => true,
                $isActive === 'true', $isActive === '1', $isActive === 1, $isActive === true => true,
                $isActive === 'false', $isActive === '0', $isActive === 0, $isActive === false => false,
                default => $isActive,
            },
            'router_ids' => $this->input('router_ids', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash:ascii', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(UserRole::values())],
            'is_active' => ['nullable', 'boolean'],
            'router_ids' => ['nullable', 'array'],
            'router_ids.*' => ['integer', 'exists:routers,id'],
        ];
    }
}
