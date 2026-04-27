<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexTelegramBotRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'status' => $this->filled('status') ? trim((string) $this->input('status')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
