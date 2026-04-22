<?php

namespace App\Http\Requests\HotspotProfile;

use App\Enums\HotspotExpiredMode;
use App\Enums\HotspotProfileValidityMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexHotspotProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');

        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'validity_mode' => $this->filled('validity_mode') ? strtolower(trim((string) $this->input('validity_mode'))) : null,
            'expired_mode' => $this->filled('expired_mode') ? strtolower(trim((string) $this->input('expired_mode'))) : null,
            'is_active' => match (true) {
                $isActive === 'true', $isActive === '1', $isActive === 1, $isActive === true => true,
                $isActive === 'false', $isActive === '0', $isActive === 0, $isActive === false => false,
                default => $isActive,
            },
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'validity_mode' => ['nullable', Rule::enum(HotspotProfileValidityMode::class)],
            'expired_mode' => ['nullable', Rule::enum(HotspotExpiredMode::class)],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
