<?php

namespace App\Http\Requests\UserManagement;

use App\Enums\UserRole;
use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');

        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'role' => $this->filled('role') ? trim((string) $this->input('role')) : null,
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
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(UserRole::values())],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
