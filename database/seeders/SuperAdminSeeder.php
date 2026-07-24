<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SUPERADMIN_EMAIL', '');
        $password = (string) env('SUPERADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            throw new \RuntimeException('Configure SUPERADMIN_EMAIL e SUPERADMIN_PASSWORD antes de executar este seeder.');
        }

        if (strlen($password) < 12) {
            throw new \RuntimeException('SUPERADMIN_PASSWORD deve possuir ao menos 12 caracteres.');
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => env('SUPERADMIN_NAME', 'Super Admin'),
            'password' => Hash::make($password),
            'is_super_admin' => true,
        ])->save();

        $this->command->info("Super admin criado/atualizado: {$email}");
    }
}
