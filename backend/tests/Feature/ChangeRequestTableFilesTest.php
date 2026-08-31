<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Resolving a change request on a table that holds a file column had to fold
 * the upload into the JSON value, where it stringified to {} — the document was
 * lost and the cell left as an empty object (report item 13).
 */
class ChangeRequestTableFilesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserAnswer $answer;

    private AdminNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['onboarding_uploads.disk' => 'local']);

        $group = QuestionGroup::create(['name' => 'Own', 'slug' => 'own', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Beneficial owners', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => [
                ['key' => 'full_legal_name', 'label' => 'Full Legal Name', 'type' => 'text'],
                ['key' => 'passport', 'label' => 'Passport', 'type' => 'file'],
            ]],
        ]);

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Client', 'position' => 'CFO']);
        $onboarding = UserOnboarding::create([
            'user_id' => $this->user->id, 'reference' => 'REF-CR', 'status' => 'submitted', 'started_at' => now(),
        ]);

        $this->answer = UserAnswer::create([
            'user_onboarding_id' => $onboarding->id, 'user_id' => $this->user->id,
            'question_id' => $question->id,
            'value' => json_encode([['full_legal_name' => 'Jane Doe']]),
        ]);

        $admin = Admin::create([
            'name' => 'Reviewer', 'email' => 'reviewer@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::Manager,
        ]);

        $this->notification = AdminNotification::create([
            'user_id' => $this->user->id, 'admin_id' => $admin->id, 'user_answer_id' => $this->answer->id,
            'type' => 'change_request', 'message' => 'Please attach the passport.', 'status' => 'pending',
        ]);
    }

    public function test_a_replacement_document_survives_the_resolve(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/notifications/{$this->notification->id}/resolve", [
            'value' => json_encode([['full_legal_name' => 'Jane Doe']]),
            'table_file_answers' => [[
                'row_index' => 0,
                'column_key' => 'passport',
                'file' => UploadedFile::fake()->create('passport.pdf', 40, 'application/pdf'),
            ]],
        ])->assertOk();

        $rows = json_decode($this->answer->fresh()->value, true);

        $this->assertSame('Jane Doe', $rows[0]['full_legal_name'], 'the text cell is preserved');
        $this->assertIsArray($rows[0]['passport'] ?? null, 'the upload is merged into the cell');
        $this->assertNotEmpty($rows[0]['passport']['path'] ?? $rows[0]['passport']['filename'] ?? null);
    }

    public function test_resolving_without_any_upload_still_works(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/notifications/{$this->notification->id}/resolve", [
            'value' => json_encode([['full_legal_name' => 'Jane Roe']]),
        ])->assertOk();

        $rows = json_decode($this->answer->fresh()->value, true);
        $this->assertSame('Jane Roe', $rows[0]['full_legal_name']);
        $this->assertSame('resolved', $this->notification->fresh()->status);
    }

    public function test_an_oversized_cell_upload_is_refused(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/notifications/{$this->notification->id}/resolve", [
            'value' => json_encode([['full_legal_name' => 'Jane Doe']]),
            'table_file_answers' => [[
                'row_index' => 0,
                'column_key' => 'passport',
                'file' => UploadedFile::fake()->create('huge.pdf', 999999, 'application/pdf'),
            ]],
        ])->assertStatus(422);
    }
}
