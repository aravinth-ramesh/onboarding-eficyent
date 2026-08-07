<?php

namespace Tests\Feature;

use App\Models\AnswerFile;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A wrongly uploaded document can be removed and replaced — there was no
 * delete path at all, so a mistake was permanent (bug report EOP-22).
 */
class DeleteAnswerFileTest extends TestCase
{
    use RefreshDatabase;

    private UserOnboarding $onboarding;
    private UserAnswer $answer;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $this->client = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $this->client->id, 'user_type_id' => $type->id,
            'status' => 'in_progress', 'started_at' => now(),
        ]);
        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $question = Question::create(['question_group_id' => $group->id, 'label' => 'Certificate', 'type' => 'file', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $this->answer = UserAnswer::create([
            'user_id' => $this->client->id, 'question_id' => $question->id,
            'user_onboarding_id' => $this->onboarding->id, 'value' => json_encode(['u/a.pdf', 'u/b.pdf']),
        ]);
    }

    private function file(string $path): AnswerFile
    {
        Storage::disk('local')->put($path, '%PDF fake');

        return AnswerFile::create([
            'user_answer_id' => $this->answer->id, 'original_filename' => basename($path),
            's3_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => 10, 'disk' => 'local',
        ]);
    }

    public function test_a_client_can_remove_their_own_document(): void
    {
        $a = $this->file('u/a.pdf');
        $this->file('u/b.pdf');

        Sanctum::actingAs($this->client);
        $this->deleteJson("/api/onboarding/answers/files/{$a->id}")->assertOk();

        $this->assertDatabaseMissing('answer_files', ['id' => $a->id]);
        Storage::disk('local')->assertMissing('u/a.pdf');
        // The other document is untouched, and the answer's path list follows.
        Storage::disk('local')->assertExists('u/b.pdf');
        $this->assertSame(['u/b.pdf'], json_decode($this->answer->fresh()->value, true));
    }

    public function test_removing_the_last_document_empties_the_answer(): void
    {
        $only = $this->file('u/a.pdf');

        Sanctum::actingAs($this->client);
        $this->deleteJson("/api/onboarding/answers/files/{$only->id}")->assertOk();

        $this->assertSame('', $this->answer->fresh()->value, 'a required question must read as unanswered again');
    }

    public function test_another_client_cannot_remove_it(): void
    {
        $a = $this->file('u/a.pdf');
        $stranger = User::create(['email' => 'x@t.com', 'name' => 'X', 'position' => 'CFO']);

        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/onboarding/answers/files/{$a->id}")->assertNotFound();

        $this->assertDatabaseHas('answer_files', ['id' => $a->id]);
    }

    public function test_documents_cannot_be_removed_after_submission(): void
    {
        $a = $this->file('u/a.pdf');
        $this->onboarding->update(['status' => 'completed', 'completed_at' => now()]);

        Sanctum::actingAs($this->client);
        $this->deleteJson("/api/onboarding/answers/files/{$a->id}")->assertForbidden();

        $this->assertDatabaseHas('answer_files', ['id' => $a->id]);
    }
}
