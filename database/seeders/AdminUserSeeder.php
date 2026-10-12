<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@bookmydiagnose.in'],
            [
                'name' => 'Dr. Arvind Mehta',
                'password' => Hash::make('admin123'),
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );
    }
}
