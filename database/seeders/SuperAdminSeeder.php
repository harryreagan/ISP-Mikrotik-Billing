<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Creating Super Admin User with secure default HSmart credentials
        $name = env('SEED_ADMIN_NAME', 'HSmart Super Admin');
        $email = env('SEED_ADMIN_EMAIL', 'admin@hsmart.co.ke');
        $password = env('SEED_ADMIN_PASSWORD', 'HSmart@Admin2026!');

        $superAdmin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );
        $superAdmin->assignRole('Super Admin');
    }
}
