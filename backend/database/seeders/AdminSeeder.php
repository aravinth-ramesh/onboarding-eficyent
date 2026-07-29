<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => 'admin@eficyent.com'],
            ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true, 'role' => AdminRole::SuperAdmin],
        );

        // One of each role for development / demoing the access model.
        $roles = [
            ['analyst@eficyent.com', 'Alex Analyst', AdminRole::Analyst],
            ['manager@eficyent.com', 'Morgan Manager', AdminRole::Manager],
            ['compliance@eficyent.com', 'Casey Compliance', AdminRole::Compliance],
            ['admin2@eficyent.com', 'Dana Admin', AdminRole::Admin],
        ];

        foreach ($roles as [$email, $name, $role]) {
            Admin::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'is_active' => true, 'role' => $role],
            );
        }
    }
}
