<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Support\CountryListQuestions;
use Database\Seeders\OnboardingDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "List all inbound countries" and friends are pickers, not free text, so an
 * invented country name or the same country twice are both impossible
 * (bug report EOP-20, EOP-21).
 */
class CountryListQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private function textQuestion(string $label): Question
    {
        $group = QuestionGroup::create(['name' => 'Financial', 'slug' => 'financial-'.uniqid(), 'order' => 1, 'is_active' => true]);

        return Question::create([
            'question_group_id' => $group->id, 'label' => $label,
            'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true,
        ]);
    }

    public function test_free_text_country_lists_become_country_pickers(): void
    {
        $inbound = $this->textQuestion('List all inbound countries:');
        $outbound = $this->textQuestion('List all outbound destination countries:');

        $this->assertSame(2, CountryListQuestions::apply());

        foreach ([$inbound, $outbound] as $question) {
            $fresh = $question->fresh();
            $this->assertSame('multi_select', $fresh->type);
            $this->assertGreaterThan(100, count($fresh->options));
            $this->assertContains(['label' => 'India', 'value' => 'IN'], $fresh->options);
        }
    }

    public function test_an_unrelated_text_question_is_untouched(): void
    {
        $other = $this->textQuestion('Describe your business model');

        $this->assertSame(0, CountryListQuestions::apply());
        $this->assertSame('text', $other->fresh()->type);
    }

    public function test_applying_twice_converts_nothing_further(): void
    {
        $this->textQuestion('List all inbound countries:');

        CountryListQuestions::apply();
        $this->assertSame(0, CountryListQuestions::apply());
    }

    public function test_the_seeded_template_ships_country_pickers(): void
    {
        $this->seed(OnboardingDataSeeder::class);

        $inbound = Question::where('label', 'like', '%inbound countries%')->first();

        $this->assertNotNull($inbound);
        $this->assertSame('multi_select', $inbound->type, 'a fresh install must not ship free-text country lists');
    }
}
