<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'setting_group' => 'app',
                'setting_key' => 'app.name',
                'setting_value' => (string) config('app.name', 'Feralix Billing'),
                'value_type' => 'string',
                'is_public' => true,
            ],
            [
                'setting_group' => 'app',
                'setting_key' => 'app.locale',
                'setting_value' => (string) config('app.locale', 'en'),
                'value_type' => 'string',
                'is_public' => true,
            ],
            [
                'setting_group' => 'app',
                'setting_key' => 'app.timezone',
                'setting_value' => (string) config('app.timezone', 'UTC'),
                'value_type' => 'string',
                'is_public' => true,
            ],
            [
                'setting_group' => 'security',
                'setting_key' => 'security.demo_mode',
                'setting_value' => '0',
                'value_type' => 'boolean',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $setting) {
            AppSetting::query()->updateOrCreate(
                ['setting_key' => $setting['setting_key']],
                $setting,
            );
        }
    }
}
