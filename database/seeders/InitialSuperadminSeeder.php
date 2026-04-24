<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialSuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $username = strtolower(trim((string) env('APP_SUPERADMIN_USERNAME', 'arya'))) ?: 'arya';
        $email = trim((string) env('APP_SUPERADMIN_EMAIL', ''));
        $password = (string) env('APP_SUPERADMIN_PASSWORD', '') ?: 'pentium_cll230';
        $name = trim((string) env('APP_SUPERADMIN_NAME', 'Superadmin'));

        if ($username === '') {
            $this->command?->warn('Skipping superadmin seeding because APP_SUPERADMIN_USERNAME is empty.');
            return;
        }

        if ($password === '') {
            $this->command?->warn('Skipping superadmin seeding because APP_SUPERADMIN_PASSWORD is not configured.');
            return;
        }

        $user = User::query()
            ->where('username', $username)
            ->when($email !== '', fn ($query) => $query->orWhere('email', $email))
            ->first() ?? new User();

        $user->fill([
            'name' => $name !== '' ? $name : 'Superadmin',
            'username' => $username,
            'email' => $email !== '' ? $email : $user->email,
            'role' => UserRole::Superadmin->value,
            'is_active' => true,
            'email_verified_at' => $email !== '' ? now() : $user->email_verified_at,
        ]);

        if (! $user->exists || $password !== '') {
            $user->password = Hash::make($password);
        }

        $user->save();
    }
}
