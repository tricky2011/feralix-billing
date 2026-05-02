<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CashflowCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Income categories
            ['name' => 'Tagihan Client', 'type' => 'income', 'description' => 'Pembayaran tagihan internet bulanan dari pelanggan.'],
            ['name' => 'Pendapatan Lain', 'type' => 'income', 'description' => 'Sumber pendapatan selain tagihan client.'],
            ['name' => 'Biaya Pemasangan', 'type' => 'income', 'description' => 'Pendapatan dari biaya pemasangan baru.'],
            ['name' => 'Penalties', 'type' => 'income', 'description' => 'Denda keterlambatan pembayaran.'],

            // Expense categories
            ['name' => 'Biaya Listrik', 'type' => 'expense', 'description' => 'Pembayaran tagihan listrik.'],
            ['name' => 'Gaji', 'type' => 'expense', 'description' => 'Pembayaran gaji karyawan.'],
            ['name' => 'Sewa', 'type' => 'expense', 'description' => 'Pembayaran sewa gedung/tempat.'],
            ['name' => 'Pembelian Alat', 'type' => 'expense', 'description' => 'Pembelian router, kabel, dan alat jaringan lainnya.'],
            ['name' => 'Perbaikan', 'type' => 'expense', 'description' => 'Biaya perbaikan dan maintenance.'],
            ['name' => 'Operasional', 'type' => 'expense', 'description' => 'Biaya operasional lainnya.'],
        ];

        foreach ($categories as $category) {
            \App\Models\CashflowCategory::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
