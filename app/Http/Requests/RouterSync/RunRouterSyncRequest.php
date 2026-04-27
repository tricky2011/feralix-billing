<?php

namespace App\Http\Requests\RouterSync;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class RunRouterSyncRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $routerId = $this->input('router_id');

        if (is_string($routerId)) {
            $routerId = trim($routerId);
        }

        $this->merge([
            'router_id' => $routerId,
        ]);
    }

    public function rules(): array
    {
        return [
            'router_id' => [
                'required',
                'integer',
                Rule::exists('routers', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }
}
