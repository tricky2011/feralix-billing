<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AppSettingSeeder::class,
            InitialSuperadminSeeder::class,
        ]);
    }
}
