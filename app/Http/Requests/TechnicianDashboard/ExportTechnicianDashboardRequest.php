<?php

namespace App\Http\Requests\TechnicianDashboard;

use App\Enums\UserRole;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Foundation\Http\FormRequest;

class ExportTechnicianDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleRouterScopeService::class)->allowsRoles(
            $this->user(),
            UserRole::Superadmin,
            UserRole::Admin,
            UserRole::Technician,
        );
    }

    public function rules(): array
    {
        return [];
    }
}
