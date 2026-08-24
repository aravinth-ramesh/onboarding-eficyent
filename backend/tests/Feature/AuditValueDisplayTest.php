<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Support\AnswerValueFormatter;
use App\Support\IndustryClassificationOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin surfaces show what an answer actually says — not a row count, and not
 * a raw code (retest items: EOP-73 audit log, 38 industry classification).
 */
class AuditValueDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function question(string $type, ?array $options = null): Question
    {
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g-'.uniqid(), 'order' => 1, 'is_active' => true]);

        return Question::create([
            'question_group_id' => $group->id, 'label' => 'Q', 'type' => $type,
            'is_required' => false, 'order' => 1, 'is_active' => true, 'options' => $options,
        ]);
    }

    public function test_table_answers_show_their_values_not_a_row_count(): void
    {
        // The audit trail read "1 row -> 1 row", so a reviewer could not see
        // what the client had changed (EOP-73).
        $question = $this->question('table', ['columns' => [
            ['key' => 'full_legal_name', 'label' => 'Full Legal Name', 'type' => 'text'],
            ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'select',
                'options' => [['value' => 'IN', 'label' => 'India']]],
        ]]);

        $readable = AnswerValueFormatter::readable(
            json_encode([['full_legal_name' => 'Jane Doe', 'nationality' => 'IN']]),
            $question,
        );

        $this->assertStringContainsString('Full Legal Name: Jane Doe', $readable);
        $this->assertStringContainsString('Nationality: India', $readable, 'a select cell must show its label');
        $this->assertStringNotContainsString('1 row', $readable);
    }

    public function test_multiple_rows_are_numbered(): void
    {
        $question = $this->question('table', ['columns' => [
            ['key' => 'full_legal_name', 'label' => 'Full Legal Name', 'type' => 'text'],
        ]]);

        $readable = AnswerValueFormatter::readable(
            json_encode([['full_legal_name' => 'Jane Doe'], ['full_legal_name' => 'John Roe']]),
            $question,
        );

        $this->assertStringContainsString('#1 Full Legal Name: Jane Doe', $readable);
        $this->assertStringContainsString('#2 Full Legal Name: John Roe', $readable);
    }

    public function test_a_file_cell_shows_its_filename(): void
    {
        $question = $this->question('table', ['columns' => [
            ['key' => 'passport', 'label' => 'Passport', 'type' => 'file'],
        ]]);

        $readable = AnswerValueFormatter::readable(
            json_encode([['passport' => ['filename' => 'passport.pdf', 'path' => 'u/passport.pdf']]]),
            $question,
        );

        $this->assertStringContainsString('Passport: passport.pdf', $readable);
    }

    public function test_an_empty_table_still_reads_sensibly(): void
    {
        $question = $this->question('table', ['columns' => []]);

        $this->assertSame('(no rows)', AnswerValueFormatter::readable('[]', $question));
    }

    public function test_industry_classification_shows_the_name_not_the_code(): void
    {
        // "5942" told a reviewer nothing (retest item 38).
        $question = $this->question('mcc');
        IndustryClassificationOptions::apply();

        $this->assertSame('Book Stores', AnswerValueFormatter::readable('5942', $question->fresh()));
    }

    public function test_an_unknown_industry_code_falls_back_to_the_code(): void
    {
        $question = $this->question('mcc');
        IndustryClassificationOptions::apply();

        $this->assertSame('9999', AnswerValueFormatter::readable('9999', $question->fresh()));
    }
}
