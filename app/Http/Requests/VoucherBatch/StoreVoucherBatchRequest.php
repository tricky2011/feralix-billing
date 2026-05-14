<?php

namespace App\Http\Requests\VoucherBatch;

use App\Enums\ResellerStatus;
use App\Models\HotspotProfile;
use App\Models\Reseller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVoucherBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $usernamePrefix = $this->filled('username_prefix')
            ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $this->input('username_prefix'))))
            : null;

        $this->merge([
            'username_mode' => $this->filled('username_mode') ? strtolower(trim((string) $this->input('username_mode'))) : null,
            'username_prefix' => $usernamePrefix !== '' ? $usernamePrefix : null,
            'password_mode' => $this->filled('password_mode') ? strtolower(trim((string) $this->input('password_mode'))) : null,
        ]);

        $this->replace(
            collect($this->all())
                ->except(['created_by'])
                ->toArray()
        );
    }

    public function rules(): array
    {
        return [
            'reseller_id' => ['required', 'integer', 'exists:resellers,id'],
            'hotspot_profile_id' => ['required', 'integer', 'exists:hotspot_profiles,id'],
            'total_vouchers' => ['required', 'integer', 'min:1', 'max:1000'],
            'username_mode' => ['nullable', Rule::in(['voucher_code', 'prefix_random'])],
            'username_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
            'username_length' => ['nullable', 'integer', 'min:6', 'max:30'],
            'voucher_code_length' => ['nullable', 'integer', 'min:6', 'max:20'],
            'password_mode' => ['nullable', Rule::in(['same_as_username', 'random_secure'])],
            'password_length' => ['nullable', 'integer', 'min:6', 'max:32'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('username_mode') === 'prefix_random') {
                $prefix = $this->input('username_prefix', 'HS');
                $usernameLength = (int) ($this->input('username_length') ?? 10);

                if (strlen($prefix) >= $usernameLength) {
                    $validator->errors()->add(
                        'username_length',
                        'username_length must be greater than username_prefix length for prefix_random mode.'
                    );
                }
            }

            if ($this->filled('reseller_id')) {
                $reseller = Reseller::query()
                    ->select(['id', 'status'])
                    ->find($this->integer('reseller_id'));

                if ($reseller !== null && $reseller->status !== ResellerStatus::Active) {
                    $validator->errors()->add('reseller_id', 'The selected reseller must be active.');
                }
            }

            if ($this->filled('hotspot_profile_id')) {
                $profile = HotspotProfile::query()
                    ->select(['id', 'is_active'])
                    ->find($this->integer('hotspot_profile_id'));

                if ($profile !== null && ! $profile->is_active) {
                    $validator->errors()->add('hotspot_profile_id', 'The selected hotspot profile must be active.');
                }
            }
        });
    }
}
