<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'customer_code' => 'CUST-' . fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'location_id' => null,
            'preferred_olt_id' => null,
            'assigned_technician_id' => null,
            'latitude' => fake()->optional(0.6)->latitude(-6.4, -6.0),
            'longitude' => fake()->optional(0.6)->longitude(106.6, 107.1),
            'customer_type' => fake()->randomElement(CustomerType::cases()),
            'status' => fake()->randomElement(CustomerStatus::cases()),
        ];
    }
}
