<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\AdminPanelRequest;

class TestTelegramGroupRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'message' => $this->filled('message') ? trim((string) $this->input('message')) : 'Feralix Billing Telegram group test message.',
        ]);
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
        ];
    }
}
