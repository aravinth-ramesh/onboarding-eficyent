<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_login_restores_a_soft_deleted_user_instead_of_creating_a_duplicate(): void
    {
        $user = User::create([
            'email' => 'returning@example.com',
            'name' => 'Returning User',
            'position' => 'Director',
        ]);
        $user->delete();

        $otp = app(OtpService::class)->generate($user->email);

        $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => $otp->plain_code,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
            'deleted_at' => null,
        ]);
        $this->assertSame(1, User::withTrashed()->where('email', $user->email)->count());
    }
}
