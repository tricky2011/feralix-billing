<?php

namespace App\Services\Settings;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class AppSettingService
{
    private const CACHE_PREFIX = 'app_setting:';

    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Get setting value by key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = AppSetting::query()
                ->where('key', $key)
                ->first();

            return $setting?->value ?? $default;
        });
    }

    /**
     * Set setting value
     */
    public function set(string $key, mixed $value): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        // Invalidate cache
        Cache::forget(self::CACHE_PREFIX . $key);

        // Broadcast event for multi-server sync
        Event::dispatch(new \App\Events\SettingUpdated($key, $value));
    }

    /**
     * Set multiple settings at once
     *
     * @param array<string, mixed> $data
     */
    public function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Get all settings as key-value array
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember('app_settings:all', self::CACHE_TTL, function () {
            return AppSetting::query()
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Get billing settings
     *
     * @return array<string, mixed>
     */
    public function getBillingSettings(): array
    {
        return [
            'billing_suspend_day' => $this->get('billing_suspend_day', 10),
            'billing_grace_days' => $this->get('billing_grace_days', 0),
            'billing_invoice_lead_days' => $this->get('billing_invoice_lead_days', 7),
            'billing_reminder_lead_days' => $this->get('billing_reminder_lead_days', 3),
            'billing_reminder_time' => $this->get('billing_reminder_time', '09:00'),
            'billing_suspend_time' => $this->get('billing_suspend_time', '00:15'),
            'billing_ppn_percent' => $this->get('billing_ppn_percent', 0),
            'billing_payment_instruction' => $this->get('billing_payment_instruction'),
        ];
    }

    /**
     * Get MikroTik settings
     *
     * @return array<string, mixed>
     */
    public function getMikrotikSettings(): array
    {
        return [
            'mikrotik_vlan_bridge_name' => $this->get('mikrotik_vlan_bridge_name', 'bridge-trunk'),
            'mikrotik_pppoe_default_profile' => $this->get('mikrotik_pppoe_default_profile', 'default'),
            'mikrotik_isolation_profile' => $this->get('mikrotik_isolation_profile', 'isolated'),
            'mikrotik_isolation_address_list' => $this->get('mikrotik_isolation_address_list', 'ISOLIR_CUSTOMER'),
        ];
    }

    /**
     * Get GenieACS settings
     *
     * @return array<string, mixed>
     */
    public function getGenieacsSettings(): array
    {
        return [
            'genieacs_nbi_url' => $this->get('genieacs_nbi_url', 'http://localhost:7557'),
            'genieacs_nbi_username' => $this->get('genieacs_nbi_username'),
            'genieacs_nbi_password' => $this->get('genieacs_nbi_password'),
            'genieacs_cwmp_url' => $this->get('genieacs_cwmp_url'),
            'genieacs_acs_username' => $this->get('genieacs_acs_username', 'admin'),
            'genieacs_acs_password' => $this->get('genieacs_acs_password'),
        ];
    }

    /**
     * Get Hotspot settings
     *
     * @return array<string, mixed>
     */
    public function getHotspotSettings(): array
    {
        return [
            'hotspot_radius_coa_port' => $this->get('hotspot_radius_coa_port', 3799),
            'hotspot_radius_default_nas_secret' => $this->get('hotspot_radius_default_nas_secret', 'secret'),
            'hotspot_radius_timeout' => $this->get('hotspot_radius_timeout', 5),
            'hotspot_radclient_path' => $this->get('hotspot_radclient_path', '/usr/bin/radclient'),
        ];
    }

    /**
     * Get Telegram settings
     *
     * @return array<string, mixed>
     */
    public function getTelegramSettings(): array
    {
        return [
            'telegram_bot_token' => $this->get('telegram_bot_token'),
            'telegram_group_id' => $this->get('telegram_group_id'),
            'telegram_enabled' => $this->get('telegram_enabled', false),
        ];
    }

    /**
     * Check if setting exists
     */
    public function has(string $key): bool
    {
        return Cache::remember(self::CACHE_PREFIX . "has:{$key}", self::CACHE_TTL, function () use ($key) {
            return AppSetting::query()->where('key', $key)->exists();
        });
    }

    /**
     * Delete a setting
     */
    public function delete(string $key): void
    {
        AppSetting::query()->where('key', $key)->delete();
        Cache::forget(self::CACHE_PREFIX . $key);
        Cache::forget('app_settings:all');
    }

    /**
     * Clear all settings cache
     */
    public function clearCache(): void
    {
        Cache::flush();
    }
}
