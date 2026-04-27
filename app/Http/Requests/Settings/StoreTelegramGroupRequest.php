<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class StoreTelegramGroupRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'group_name' => $this->filled('group_name') ? trim((string) $this->input('group_name')) : null,
            'chat_id' => $this->filled('chat_id') ? trim((string) $this->input('chat_id')) : null,
            'group_type' => $this->filled('group_type') ? trim((string) $this->input('group_type')) : null,
            'status' => $this->filled('status') ? trim((string) $this->input('status')) : 'active',
        ]);
    }

    public function rules(): array
    {
        return [
            'telegram_bot_id' => ['required', 'integer', 'exists:telegram_bots,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'group_name' => ['required', 'string', 'max:150'],
            'chat_id' => ['required', 'string', 'max:120'],
            'group_type' => ['required', Rule::in(['teknisi', 'admin', 'owner', 'alert'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
