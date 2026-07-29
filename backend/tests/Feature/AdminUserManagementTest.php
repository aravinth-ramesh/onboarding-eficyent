<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(AdminRole $role, string $email, bool $active = true): Admin
    {
        return Admin::create(['name' => ucfirst($role->value), 'email' => $email, 'password' => 'x', 'is_active' => $active, 'role' => $role]);
    }

    // --- Access -------------------------------------------------------------

    public function test_only_admins_and_super_admins_reach_staff_management(): void
    {
        $this->actingAs($this->admin(AdminRole::Analyst, 'a@t.com'), 'admin')
            ->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($this->admin(AdminRole::Manager, 'm@t.com'), 'admin')
            ->get(route('admin.users.index'))->assertForbidden();

        $this->actingAs($this->admin(AdminRole::Admin, 'd@t.com'), 'admin')
            ->get(route('admin.users.index'))->assertOk();
        $this->actingAs($this->admin(AdminRole::SuperAdmin, 's@t.com'), 'admin')
            ->get(route('admin.users.index'))->assertOk();
    }

    // --- Create -------------------------------------------------------------

    public function test_super_admin_creates_a_staff_member_with_a_role(): void
    {
        $super = $this->admin(AdminRole::SuperAdmin, 's@t.com');

        $this->actingAs($super, 'admin')
            ->post(route('admin.users.store'), [
                'name' => 'New Analyst', 'email' => 'new@t.com',
                'password' => 'password123', 'role' => 'analyst',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $created = Admin::where('email', 'new@t.com')->sole();
        $this->assertTrue($created->isRole(AdminRole::Analyst));
        $this->assertTrue($created->is_active);
        $this->assertTrue(Hash::check('password123', $created->password));
    }

    public function test_an_admin_cannot_grant_a_role_at_or_above_their_own(): void
    {
        $admin = $this->admin(AdminRole::Admin, 'd@t.com');

        // Admin may create analysts/managers/compliance...
        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@t.com', 'password' => 'password123', 'role' => 'manager'])
            ->assertSessionHasNoErrors();

        // ...but not another admin or a super admin — the role is not assignable.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.store'), ['name' => 'Y', 'email' => 'y@t.com', 'password' => 'password123', 'role' => 'admin'])
            ->assertSessionHasErrors('role');
        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.store'), ['name' => 'Z', 'email' => 'z@t.com', 'password' => 'password123', 'role' => 'super_admin'])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('admins', ['email' => 'y@t.com']);
        $this->assertDatabaseMissing('admins', ['email' => 'z@t.com']);
    }

    // --- Edit / manage-target rules -----------------------------------------

    public function test_an_admin_cannot_edit_another_admin_or_a_super_admin(): void
    {
        $admin = $this->admin(AdminRole::Admin, 'd@t.com');
        $peer = $this->admin(AdminRole::Admin, 'peer@t.com');
        $super = $this->admin(AdminRole::SuperAdmin, 's@t.com');

        $this->actingAs($admin, 'admin')->get(route('admin.users.edit', $peer))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.users.edit', $super))->assertForbidden();

        // But can edit a manager (below them).
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $this->actingAs($admin, 'admin')->get(route('admin.users.edit', $manager))->assertOk();
    }

    public function test_nobody_can_manage_their_own_account_here(): void
    {
        $super = $this->admin(AdminRole::SuperAdmin, 's@t.com');

        $this->actingAs($super, 'admin')->get(route('admin.users.edit', $super))->assertForbidden();
        $this->actingAs($super, 'admin')->post(route('admin.users.toggle', $super))->assertForbidden();
    }

    public function test_a_super_admin_updates_role_and_optionally_password(): void
    {
        $super = $this->admin(AdminRole::SuperAdmin, 's@t.com');
        $target = $this->admin(AdminRole::Analyst, 't@t.com');
        $oldHash = $target->password;

        // Promote to manager, leave password blank (unchanged).
        $this->actingAs($super, 'admin')
            ->put(route('admin.users.update', $target), ['name' => 'Renamed', 'email' => 't@t.com', 'role' => 'manager', 'password' => ''])
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertSame('Renamed', $target->name);
        $this->assertTrue($target->isRole(AdminRole::Manager));
        $this->assertSame($oldHash, $target->password); // unchanged

        // Now set a new password.
        $this->actingAs($super, 'admin')
            ->put(route('admin.users.update', $target), ['name' => 'Renamed', 'email' => 't@t.com', 'role' => 'manager', 'password' => 'brandnew123']);
        $this->assertTrue(Hash::check('brandnew123', $target->refresh()->password));
    }

    // --- Deactivate & last-super-admin guard --------------------------------

    public function test_deactivating_a_staff_member_keeps_the_record(): void
    {
        $super = $this->admin(AdminRole::SuperAdmin, 's@t.com');
        $target = $this->admin(AdminRole::Analyst, 't@t.com');

        $this->actingAs($super, 'admin')
            ->post(route('admin.users.toggle', $target))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($target->refresh()->is_active);
        $this->assertDatabaseHas('admins', ['id' => $target->id]); // not deleted
    }

    public function test_a_super_admin_can_deactivate_another_super_admin_when_others_remain(): void
    {
        // Two super admins — deactivating one is allowed because another stays.
        // (The "last active super admin" guard is defensive: it can't actually
        //  be hit through the UI, since deactivating the last one would require
        //  the actor to be a *different* active super admin — a contradiction.)
        $super = $this->admin(AdminRole::SuperAdmin, 's@t.com');
        $other = $this->admin(AdminRole::SuperAdmin, 'other@t.com');

        $this->actingAs($super, 'admin')
            ->post(route('admin.users.toggle', $other))
            ->assertSessionHas('success');

        $this->assertFalse($other->refresh()->is_active);
        $this->assertSame(1, Admin::where('role', 'super_admin')->where('is_active', true)->count());
    }
}
