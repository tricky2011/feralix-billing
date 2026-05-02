<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OntParameterProfile extends Model
{
    /** @use HasFactory<\Database\Factories\OntParameterProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'brand',
        'model_pattern',
        'param_ssid_2g',
        'param_ssid_5g',
        'param_pass_2g',
        'param_pass_5g',
        'param_rx_power',
        'param_tx_power',
        'param_uptime',
        'param_online_status',
        'param_ip_address',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Find profile by brand
     */
    public static function findByBrand(string $brand): ?self
    {
        return self::query()
            ->where('brand', $brand)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get parameter path by key
     */
    public function getParamPath(string $key): ?string
    {
        return match ($key) {
            'ssid_2g' => $this->param_ssid_2g,
            'ssid_5g' => $this->param_ssid_5g,
            'pass_2g' => $this->param_pass_2g,
            'pass_5g' => $this->param_pass_5g,
            'rx_power' => $this->param_rx_power,
            'tx_power' => $this->param_tx_power,
            'uptime' => $this->param_uptime,
            'online_status' => $this->param_online_status,
            'ip_address' => $this->param_ip_address,
            default => null,
        };
    }
}
