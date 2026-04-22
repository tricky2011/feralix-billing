<?php

namespace App\Http\Requests\HotspotProfile;

use App\Enums\HotspotExpiredMode;
use App\Enums\HotspotProfileUserLock;
use App\Enums\HotspotProfileValidityMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class HotspotProfilePayloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    abstract protected function currentProfileId(): ?int;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'profile_name' => $this->filled('profile_name') ? trim((string) $this->input('profile_name')) : null,
            'validity_mode' => $this->filled('validity_mode') ? strtolower(trim((string) $this->input('validity_mode'))) : null,
            'user_lock' => $this->filled('user_lock') ? strtolower(trim((string) $this->input('user_lock'))) : null,
            'expired_mode' => $this->filled('expired_mode') ? strtolower(trim((string) $this->input('expired_mode'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'profile_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('hotspot_profiles', 'profile_name')->ignore($this->currentProfileId()),
            ],
            'validity_mode' => ['required', Rule::enum(HotspotProfileValidityMode::class)],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'data_limit_bytes' => ['nullable', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'gte:price'],
            'user_lock' => ['required', Rule::enum(HotspotProfileUserLock::class)],
            'expired_mode' => ['required', Rule::enum(HotspotExpiredMode::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $expiredMode = $this->input('expired_mode');
            $validityMode = $this->input('validity_mode');

            if (in_array($expiredMode, [
                HotspotExpiredMode::Time->value,
                HotspotExpiredMode::TimeOrData->value,
            ], true)) {
                if ($validityMode !== HotspotProfileValidityMode::DaysAfterFirstLogin->value) {
                    $validator->errors()->add(
                        'validity_mode',
                        'Time-based expiry requires validity_mode days_after_first_login.'
                    );
                }

                if (! $this->filled('validity_days')) {
                    $validator->errors()->add(
                        'validity_days',
                        'validity_days is required when expired_mode uses time expiry.'
                    );
                }
            }

            if (in_array($expiredMode, [
                HotspotExpiredMode::Data->value,
                HotspotExpiredMode::TimeOrData->value,
            ], true) && ! $this->filled('data_limit_bytes')) {
                $validator->errors()->add(
                    'data_limit_bytes',
                    'data_limit_bytes is required when expired_mode uses data expiry.'
                );
            }
        });
    }
}
