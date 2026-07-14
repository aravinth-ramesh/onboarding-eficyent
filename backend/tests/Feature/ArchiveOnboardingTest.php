<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private UserOnboarding $approved;
    private UserOnboarding $awaiting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create(['name' => 'Reviewer', 'email' => 'admin@test.com', 'password' => 'x', 'is_active' => true]);
        $type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);

        $a = User::create(['email' => 'a@test.com', 'name' => 'Alice Done', 'position' => 'CFO']);
        $this->approved = UserOnboarding::create([
            'user_id' => $a->id, 'user_type_id' => $type->id,
            'status' => 'approved', 'started_at' => now(),
        ]);

        $b = User::create(['email' => 'b@test.com', 'name' => 'Bob Waiting', 'position' => 'CEO']);
        $this->awaiting = UserOnboarding::create([
            'user_id' => $b->id, 'user_type_id' => $type->id,
            'status' => 'completed', 'started_at' => now(),
        ]);
    }

    public function test_decided_applications_can_be_archived_and_leave_the_active_list(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.archive', $this->approved))
            ->assertRedirect(route('admin.user-onboardings.index'));

        $this->approved->refresh();
        $this->assertNotNull($this->approved->archived_at);
        $this->assertSame($this->admin->id, $this->approved->archived_by);

        // Default list hides it; archived filter shows only it.
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.index'))
            ->assertDontSee('Alice Done')
            ->assertSee('Bob Waiting');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.index', ['archived' => 1]))
            ->assertSee('Alice Done')
            ->assertDontSee('Bob Waiting');
    }

    public function test_undecided_applications_cannot_be_archived(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.archive', $this->awaiting))
            ->assertSessionHas('error');

        $this->assertNull($this->awaiting->fresh()->archived_at);
    }

    public function test_unarchive_restores_the_application(): void
    {
        $this->approved->update(['archived_at' => now(), 'archived_by' => $this->admin->id]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.unarchive', $this->approved))
            ->assertRedirect();

        $this->assertNull($this->approved->fresh()->archived_at);
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.index'))
            ->assertSee('Alice Done');
    }

    public function test_csv_export_follows_the_archived_filter(): void
    {
        $this->approved->update(['archived_at' => now(), 'archived_by' => $this->admin->id]);

        $active = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.export-csv'))
            ->streamedContent();
        $this->assertStringNotContainsString('a@test.com', $active);
        $this->assertStringContainsString('b@test.com', $active);

        $archived = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.export-csv', ['archived' => 1]))
            ->streamedContent();
        $this->assertStringContainsString('a@test.com', $archived);
        $this->assertStringNotContainsString('b@test.com', $archived);
    }
}
