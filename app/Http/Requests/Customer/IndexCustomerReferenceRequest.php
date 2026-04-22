<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class IndexCustomerReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $onlyActive = $this->input('only_active');

        $this->merge([
            'only_active' => match (true) {
                $onlyActive === null => true,
                $onlyActive === 'true', $onlyActive === '1', $onlyActive === 1, $onlyActive === true => true,
                $onlyActive === 'false', $onlyActive === '0', $onlyActive === 0, $onlyActive === false => false,
                default => $onlyActive,
            },
        ]);
    }

    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'only_active' => ['nullable', 'boolean'],
        ];
    }
}
