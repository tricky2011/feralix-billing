<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\AdminPanelRequest;

class SwitchDashboardRouterRequest extends AdminPanelRequest
{
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
