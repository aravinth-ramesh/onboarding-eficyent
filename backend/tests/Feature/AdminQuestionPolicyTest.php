<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Question;
use App\Models\QuestionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin question form's document-policy picker round-trip: the form posts
 * validation_rules as a JSON string; the controller sanitizes the policy
 * against config/document_validation.php.
 */
class AdminQuestionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private QuestionGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
        $this->group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'question_group_id' => $this->group->id,
            'label' => 'Upload your certificate',
            'type' => 'file',
            'is_required' => '1',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_admin_can_set_a_document_policy_on_a_file_question(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.questions.store'), $this->payload([
                'validation_rules' => json_encode([
                    'expected_document' => 'certificate_of_incorporation',
                    'max_age_months' => 6,
                ]),
            ]))
            ->assertRedirect(route('admin.questions.index'));

        $question = Question::latest('id')->firstOrFail();
        $this->assertSame('certificate_of_incorporation', $question->validation_rules['expected_document']);
        $this->assertSame(6, $question->validation_rules['max_age_months']);
    }

    public function test_multiple_expected_documents_are_stored_as_alternatives(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.questions.store'), $this->payload([
                'validation_rules' => json_encode([
                    'expected_document' => ['proof_of_address', 'bank_statement'],
                    'max_age_months' => 3,
                ]),
            ]))
            ->assertRedirect();

        $question = Question::latest('id')->firstOrFail();
        $this->assertSame(['proof_of_address', 'bank_statement'], $question->validation_rules['expected_document']);
    }

    public function test_unknown_document_types_are_dropped(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.questions.store'), $this->payload([
                'validation_rules' => json_encode([
                    'expected_document' => 'passport_of_atlantis',
                    'max_age_months' => 3,
                ]),
            ]))
            ->assertRedirect();

        $question = Question::latest('id')->firstOrFail();
        $this->assertNull($question->validation_rules);
    }

    public function test_admin_can_create_questions_with_kyb_types(): void
    {
        foreach (['phone', 'mcc', 'address', 'ubo'] as $type) {
            $this->actingAs($this->admin, 'admin')
                ->post(route('admin.questions.store'), $this->payload([
                    'label' => "KYB {$type} question",
                    'type' => $type,
                ]))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.questions.index'));
        }

        $this->assertSame(4, Question::whereIn('type', ['phone', 'mcc', 'address', 'ubo'])->count());
    }

    public function test_updating_a_question_clamps_max_age(): void
    {
        $question = Question::create([
            'question_group_id' => $this->group->id,
            'label' => 'Upload proof',
            'type' => 'file',
            'is_required' => true,
            'order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.questions.update', $question), $this->payload([
                'label' => 'Upload proof',
                'validation_rules' => json_encode([
                    'expected_document' => 'proof_of_address',
                    'max_age_months' => 9999,
                ]),
            ]))
            ->assertRedirect();

        $this->assertSame(120, $question->fresh()->validation_rules['max_age_months']);
    }
}
