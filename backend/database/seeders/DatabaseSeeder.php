<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);

        // Create a test user
        User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'email_verified_at' => now(),
        ]);

        $this->call(OnboardingDataSeeder::class);
        $this->call(CountryRegistrationSeeder::class);
    }
}
