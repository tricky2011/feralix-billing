<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class UpdateTelegramBotRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $payload = [
            'bot_name' => $this->filled('bot_name') ? trim((string) $this->input('bot_name')) : null,
            'status' => $this->filled('status') ? trim((string) $this->input('status')) : 'active',
        ];

        if ($this->has('token')) {
            $payload['token'] = $this->filled('token') ? trim((string) $this->input('token')) : null;
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'bot_name' => ['required', 'string', 'max:150'],
            'token' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
