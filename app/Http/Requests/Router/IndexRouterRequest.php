<?php

namespace App\Http\Requests\Router;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexRouterRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $legacyIsActive = $this->input('is_active');
        $status = $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null;

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

        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'status' => $status,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
