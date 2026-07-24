<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL');
        $password = env('SUPERADMIN_PASSWORD');

        if (! $email && ! $password) {
            $this->command?->warn('Super admin não criado: configure SUPERADMIN_EMAIL e SUPERADMIN_PASSWORD.');
            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 12) {
            throw new RuntimeException('SUPERADMIN_EMAIL deve ser válido e SUPERADMIN_PASSWORD deve ter ao menos 12 caracteres.');
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => env('SUPERADMIN_NAME', 'Super Admin'),
            'password' => Hash::make($password),
        ]);
        $user->is_super_admin = true;
        $user->save();

        $this->command?->info("Super admin configurado: {$email}");
    }
}
