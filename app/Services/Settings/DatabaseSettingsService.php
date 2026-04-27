<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class DatabaseSettingsService
{
    private const GROUP = 'database';

    /**
     * @return array{host:string,port:string,database:string,username:string,has_password:bool}
     */
    public function current(): array
    {
        $settings = $this->settings();
        $mysql = config('database.connections.mysql');

        return [
            'host' => $settings['host'] ?? (string) ($mysql['host'] ?? '127.0.0.1'),
            'port' => $settings['port'] ?? (string) ($mysql['port'] ?? '3306'),
            'database' => $settings['database'] ?? (string) ($mysql['database'] ?? ''),
            'username' => $settings['username'] ?? (string) ($mysql['username'] ?? ''),
            'has_password' => array_key_exists('password', $settings) || (string) ($mysql['password'] ?? '') !== '',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{host:string,port:string,database:string,username:string,has_password:bool}
     */
    public function update(array $payload): array
    {
        SystemSetting::write(self::GROUP, 'host', (string) $payload['host']);
        SystemSetting::write(self::GROUP, 'port', (string) $payload['port']);
        SystemSetting::write(self::GROUP, 'database', (string) $payload['database']);
        SystemSetting::write(self::GROUP, 'username', (string) $payload['username']);

        if (array_key_exists('password', $payload) && $payload['password'] !== null && $payload['password'] !== '') {
            SystemSetting::write(self::GROUP, 'password', (string) $payload['password'], encrypted: true);
        }

        return $this->current();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{connected:bool,database:string,host:string,message:string}
     */
    public function testConnection(array $payload = []): array
    {
        $settings = [
            ...$this->settings(),
            ...collect($payload)
                ->filter(static fn (mixed $value): bool => $value !== null && $value !== '')
                ->all(),
        ];

        $current = $this->current();
        $host = (string) ($settings['host'] ?? $current['host']);
        $port = (string) ($settings['port'] ?? $current['port']);
        $database = (string) ($settings['database'] ?? $current['database']);
        $username = (string) ($settings['username'] ?? $current['username']);
        $password = (string) ($settings['password'] ?? $this->settings()['password'] ?? config('database.connections.mysql.password', ''));

        config([
            'database.connections.settings_test' => [
                ...config('database.connections.mysql'),
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'unix_socket' => '',
            ],
        ]);

        DB::purge('settings_test');

        try {
            DB::connection('settings_test')->select('select 1 as connection_ok');

            return [
                'connected' => true,
                'database' => $database,
                'host' => $host,
                'message' => 'Database connection successful.',
            ];
        } finally {
            DB::disconnect('settings_test');
            DB::purge('settings_test');
        }
    }

    /**
     * @return array<string, string>
     */
    private function settings(): array
    {
        return SystemSetting::query()
            ->where('setting_group', self::GROUP)
            ->get()
            ->mapWithKeys(static fn (SystemSetting $setting): array => [
                $setting->setting_key => $setting->value(),
            ])
            ->filter(static fn (?string $value): bool => $value !== null)
            ->all();
    }
}
