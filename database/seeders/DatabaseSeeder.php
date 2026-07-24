<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SUPERADMIN_EMAIL', '');
        $password = (string) env('SUPERADMIN_PASSWORD', '');

        if ($email !== '' && $password !== '') {
            if (strlen($password) < 12) {
                throw new \RuntimeException('SUPERADMIN_PASSWORD deve possuir ao menos 12 caracteres.');
            }

            $superAdmin = User::firstOrNew(['email' => $email]);
            $superAdmin->forceFill([
                'name' => env('SUPERADMIN_NAME', 'Super Admin'),
                'password' => Hash::make($password),
                'is_super_admin' => true,
            ])->save();
        } elseif (app()->environment('production')) {
            $this->command?->warn('Super Admin não criado: configure SUPERADMIN_EMAIL e SUPERADMIN_PASSWORD.');
        }

        // Tenants reais
        $this->call(OdontoExcellenceSeeder::class);
    }
}
