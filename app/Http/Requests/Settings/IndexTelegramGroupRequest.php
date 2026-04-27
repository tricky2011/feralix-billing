<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexTelegramGroupRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'group_type' => $this->filled('group_type') ? trim((string) $this->input('group_type')) : null,
            'status' => $this->filled('status') ? trim((string) $this->input('status')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'telegram_bot_id' => ['nullable', 'integer', 'exists:telegram_bots,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'group_type' => ['nullable', Rule::in(['teknisi', 'admin', 'owner', 'alert'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
