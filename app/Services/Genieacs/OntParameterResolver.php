<?php

namespace App\Services\Genieacs;

use App\Models\OntParameterProfile;

class OntParameterResolver
{
    /**
     * Resolve parameter path by brand and parameter key
     *
     * @param string $brand (e.g., 'Huawei', 'ZTE', 'FiberHome')
     * @param string $paramKey (e.g., 'ssid_2g', 'rx_power', 'pass_2g')
     * @return string|null The TR-069 parameter path
     */
    public function resolve(string $brand, string $paramKey): ?string
    {
        $profile = OntParameterProfile::findByBrand($brand);

        if ($profile === null) {
            return null;
        }

        return $profile->getParamPath($paramKey);
    }

    /**
     * Get all parameter paths for a brand
     *
     * @return array<string, string|null>
     */
    public function getAllParams(string $brand): array
    {
        $profile = OntParameterProfile::findByBrand($brand);

        if ($profile === null) {
            return [
                'ssid_2g' => null,
                'ssid_5g' => null,
                'pass_2g' => null,
                'pass_5g' => null,
                'rx_power' => null,
                'tx_power' => null,
                'uptime' => null,
                'online_status' => null,
                'ip_address' => null,
            ];
        }

        return [
            'ssid_2g' => $profile->param_ssid_2g,
            'ssid_5g' => $profile->param_ssid_5g,
            'pass_2g' => $profile->param_pass_2g,
            'pass_5g' => $profile->param_pass_5g,
            'rx_power' => $profile->param_rx_power,
            'tx_power' => $profile->param_tx_power,
            'uptime' => $profile->param_uptime,
            'online_status' => $profile->param_online_status,
            'ip_address' => $profile->param_ip_address,
        ];
    }

    /**
     * Check if brand is supported
     */
    public function isBrandSupported(string $brand): bool
    {
        return OntParameterProfile::findByBrand($brand) !== null;
    }

    /**
     * Get all supported brands
     *
     * @return array<string>
     */
    public function getSupportedBrands(): array
    {
        return OntParameterProfile::query()
            ->where('is_active', true)
            ->pluck('brand')
            ->toArray();
    }
}
