<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'System Admin',
                'email' => 'admin@school.edu',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'cashier@school.edu',
                'password' => Hash::make('password'),
                'role' => 'cashier',
            ],
            [
                'name' => 'Juan Reyes',
                'email' => 'accountant@school.edu',
                'password' => Hash::make('password'),
                'role' => 'accountant',
            ],
            [
                'name' => 'Jose Cruz',
                'email' => 'jose@student.edu',
                'password' => Hash::make('password'),
                'role' => 'student',
            ],
            [
                'name' => 'Ana Dela Cruz',
                'email' => 'ana@student.edu',
                'password' => Hash::make('password'),
                'role' => 'student',
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
