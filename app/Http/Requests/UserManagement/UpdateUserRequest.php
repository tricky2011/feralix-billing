<?php

namespace App\Http\Requests\UserManagement;

use App\Enums\UserRole;
use App\Http\Requests\AdminPanelRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');
        $payload = [
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'username' => $this->filled('username') ? strtolower(trim((string) $this->input('username'))) : null,
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'role' => $this->filled('role') ? trim((string) $this->input('role')) : null,
            'is_active' => match (true) {
                $isActive === 'true', $isActive === '1', $isActive === 1, $isActive === true => true,
                $isActive === 'false', $isActive === '0', $isActive === 0, $isActive === false => false,
                default => $isActive,
            },
        ];

        if ($this->has('password')) {
            $payload['password'] = $this->filled('password') ? (string) $this->input('password') : null;
        }

        if ($this->has('router_ids')) {
            $payload['router_ids'] = $this->input('router_ids') ?? [];
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(UserRole::values())],
            'is_active' => ['nullable', 'boolean'],
            'router_ids' => ['nullable', 'array'],
            'router_ids.*' => ['integer', 'exists:routers,id'],
        ];
    }
}
