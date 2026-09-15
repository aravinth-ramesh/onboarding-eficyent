<?php

namespace Tests\Feature;

use App\Models\CountryRegistration;
use App\Models\Question;
use App\Models\QuestionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One command to bring an existing database in line with the configuration
 * shipped in code, so a changed definition no longer needs its own data
 * migration to reach an installation — the gap behind four separate reports.
 */
class SyncOnboardingConfigTest extends TestCase
{
    use RefreshDatabase;

    private function mccQuestion(): Question
    {
        $group = QuestionGroup::create(['name' => 'Industry', 'slug' => 'ind-'.uniqid(), 'order' => 1, 'is_active' => true]);

        return Question::create([
            'question_group_id' => $group->id, 'label' => 'Industry Classification', 'type' => 'mcc',
            'is_required' => false, 'order' => 1, 'is_active' => true, 'options' => null,
        ]);
    }

    public function test_it_applies_configuration_to_an_empty_database(): void
    {
        $question = $this->mccQuestion();
        // The catalog migration already ran under RefreshDatabase, so clear it
        // to test the command rather than that migration's side effect.
        CountryRegistration::query()->delete();
        $this->assertSame(0, CountryRegistration::count());

        $this->artisan('onboarding:sync-config')
            ->expectsOutputToContain('record(s) updated')
            ->assertSuccessful();

        $this->assertGreaterThan(0, CountryRegistration::count(), 'the country catalog is populated');
        $this->assertGreaterThan(0, count($question->fresh()->options ?? []), 'MCC options are seeded');
    }

    public function test_a_second_run_reports_nothing(): void
    {
        // The property that makes the command trustworthy: an operator can tell
        // from the output whether a deploy actually needed it.
        $this->mccQuestion();
        $this->artisan('onboarding:sync-config')->assertSuccessful();

        $this->artisan('onboarding:sync-config')
            ->expectsOutputToContain('Already in sync')
            ->assertSuccessful();
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->mccQuestion();
        CountryRegistration::query()->delete();

        $this->artisan('onboarding:sync-config', ['--dry-run' => true])
            ->expectsOutputToContain('would change')
            ->assertSuccessful();

        $this->assertSame(0, CountryRegistration::count(), 'the dry run rolled everything back');
    }

    public function test_it_repairs_drift(): void
    {
        $question = $this->mccQuestion();
        $this->artisan('onboarding:sync-config')->assertSuccessful();

        // Drift of the kind a deploy without the matching migration produces.
        CountryRegistration::query()->delete();
        $question->update(['options' => null]);

        $this->artisan('onboarding:sync-config')
            ->expectsOutputToContain('record(s) updated')
            ->assertSuccessful();

        $this->assertGreaterThan(0, CountryRegistration::count());
        $this->assertGreaterThan(0, count($question->fresh()->options ?? []));
    }

    public function test_it_reports_which_configuration_changed(): void
    {
        $this->mccQuestion();

        $this->artisan('onboarding:sync-config')
            ->expectsOutputToContain('Country registration catalog')
            ->expectsOutputToContain('Industry classification')
            ->assertSuccessful();
    }

    public function test_an_id_type_reverted_to_free_text_is_restored(): void
    {
        $group = QuestionGroup::create(['name' => 'Own', 'slug' => 'own-'.uniqid(), 'order' => 1, 'is_active' => true]);
        $table = Question::create([
            'question_group_id' => $group->id, 'label' => 'Directors', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => [['key' => 'id_type', 'label' => 'ID Type', 'type' => 'text']]],
        ]);

        $this->artisan('onboarding:sync-config')->assertSuccessful();

        $column = collect($table->fresh()->options['columns'])->firstWhere('key', 'id_type');
        $this->assertSame('select', $column['type']);
        $this->assertCount(4, $column['options']);
    }
}
