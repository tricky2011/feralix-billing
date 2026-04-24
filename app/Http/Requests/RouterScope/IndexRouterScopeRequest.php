<?php

namespace App\Http\Requests\RouterScope;

use App\Http\Requests\AdminPanelRequest;

class IndexRouterScopeRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $isSpecial = $this->input('is_special');

        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'is_special' => match (true) {
                $isSpecial === 'true', $isSpecial === '1', $isSpecial === 1, $isSpecial === true => true,
                $isSpecial === 'false', $isSpecial === '0', $isSpecial === 0, $isSpecial === false => false,
                default => $isSpecial,
            },
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'is_special' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
