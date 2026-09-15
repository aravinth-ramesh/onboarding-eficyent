<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Read-only uploads were linked by the storage URL frozen into the answer at
 * upload time — wrong host on the public disk, unsigned on S3, and absent
 * entirely on older rows, where the template rendered an unclickable span
 * (report item 20).
 */
class ReadOnlyDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private UserOnboarding $onboarding;

    private UserAnswer $answer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['onboarding_uploads.disk' => 'local']);
        Storage::disk('local')->put('uploads/passport.pdf', 'PASSPORT BYTES');

        $group = QuestionGroup::create(['name' => 'UBO', 'slug' => 'ubo', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Owners', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => [['key' => 'passport', 'label' => 'Passport', 'type' => 'file']]],
        ]);

        $user = User::create(['email' => 'client@test.com', 'name' => 'Client', 'position' => 'CFO']);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $user->id, 'reference' => 'REF-RO', 'status' => 'completed',
            'started_at' => now(), 'completed_at' => now(),
        ]);

        $this->answer = UserAnswer::create([
            'user_onboarding_id' => $this->onboarding->id, 'user_id' => $user->id,
            'question_id' => $question->id,
            'value' => json_encode([[
                'passport' => [
                    'filename' => 'passport.pdf', 'path' => 'uploads/passport.pdf',
                    'disk' => 'local', 'mime' => 'application/pdf',
                ],
            ]]),
        ]);
    }

    private function admin(AdminRole $role = AdminRole::SuperAdmin, string $email = 'ops@test.com'): Admin
    {
        return Admin::create([
            'name' => 'Ops', 'email' => $email, 'password' => 'x', 'is_active' => true, 'role' => $role,
        ]);
    }

    private function url(int $row = 0, string $column = 'passport'): string
    {
        return route('admin.documents.table-cell', [$this->onboarding, $this->answer, $row, $column]);
    }

    public function test_a_table_cell_document_opens(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->get($this->url());

        $response->assertOk();
        $this->assertSame('PASSPORT BYTES', $response->streamedContent());
        $response->assertHeader('content-disposition', 'inline; filename=passport.pdf');
    }

    public function test_it_serves_even_when_the_stored_url_is_absent(): void
    {
        // Older rows predate the `url` key entirely — that is the case that
        // rendered as an unclickable span.
        $this->assertStringNotContainsString('"url"', $this->answer->value);
        $this->actingAs($this->admin(), 'admin')->get($this->url())->assertOk();
    }

    public function test_a_missing_row_or_column_is_not_found(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->get($this->url(row: 7))->assertNotFound();
        $this->actingAs($admin, 'admin')->get($this->url(column: 'nope'))->assertNotFound();
    }

    public function test_an_answer_from_another_application_is_not_found(): void
    {
        $other = UserOnboarding::create([
            'user_id' => User::create(['email' => 'o@test.com', 'name' => 'O', 'position' => 'X'])->id,
            'reference' => 'REF-OTHER', 'status' => 'completed', 'started_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.documents.table-cell', [$other, $this->answer, 0, 'passport']))
            ->assertNotFound();
    }

    public function test_an_analyst_who_is_not_assigned_is_refused(): void
    {
        $analyst = $this->admin(AdminRole::Analyst, 'analyst@test.com');

        $this->actingAs($analyst, 'admin')->get($this->url())->assertForbidden();
    }
}
