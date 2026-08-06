<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin module pages carry breadcrumb navigation (Dashboard > Section > Action)
 * so a reviewer can return without the browser Back button (bug report EOP-63).
 */
class AdminBreadcrumbTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_create_page_shows_a_breadcrumb_with_a_link_back_to_the_index(): void
    {
        $admin = Admin::create(['name' => 'Super', 'email' => 's@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::SuperAdmin]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.country-registrations.create'))
            ->assertOk()
            ->assertSee('<nav class="admin-breadcrumb"', false)
            ->assertSee(route('admin.country-registrations.index'), false)
            ->assertSee('Country Registrations')
            ->assertSee('New');
    }

    public function test_the_dashboard_has_no_breadcrumb(): void
    {
        $admin = Admin::create(['name' => 'Super', 'email' => 's2@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::SuperAdmin]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('<nav class="admin-breadcrumb"', false);
    }
}
