<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Support\FieldValidationRules;
use Database\Seeders\OnboardingDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The standard template ships with field-validation rules the client engine
 * enforces, and they survive a re-seed (bug report EOP-32, EOP-37, EOP-39,
 * EOP-42).
 */
class FieldValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    /** @return Collection<int, array<string, mixed>> */
    private function columns(string $key): Collection
    {
        return Question::where('type', 'table')->get()
            ->flatMap(fn (Question $q) => $q->options['columns'] ?? [])
            ->filter(fn ($column) => ($column['key'] ?? null) === $key)
            ->values();
    }

    public function test_the_seeder_applies_rules_so_they_survive_a_reseed(): void
    {
        $this->seed(OnboardingDataSeeder::class);

        $dobColumns = $this->columns('date_of_birth');
        $this->assertNotEmpty($dobColumns, 'the template should have date-of-birth columns');

        foreach ($dobColumns as $column) {
            // A text column can never reach the date validator, so a future
            // date of birth was accepted (EOP-32).
            $this->assertSame('date', $column['type']);
            $this->assertFalse($column['validation']['allow_future']);
        }

        foreach ($this->columns('position') as $column) {
            $this->assertTrue($column['validation']['requires_letter'] ?? false, 'Position must reject digits-only (EOP-37)');
        }

        foreach ($this->columns('id_number') as $column) {
            $this->assertSame(4, $column['validation']['min_length'] ?? null, 'a one-character ID must be rejected (EOP-39)');
            $this->assertSame(30, $column['validation']['max_length'] ?? null);
        }
    }

    public function test_a_legacy_meta_blob_does_not_block_rule_seeding(): void
    {
        // Imported questions carry a {"fields": [...]} layout blob in
        // validation_rules; it holds no real rules, but it made the seeder skip
        // the field — which is why the MLRO contact stayed unvalidated (EOP-42).
        $group = \App\Models\QuestionGroup::create(['name' => 'AML', 'slug' => 'aml', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id,
            'label' => 'AML Officer Contact Email & Number',
            'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true,
            'validation_rules' => ['fields' => [['name' => 'response', 'type' => 'text']]],
        ]);

        FieldValidationRules::apply();

        $this->assertSame(['format' => 'contact'], $question->fresh()->validation_rules);
    }

    public function test_configured_rules_are_never_overwritten(): void
    {
        $group = \App\Models\QuestionGroup::create(['name' => 'G', 'slug' => 'g', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Company Website',
            'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true,
            'validation_rules' => ['format' => 'email'],
        ]);

        FieldValidationRules::apply();

        $this->assertSame(['format' => 'email'], $question->fresh()->validation_rules);
    }
}
