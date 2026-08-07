<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use App\Services\AnswerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Team members share an application's answers, so two people editing the same
 * question used to mean last-write-wins with no warning. A save now carries
 * the version it loaded and is refused if someone else got there first
 * (bug report EOP-97).
 */
class ConcurrentAnswerEditTest extends TestCase
{
    use RefreshDatabase;

    private UserOnboarding $onboarding;
    private Question $question;
    private User $owner;
    private User $collaborator;

    protected function setUp(): void
    {
        parent::setUp();

        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $this->owner = User::create(['email' => 'owner@t.com', 'name' => 'Owner', 'position' => 'CFO']);
        $this->collaborator = User::create(['email' => 'mate@t.com', 'name' => 'Mate', 'position' => 'COO']);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $this->owner->id, 'user_type_id' => $type->id,
            'status' => 'in_progress', 'started_at' => now(),
        ]);
        $group = QuestionGroup::create(['name' => 'Company', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
        $this->question = Question::create(['question_group_id' => $group->id, 'label' => 'Legal name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
    }

    private function seedAnswer(string $value = 'Original'): UserAnswer
    {
        return UserAnswer::create([
            'user_id' => $this->owner->id, 'question_id' => $this->question->id,
            'user_onboarding_id' => $this->onboarding->id, 'value' => $value,
        ]);
    }

    public function test_a_save_with_a_stale_version_is_refused(): void
    {
        $answer = $this->seedAnswer();
        $staleVersion = AnswerService::versionOf($answer);

        // The collaborator saves first, moving the version on.
        $answer->update(['value' => 'Collaborator wins']);

        $this->expectException(\App\Exceptions\StaleAnswerException::class);
        app(AnswerService::class)->saveAnswer(
            $this->owner, $this->onboarding, $this->question->id, 'Owner overwrite', null, $staleVersion,
        );
    }

    public function test_the_collaborators_value_survives_the_refused_save(): void
    {
        $answer = $this->seedAnswer();
        $staleVersion = AnswerService::versionOf($answer);
        $answer->update(['value' => 'Collaborator wins']);

        try {
            app(AnswerService::class)->saveAnswer(
                $this->owner, $this->onboarding, $this->question->id, 'Owner overwrite', null, $staleVersion,
            );
        } catch (\App\Exceptions\StaleAnswerException) {
            // expected
        }

        $this->assertSame('Collaborator wins', $answer->fresh()->value, 'the earlier save must not be clobbered');
    }

    public function test_a_save_with_the_current_version_succeeds(): void
    {
        $answer = $this->seedAnswer();

        app(AnswerService::class)->saveAnswer(
            $this->owner, $this->onboarding, $this->question->id, 'Updated', null, AnswerService::versionOf($answer),
        );

        $this->assertSame('Updated', $answer->fresh()->value);
    }

    public function test_a_save_without_a_version_still_works(): void
    {
        // Older clients send no version — they must not start failing.
        $answer = $this->seedAnswer();

        app(AnswerService::class)->saveAnswer(
            $this->owner, $this->onboarding, $this->question->id, 'No version supplied',
        );

        $this->assertSame('No version supplied', $answer->fresh()->value);
    }

    public function test_the_api_reports_a_conflict_rather_than_overwriting(): void
    {
        $answer = $this->seedAnswer();
        $staleVersion = AnswerService::versionOf($answer);
        $answer->update(['value' => 'Collaborator wins']);

        \Laravel\Sanctum\Sanctum::actingAs($this->owner);
        $response = $this->postJson('/api/onboarding/answers', [
            'answers' => [[
                'question_id' => $this->question->id,
                'value' => 'Owner overwrite',
                'version' => $staleVersion,
            ]],
        ])->assertStatus(409);

        $this->assertSame('answer_conflict', $response->json('code'));
        $this->assertSame($this->question->id, $response->json('question_id'));
        $this->assertSame('Collaborator wins', $answer->fresh()->value);
    }

    public function test_a_conflict_rolls_back_the_whole_group(): void
    {
        // Otherwise half a page would save and half would not.
        $group = QuestionGroup::create(['name' => 'More', 'slug' => 'more', 'order' => 2, 'is_active' => true]);
        $other = Question::create(['question_group_id' => $group->id, 'label' => 'Trading name', 'type' => 'text', 'is_required' => false, 'order' => 1, 'is_active' => true]);

        $answer = $this->seedAnswer();
        $staleVersion = AnswerService::versionOf($answer);
        $answer->update(['value' => 'Collaborator wins']);

        \Laravel\Sanctum\Sanctum::actingAs($this->owner);
        $this->postJson('/api/onboarding/answers', [
            'answers' => [
                ['question_id' => $other->id, 'value' => 'Should not persist'],
                ['question_id' => $this->question->id, 'value' => 'Owner overwrite', 'version' => $staleVersion],
            ],
        ])->assertStatus(409);

        $this->assertDatabaseMissing('user_answers', ['question_id' => $other->id, 'value' => 'Should not persist']);
    }
}
