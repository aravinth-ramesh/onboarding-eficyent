<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AnswerFile;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploaded documents open inline (preview) by default, with a separate
 * ?download=1 attachment option, and only admins who can see the onboarding
 * may fetch its files (bug report EOP-81).
 */
class DocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeFile(): array
    {
        Storage::fake('local');
        Storage::disk('local')->put('u/c.pdf', '%PDF-1.4 fake');

        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $onb = UserOnboarding::create(['user_id' => $user->id, 'user_type_id' => $type->id, 'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $q = Question::create(['question_group_id' => $group->id, 'label' => 'Certificate', 'type' => 'file', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $answer = UserAnswer::create(['user_id' => $user->id, 'question_id' => $q->id, 'user_onboarding_id' => $onb->id, 'value' => 'cert.pdf']);
        $file = AnswerFile::create(['user_answer_id' => $answer->id, 'original_filename' => 'cert.pdf', 's3_path' => 'u/c.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100, 'disk' => 'local', 'validation_status' => 'passed']);

        return [$onb, $file];
    }

    public function test_document_is_served_inline_for_preview_by_default(): void
    {
        [, $file] = $this->makeFile();
        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.documents.show', $file));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_download_query_forces_an_attachment(): void
    {
        [, $file] = $this->makeFile();
        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.documents.show', [$file, 'download' => 1]));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_analyst_cannot_view_documents_of_an_unassigned_onboarding(): void
    {
        [, $file] = $this->makeFile();
        $analyst = Admin::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Analyst]);

        $this->actingAs($analyst, 'admin')
            ->get(route('admin.documents.show', $file))
            ->assertForbidden();
    }
}
