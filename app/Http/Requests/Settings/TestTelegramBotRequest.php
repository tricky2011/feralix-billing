<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\AdminPanelRequest;

class TestTelegramBotRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'chat_id' => $this->filled('chat_id') ? trim((string) $this->input('chat_id')) : null,
            'message' => $this->filled('message') ? trim((string) $this->input('message')) : 'Feralix Billing Telegram test message.',
        ]);
    }

    public function rules(): array
    {
        return [
            'chat_id' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }
}
