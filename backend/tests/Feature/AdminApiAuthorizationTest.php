<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_tokens_cannot_reach_the_admin_json_api(): void
    {
        $client = User::create(['email' => 'client@test.com', 'name' => 'Client', 'position' => 'CFO']);

        Sanctum::actingAs($client);
        $this->getJson('/api/admin/user-types')->assertStatus(403);
        $this->getJson('/api/admin/user-onboardings')->assertStatus(403);
        $this->postJson('/api/admin/question-groups', ['name' => 'x'])->assertStatus(403);
    }

    public function test_admin_flagged_users_can_reach_the_admin_json_api(): void
    {
        $admin = User::create(['email' => 'admin@test.com', 'name' => 'Admin', 'position' => 'Ops', 'is_admin' => true]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/user-types')->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/admin/user-types')->assertStatus(401);
    }
}
