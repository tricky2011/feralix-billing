<?php

namespace App\Http\Requests\PonPort;

use Illuminate\Foundation\Http\FormRequest;

class StorePonPortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'port_number' => ['required', 'integer', 'min:1', 'max:32'],
            'name' => ['nullable', 'string', 'max:50'],
            'max_capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}