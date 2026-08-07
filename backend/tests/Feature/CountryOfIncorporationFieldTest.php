<?php

namespace Tests\Feature;

use App\Models\Question;
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
}
