<?php

namespace App\Http\Requests;

use App\Services\Access\RoleRouterScopeService;
use Illuminate\Foundation\Http\FormRequest;

abstract class AdminPanelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleRouterScopeService::class)->allowsAdminPanel($this->user());
    }
}
