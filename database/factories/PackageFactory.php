<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        $downloadMbps = fake()->unique()->numberBetween(110, 999);

        return [
            'package_name' => 'Internet ' . $downloadMbps . ' Mbps',
            'monthly_price' => fake()->randomFloat(2, 150000, 1000000),
            'ip_pool_count' => 5,
            'rate_limit_mbps' => 10,
            'description' => fake()->sentence(),
            'is_active' => fake()->boolean(90),
        ];
    }
}
