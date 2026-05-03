<?php

namespace App\Http\Requests\PonPort;

use Illuminate\Foundation\Http\FormRequest;

class IndexPonPortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}