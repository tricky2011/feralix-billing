<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseFoundationSeeder::class,
        ]);

        if (! $this->shouldSeedSampleData()) {
            return;
        }

        $this->call([
            PackageSeeder::class,
            CustomerSeeder::class,
        ]);
    }

    private function shouldSeedSampleData(): bool
    {
        return filter_var(env('APP_SEED_SAMPLE_DATA', false), FILTER_VALIDATE_BOOL);
    }
}
