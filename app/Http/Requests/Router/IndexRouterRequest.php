<?php

namespace App\Http\Requests\Router;

use App\Http\Requests\AdminPanelRequest;

class IndexRouterRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');

        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'router_role' => $this->filled('router_role') ? trim((string) $this->input('router_role')) : null,
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
            'router_role' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
