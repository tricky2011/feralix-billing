<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\AdminPanelRequest;

class UpdateDatabaseSettingsRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $payload = [
            'host' => $this->filled('host') ? trim((string) $this->input('host')) : null,
            'port' => $this->filled('port') ? trim((string) $this->input('port')) : '3306',
            'database' => $this->filled('database') ? trim((string) $this->input('database')) : null,
            'username' => $this->filled('username') ? trim((string) $this->input('username')) : null,
        ];

        if ($this->has('password')) {
            $payload['password'] = $this->filled('password') ? (string) $this->input('password') : null;
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
