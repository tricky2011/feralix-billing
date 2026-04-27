<?php

namespace App\Http\Requests\TechnicianDashboard;

use App\Enums\UserRole;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShowTechnicianDashboardRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $period = $this->filled('period')
            ? strtolower(trim((string) $this->input('period')))
            : null;

        $this->merge([
            'period' => $period,
            'technician_id' => $this->filled('technician_id')
                ? (int) $this->input('technician_id')
                : null,
            'date_from' => $this->filled('date_from')
                ? trim((string) $this->input('date_from'))
                : null,
            'date_to' => $this->filled('date_to')
                ? trim((string) $this->input('date_to'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'date_from' => ['nullable', 'date', 'required_with:date_to'],
            'date_to' => ['nullable', 'date', 'required_with:date_from', 'after_or_equal:date_from'],
            'period' => ['nullable', Rule::in(['today', 'week', 'month', 'custom'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->input('period') === 'custom'
                && (! $this->filled('date_from') || ! $this->filled('date_to'))
            ) {
                $validator->errors()->add('period', 'The custom period requires both date_from and date_to.');
            }
        });
    }
}
