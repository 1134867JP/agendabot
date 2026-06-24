<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@admin.com'],
            [
                'name'           => 'Super Admin',
                'password'       => Hash::make('admin123'),
                'is_super_admin' => true,
            ],
        );

        $this->command->info('Super admin criado: superadmin@admin.com / admin123');
    }
}
