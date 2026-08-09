<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\OnboardingStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An application keeps the steps it started with, so editing a step here looks
 * like it did nothing when an in-progress application is checked. The page says
 * so rather than leaving an admin to guess (retest item 37).
 */
class OnboardingStepTemplateNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Ops', 'email' => 'ops@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::Admin,
        ]);
    }

    public function test_the_steps_page_explains_that_edits_only_affect_new_applications(): void
    {
        OnboardingStep::create([
            'name' => 'Company Details', 'slug' => 'company-details',
            'component_key' => 'company', 'order' => 1, 'is_active' => true,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.onboarding-steps.index'))
            ->assertOk()
            ->assertSee('Company Details')
            ->assertSee('apply to applications started from now on');
    }
}
