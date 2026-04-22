<?php

namespace App\Http\Requests\Ont;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreOntRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ont_sn' => $this->filled('ont_sn') ? Str::upper(trim((string) $this->input('ont_sn'))) : null,
            'ont_name' => $this->filled('ont_name') ? trim((string) $this->input('ont_name')) : null,
            'pon_port' => $this->filled('pon_port') ? trim((string) $this->input('pon_port')) : null,
            'ssid_name' => $this->filled('ssid_name') ? trim((string) $this->input('ssid_name')) : null,
            'wifi_password' => $this->has('wifi_password') ? (string) $this->input('wifi_password') : null,
            'optical_status' => $this->filled('optical_status') ? trim((string) $this->input('optical_status')) : null,
            'optical_info' => $this->filled('optical_info') ? trim((string) $this->input('optical_info')) : null,
            'genieacs_device_id' => $this->filled('genieacs_device_id') ? trim((string) $this->input('genieacs_device_id')) : null,
            'status' => $this->filled('status') ? trim((string) $this->input('status')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'ont_sn' => ['required', 'string', 'max:50', 'unique:onts,ont_sn'],
            'ont_name' => ['nullable', 'string', 'max:150'],
            'pon_port' => ['nullable', 'string', 'max:50'],
            'onu_id' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssid_name' => ['nullable', 'string', 'max:100'],
            'wifi_password' => ['nullable', 'string', 'max:255'],
            'optical_status' => ['nullable', 'string', 'max:50'],
            'optical_info' => ['nullable', 'string', 'max:255'],
            'rx_power' => ['nullable', 'numeric', 'between:-100,100'],
            'tx_power' => ['nullable', 'numeric', 'between:-100,100'],
            'genieacs_device_id' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', 'max:30'],
            'last_seen_at' => ['nullable', 'date'],
            'last_inform_at' => ['nullable', 'date'],
            'genieacs_last_synced_at' => ['nullable', 'date'],
        ];
    }
}
