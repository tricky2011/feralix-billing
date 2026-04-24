<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ApiLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $username = $this->filled('username')
            ? trim((string) $this->input('username'))
            : ($this->filled('email') ? trim((string) $this->input('email')) : null);

        $this->merge([
            'username' => $username !== null
                ? strtolower($username)
                : null,
            'device_name' => $this->filled('device_name')
                ? trim((string) $this->input('device_name'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
