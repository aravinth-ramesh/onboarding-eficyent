<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AnswerAuditLog;
use App\Models\AnswerFile;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Support\AnswerValueFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Client Changes named the previous and replacement documents in plain text,
 * so a reviewer could see that a file had changed but could open neither one
 * to compare them (retest items 40/41).
 */
class AuditLogDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Question $question;

    private UserOnboarding $onboarding;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['onboarding_uploads.disk' => 'local']);

        $group = QuestionGroup::create(['name' => 'Docs', 'slug' => 'docs', 'order' => 1, 'is_active' => true]);
        $this->question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Certificate', 'type' => 'file',
            'is_required' => false, 'order' => 1, 'is_active' => true,
        ]);

        $user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $user->id, 'reference' => 'REF-1', 'status' => 'submitted', 'started_at' => now(),
        ]);
    }

    private function log(string $oldPath, string $newPath): AnswerAuditLog
    {
        $answer = UserAnswer::create([
            'user_onboarding_id' => $this->onboarding->id,
            'user_id' => $this->onboarding->user_id,
            'question_id' => $this->question->id,
            'value' => json_encode([$newPath]),
        ]);

        return AnswerAuditLog::create([
            'user_answer_id' => $answer->id,
            'question_id' => $this->question->id,
            'user_id' => $this->onboarding->user_id,
            'edited_by' => $this->onboarding->user_id,
            'old_value' => json_encode([[
                'original_filename' => 'old-certificate.pdf', 's3_path' => $oldPath,
                'mime_type' => 'application/pdf', 'file_size' => 10,
            ]]),
            'new_value' => json_encode([$newPath]),
            'edited_at' => now(),
        ]);
    }

    private function compliance(): Admin
    {
        return Admin::create([
            'name' => 'Compliance', 'email' => 'compliance@test.com',
            'password' => 'x', 'is_active' => true, 'role' => AdminRole::Compliance,
        ]);
    }

    public function test_both_sides_of_a_replaced_document_can_be_opened(): void
    {
        Storage::disk('local')->put('uploads/old.pdf', 'OLD BYTES');
        Storage::disk('local')->put('uploads/new.pdf', 'NEW BYTES');
        $log = $this->log('uploads/old.pdf', 'uploads/new.pdf');

        $admin = $this->compliance();

        $old = $this->actingAs($admin, 'admin')->get(route('admin.audit-logs.document', [$log, 'old', 0]));
        $old->assertOk();
        $this->assertSame('OLD BYTES', $old->streamedContent());

        $new = $this->actingAs($admin, 'admin')->get(route('admin.audit-logs.document', [$log, 'new', 0]));
        $new->assertOk();
        $this->assertSame('NEW BYTES', $new->streamedContent());
    }

    public function test_the_replaced_document_serves_under_its_original_filename(): void
    {
        Storage::disk('local')->put('uploads/old.pdf', 'OLD BYTES');
        $log = $this->log('uploads/old.pdf', 'uploads/new.pdf');

        $this->actingAs($this->compliance(), 'admin')
            ->get(route('admin.audit-logs.document', [$log, 'old', 0]))
            ->assertHeader('content-disposition', 'inline; filename=old-certificate.pdf');
    }

    public function test_it_serves_a_still_current_upload_from_its_own_disk(): void
    {
        Storage::disk('local')->put('uploads/new.pdf', 'NEW BYTES');
        $log = $this->log('uploads/old.pdf', 'uploads/new.pdf');

        AnswerFile::create([
            'user_answer_id' => $log->user_answer_id, 'original_filename' => 'new-certificate.pdf',
            's3_path' => 'uploads/new.pdf', 'mime_type' => 'application/pdf', 'file_size' => 9, 'disk' => 'local',
        ]);

        $this->actingAs($this->compliance(), 'admin')
            ->get(route('admin.audit-logs.document', [$log, 'new', 0]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_an_analyst_cannot_open_a_document_on_an_application_they_are_not_assigned(): void
    {
        Storage::disk('local')->put('uploads/old.pdf', 'OLD BYTES');
        $log = $this->log('uploads/old.pdf', 'uploads/new.pdf');

        $analyst = Admin::create([
            'name' => 'Analyst', 'email' => 'analyst@test.com',
            'password' => 'x', 'is_active' => true, 'role' => AdminRole::Analyst,
        ]);

        $this->actingAs($analyst, 'admin')
            ->get(route('admin.audit-logs.document', [$log, 'old', 0]))
            ->assertForbidden();
    }

    public function test_an_index_outside_the_row_is_rejected(): void
    {
        Storage::disk('local')->put('uploads/old.pdf', 'OLD BYTES');
        $log = $this->log('uploads/old.pdf', 'uploads/new.pdf');

        $this->actingAs($this->compliance(), 'admin')
            ->get(route('admin.audit-logs.document', [$log, 'old', 7]))
            ->assertNotFound();
    }

    public function test_a_missing_file_is_a_404_rather_than_an_error(): void
    {
        $log = $this->log('uploads/gone.pdf', 'uploads/new.pdf');

        $this->actingAs($this->compliance(), 'admin')
            ->get(route('admin.audit-logs.document', [$log, 'old', 0]))
            ->assertNotFound();
    }

    public function test_the_path_comes_from_the_audit_row_not_the_url(): void
    {
        // The route exposes only an index into the row's own uploads, so there
        // is no parameter an attacker could point at another file. Serve the
        // real document so this fails if a smuggled path were ever honoured,
        // rather than passing because nothing was there to serve.
        Storage::disk('local')->put('uploads/old.pdf', 'OLD BYTES');
        Storage::disk('local')->put('secret.env', 'SECRET');
        $log = $this->log('uploads/old.pdf', 'uploads/new.pdf');

        $response = $this->actingAs($this->compliance(), 'admin')
            ->get(route('admin.audit-logs.document', [$log, 'old', 0]).'?path=secret.env');

        $response->assertOk();
        $this->assertSame('OLD BYTES', $response->streamedContent());
    }

    public function test_the_client_changes_screen_links_to_both_documents(): void
    {
        $log = $this->log('uploads/old.pdf', 'uploads/new.pdf');

        $this->actingAs($this->compliance(), 'admin')
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee(route('admin.audit-logs.document', [$log, 'old', 0]), false)
            ->assertSee(route('admin.audit-logs.document', [$log, 'new', 0]), false)
            ->assertSee('old-certificate.pdf');
    }

    public function test_a_non_file_change_is_still_shown_as_plain_text(): void
    {
        $group = QuestionGroup::create(['name' => 'Co', 'slug' => 'co', 'order' => 2, 'is_active' => true]);
        $text = Question::create([
            'question_group_id' => $group->id, 'label' => 'Company', 'type' => 'text',
            'is_required' => false, 'order' => 1, 'is_active' => true,
        ]);
        $answer = UserAnswer::create([
            'user_onboarding_id' => $this->onboarding->id, 'user_id' => $this->onboarding->user_id,
            'question_id' => $text->id, 'value' => 'Acme Ltd',
        ]);
        AnswerAuditLog::create([
            'user_answer_id' => $answer->id, 'question_id' => $text->id,
            'user_id' => $this->onboarding->user_id, 'edited_by' => $this->onboarding->user_id,
            'old_value' => 'Acme Plc', 'new_value' => 'Acme Ltd', 'edited_at' => now(),
        ]);

        $this->actingAs($this->compliance(), 'admin')
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Acme Plc')
            ->assertSee('Acme Ltd')
            ->assertDontSee('/document/old/', false);
    }

    public function test_file_entries_read_both_stored_shapes(): void
    {
        $old = AnswerValueFormatter::fileEntries(json_encode([
            ['original_filename' => 'certificate.pdf', 's3_path' => 'uploads/a.pdf'],
        ]));
        $this->assertSame([['name' => 'certificate.pdf', 'path' => 'uploads/a.pdf']], $old);

        $new = AnswerValueFormatter::fileEntries(json_encode(['uploads/2026/b.pdf']));
        $this->assertSame([['name' => 'b.pdf', 'path' => 'uploads/2026/b.pdf']], $new);

        $this->assertSame([], AnswerValueFormatter::fileEntries(null));
        $this->assertSame([], AnswerValueFormatter::fileEntries('just text'));
    }
}
