<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        User::firstOrCreate(
            ['email' => 'joao@agendou.com'],
            [
                'name'           => 'João Pedro',
                'password'       => Hash::make('password'),
                'is_super_admin' => true,
            ]
        );

        // Tenants reais
        $this->call(OdontoExcellenceSeeder::class);
    }
}
