<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Support\AnswerValueFormatter;
use PHPUnit\Framework\TestCase;

/**
 * The audit trail must read in plain English — no raw JSON — for files,
 * multi-selects, tables and option-based answers.
 */
class AnswerValueFormatterTest extends TestCase
{
    private function question(string $type, array $options = []): Question
    {
        $q = new Question(['type' => $type]);
        $q->options = $options; // cast handles array
        $q->type = $type;

        return $q;
    }

    public function test_plain_text_is_unchanged(): void
    {
        $this->assertSame('Acme Ltd', AnswerValueFormatter::readable('Acme Ltd', $this->question('text')));
    }

    public function test_empty_becomes_a_dash(): void
    {
        $this->assertSame('—', AnswerValueFormatter::readable('', $this->question('text')));
        $this->assertSame('—', AnswerValueFormatter::readable(null, $this->question('text')));
    }

    public function test_old_file_json_shows_original_filenames(): void
    {
        $raw = json_encode([
            ['original_filename' => 'certificate.pdf', 's3_path' => 'u/x.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1000],
            ['original_filename' => 'passport.jpg', 's3_path' => 'u/y.jpg'],
        ]);

        $this->assertSame('2 files: certificate.pdf, passport.jpg', AnswerValueFormatter::readable($raw, $this->question('file')));
    }

    public function test_new_file_json_of_paths_shows_basenames(): void
    {
        $raw = json_encode(['uploads/2026/abc123.pdf']);

        $this->assertSame('1 file: abc123.pdf', AnswerValueFormatter::readable($raw, $this->question('file')));
    }

    public function test_multi_select_maps_values_to_labels(): void
    {
        $q = $this->question('multi_select', [
            ['value' => 'aml', 'label' => 'AML Policy'],
            ['value' => 'kyc', 'label' => 'KYC Policy'],
        ]);

        $this->assertSame('AML Policy, KYC Policy', AnswerValueFormatter::readable(json_encode(['aml', 'kyc']), $q));
    }

    public function test_single_choice_maps_value_to_label(): void
    {
        $q = $this->question('select', [['value' => 'ltd', 'label' => 'Private Limited']]);

        $this->assertSame('Private Limited', AnswerValueFormatter::readable('ltd', $q));
    }

    public function test_table_is_summarised_by_row_count(): void
    {
        $raw = json_encode([['name' => 'Jane'], ['name' => 'John'], ['name' => 'Sam']]);

        $this->assertSame('3 rows', AnswerValueFormatter::readable($raw, $this->question('table')));
    }
}
