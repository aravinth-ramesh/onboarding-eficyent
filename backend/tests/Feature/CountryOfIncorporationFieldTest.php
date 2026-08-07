<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Support\CountryOfIncorporationField;
use Database\Seeders\OnboardingDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Country of Incorporation is a single-country dropdown, not free text, so a
 * client can't enter several countries for a company that has exactly one
 * (bug report EOP-46).
 */
class CountryOfIncorporationFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_of_incorporation_is_a_single_country_select(): void
    {
        $this->seed(OnboardingDataSeeder::class);

        $question = Question::where('label', 'Country of Incorporation')->first();

        $this->assertNotNull($question);
        $this->assertSame('select', $question->type, 'must be a single-select, not free text');

        $labels = collect($question->options ?? [])->pluck('label');
        $this->assertGreaterThan(100, $labels->count(), 'should list the full country catalog');
        $this->assertTrue($labels->contains('Afghanistan'));
        $this->assertTrue($labels->contains('United Kingdom'));
    }

    public function test_an_existing_free_text_field_is_converted_and_the_conversion_is_idempotent(): void
    {
        // What the data migration faces on a deployed database.
        $group = QuestionGroup::create(['name' => 'Company', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Country of Incorporation',
            'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true,
        ]);

        $this->assertSame(1, CountryOfIncorporationField::apply());

        $question->refresh();
        $this->assertSame('select', $question->type);
        $optionCount = count($question->options);
        $this->assertGreaterThan(100, $optionCount);

        // Re-running (a second deploy, or migrate re-run) changes nothing more.
        $this->assertSame(0, CountryOfIncorporationField::apply());
        $this->assertCount($optionCount, $question->fresh()->options);
    }

    public function test_a_field_an_admin_already_configured_as_a_select_is_left_alone(): void
    {
        $group = QuestionGroup::create(['name' => 'Company', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
        $custom = [['label' => 'Ireland', 'value' => 'IE']];
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Country of Incorporation',
            'type' => 'select', 'options' => $custom,
            'is_required' => true, 'order' => 1, 'is_active' => true,
        ]);

        $this->assertSame(0, CountryOfIncorporationField::apply());
        $this->assertSame($custom, $question->fresh()->options);
    }
}
