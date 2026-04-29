<?php

namespace App\Http\Requests\IpPool;

use Illuminate\Foundation\Http\FormRequest;

class IndexIpPoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'router_id' => ['required', 'integer', 'exists:routers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'router_id.required' => 'Router ID is required.',
            'router_id.exists' => 'Router not found.',
        ];
    }
}
