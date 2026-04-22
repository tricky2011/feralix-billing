<?php

namespace App\Http\Requests\HotspotRadius;

use Illuminate\Foundation\Http\FormRequest;

class AuthorizeHotspotRadiusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $configuredSecret = trim((string) config('hotspot.radius.internal_secret', ''));

        if ($configuredSecret === '') {
            return app()->environment(['local', 'testing']);
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
            'calling_station_id' => $callingStationId,
            'requested_at' => $this->input('requested_at', $this->input('login_at')),
        ]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
            'calling_station_id' => ['required', 'regex:/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/'],
            'nas_identifier' => ['nullable', 'string', 'max:120'],
            'nas_ip_address' => ['nullable', 'ip'],
            'called_station_id' => ['nullable', 'string', 'max:120'],
            'requested_at' => ['nullable', 'date'],
            'context' => ['nullable', 'array'],
        ];
    }
}
