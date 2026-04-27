<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class StoreTelegramBotRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'bot_name' => $this->filled('bot_name') ? trim((string) $this->input('bot_name')) : null,
            'token' => $this->filled('token') ? trim((string) $this->input('token')) : null,
            'status' => $this->filled('status') ? trim((string) $this->input('status')) : 'active',
        ]);
    }

    public function rules(): array
    {
        return [
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'bot_name' => ['required', 'string', 'max:150'],
            'token' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
