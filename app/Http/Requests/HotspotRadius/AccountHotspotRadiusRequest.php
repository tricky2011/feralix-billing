<?php

namespace App\Http\Requests\HotspotRadius;

use App\Enums\HotspotRadiusAccountingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountHotspotRadiusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $configuredSecret = trim((string) config('hotspot.radius.internal_secret', ''));

        if ($configuredSecret === '') {
            return false;
        }

        return hash_equals($configuredSecret, (string) $this->header('X-Hotspot-Internal-Secret'));
    }

    protected function prepareForValidation(): void
    {
        $callingStationId = $this->filled('calling_station_id')
            ? strtoupper(str_replace('-', ':', trim((string) $this->input('calling_station_id'))))
            : ($this->filled('mac_address')
                ? strtoupper(str_replace('-', ':', trim((string) $this->input('mac_address'))))
                : null);

        $this->merge([
            'acct_status_type' => $this->input('acct_status_type', $this->input('accounting_type')),
            'calling_station_id' => $callingStationId,
            'acct_session_time' => $this->input('acct_session_time', $this->input('session_time_seconds')),
        ]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:100'],
            'acct_status_type' => ['required', Rule::in(HotspotRadiusAccountingType::values())],
            'acct_session_id' => ['required', 'string', 'max:120'],
            'calling_station_id' => ['nullable', 'regex:/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/'],
            'nas_identifier' => ['nullable', 'string', 'max:120'],
            'nas_ip_address' => ['nullable', 'ip'],
            'called_station_id' => ['nullable', 'string', 'max:120'],
            'framed_ip_address' => ['nullable', 'ip'],
            'event_at' => ['nullable', 'date'],
            'acct_session_time' => ['nullable', 'integer', 'min:0'],
            'input_octets' => ['nullable', 'integer', 'min:0'],
            'output_octets' => ['nullable', 'integer', 'min:0'],
            'terminate_cause' => ['nullable', 'string', 'max:60'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
