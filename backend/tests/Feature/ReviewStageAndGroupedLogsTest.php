<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\AnswerAuditLog;
use App\Models\CountryRegistration;
use App\Models\OnboardingSectionReview;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Support\CountryRegistrationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The onboarding list could not distinguish review stages (item 9), the two
 * activity logs read line by line (items 6 and 8), and the country catalog was
 * empty on any installation that had been migrated but never seeded (item 1).
 */
class ReviewStageAndGroupedLogsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Ops', 'email' => 'ops@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::SuperAdmin,
        ]);
    }

    /** An application with one answered group, optionally signed off. */
    private function application(string $reference, bool $reviewed): UserOnboarding
    {
        $group = QuestionGroup::create(['name' => 'G'.$reference, 'slug' => 's-'.uniqid(), 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Legal name', 'type' => 'text',
            'is_required' => false, 'order' => 1, 'is_active' => true,
        ]);

        $user = User::create(['email' => $reference.'@test.com', 'name' => 'Client', 'position' => 'CFO']);
        $onboarding = UserOnboarding::create([
            'user_id' => $user->id, 'reference' => $reference, 'status' => 'completed',
            'started_at' => now(), 'completed_at' => now(),
        ]);

        UserAnswer::create([
            'user_onboarding_id' => $onboarding->id, 'user_id' => $user->id,
            'question_id' => $question->id, 'value' => 'Acme Ltd',
        ]);

        if ($reviewed) {
            OnboardingSectionReview::create([
                'user_onboarding_id' => $onboarding->id, 'question_group_id' => $group->id,
                'status' => 'completed', 'reviewed_at' => now(),
            ]);
        }

        return $onboarding;
    }

    public function test_a_fully_reviewed_application_is_flagged_ready(): void
    {
        $this->application('REF-READY', reviewed: true);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.user-onboardings.index'))
            ->assertOk()
            ->assertSee('Ready');
    }

    public function test_an_unreviewed_application_says_so(): void
    {
        $this->application('REF-NEW', reviewed: false);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.user-onboardings.index'))
            ->assertOk()
            ->assertSee('Not started');
    }

    public function test_the_underlying_status_is_untouched(): void
    {
        // The indicator is derived — the status column and the approval
        // workflow stay exactly as they were.
        $onboarding = $this->application('REF-SAME', reviewed: true);

        $this->assertSame('completed', $onboarding->fresh()->status);
        $this->assertTrue($onboarding->fresh()->sectionReviewProgress()['complete']);
    }

    public function test_client_changes_are_grouped_under_their_application(): void
    {
        $onboarding = $this->application('REF-GROUPED', reviewed: false);
        $answer = $onboarding->answers()->first();

        foreach (['Acme Ltd' => 'Acme Holdings', 'Acme Holdings' => 'Acme Group'] as $old => $new) {
            AnswerAuditLog::create([
                'user_answer_id' => $answer->id, 'question_id' => $answer->question_id,
                'user_id' => $onboarding->user_id, 'edited_by' => $onboarding->user_id,
                'old_value' => $old, 'new_value' => $new, 'edited_at' => now(),
            ]);
        }

        // The reference is generated on create, so assert the real one.
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee($onboarding->fresh()->reference)
            ->assertSee('2 changes');
    }

    public function test_admin_activity_is_grouped_under_each_member_of_staff(): void
    {
        $admin = $this->admin();

        foreach (['viewed', 'updated'] as $action) {
            AdminActivityLog::create([
                'admin_id' => $admin->id, 'action' => $action,
                'path' => '/admin/user-onboardings', 'method' => 'GET', 'status' => 200,
            ]);
        }

        $this->actingAs($admin, 'admin')
            ->get(route('admin.admin-activity.index'))
            ->assertOk()
            ->assertSee('Ops')
            ->assertSee('actions');
    }

    public function test_the_country_catalog_is_populated_without_running_the_seeder(): void
    {
        // A deploy runs migrations, not seeders — the screen was empty while the
        // onboarding form worked from the config fallback (item 1). The
        // migration now populates it, so clear the table to prove the applier
        // is what fills it rather than asserting on migration side effects.
        CountryRegistration::query()->delete();
        $this->assertSame(0, CountryRegistration::count());

        CountryRegistrationCatalog::apply();

        $this->assertGreaterThan(0, CountryRegistration::count());
        $this->assertTrue(
            CountryRegistration::where('country_code', '*')->exists(),
            'the generic defaults are listed too',
        );
    }

    public function test_populating_the_catalog_twice_does_not_duplicate_rows(): void
    {
        CountryRegistrationCatalog::apply();
        $first = CountryRegistration::count();

        CountryRegistrationCatalog::apply();

        $this->assertSame($first, CountryRegistration::count());
    }
}
