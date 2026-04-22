<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::query()->create([
            'customer_code' => 'CUST-00001',
            'full_name' => 'PT Feralix Nusantara',
            'phone' => '081234567890',
            'address' => 'Jl. Fiber Optik No. 1, Jakarta',
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'customer_type' => CustomerType::Business,
            'status' => CustomerStatus::Active,
        ]);

        Customer::factory()->count(20)->create();
    }
}
