<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'package_name' => 'Starter 10 Mbps',
                'monthly_price' => 150000,
                'ip_pool_count' => 5,
                'rate_limit_mbps' => 10,
                'description' => 'Paket dasar FTTH untuk pelanggan rumahan.',
                'is_active' => true,
            ],
            [
                'package_name' => 'Business 20 Mbps',
                'monthly_price' => 350000,
                'ip_pool_count' => 5,
                'rate_limit_mbps' => 10,
                'description' => 'Paket usaha kecil dengan dedicated internet VID.',
                'is_active' => true,
            ],
            [
                'package_name' => 'Legacy Suspended Package',
                'monthly_price' => 250000,
                'ip_pool_count' => 5,
                'rate_limit_mbps' => 10,
                'description' => 'Contoh paket nonaktif untuk testing master data.',
                'is_active' => false,
            ],
        ];

        foreach ($packages as $package) {
            Package::query()->updateOrCreate(
                ['package_name' => $package['package_name']],
                $package,
            );
        }

        Package::factory()->count(5)->create();
    }
}
