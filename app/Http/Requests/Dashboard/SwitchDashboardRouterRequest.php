<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class SwitchDashboardRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $routerId = $this->input('router_id');

        $this->merge([
            'router_id' => in_array($routerId, [null, '', 'all'], true)
                ? null
                : $routerId,
        ]);
    }

    public function rules(): array
    {
        return [
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
        ];
    }
}
