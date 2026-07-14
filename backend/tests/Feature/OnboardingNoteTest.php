<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Smalot\PdfParser\Parser as PdfParser;
use Tests\TestCase;

class OnboardingNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Admin $author;
    private Admin $otherAdmin;
    private $onboarding;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        $this->author = Admin::create(['name' => 'Author', 'email' => 'author@test.com', 'password' => 'x', 'is_active' => true]);
        $this->otherAdmin = Admin::create(['name' => 'Other', 'email' => 'other@test.com', 'password' => 'x', 'is_active' => true]);

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);

        $this->onboarding = app(OnboardingService::class)->initializeForUser($this->user);
    }

    public function test_admin_can_add_a_note_and_see_it_on_the_review_page(): void
    {
        $this->actingAs($this->author, 'admin')
            ->post(route('admin.user-onboardings.notes.store', $this->onboarding), [
                'note' => 'Called the client; registrar extract arriving Friday.',
            ])
            ->assertRedirect(route('admin.user-onboardings.show', $this->onboarding));

        $this->actingAs($this->otherAdmin, 'admin')
            ->get(route('admin.user-onboardings.show', $this->onboarding))
            ->assertOk()
            ->assertSee('Internal Notes')
            ->assertSee('Called the client; registrar extract arriving Friday.')
            ->assertSee('Author');
    }

    public function test_note_content_is_required(): void
    {
        $this->actingAs($this->author, 'admin')
            ->post(route('admin.user-onboardings.notes.store', $this->onboarding), ['note' => ''])
            ->assertSessionHasErrors('note');
    }

    public function test_only_the_author_can_delete_a_note(): void
    {
        $note = $this->onboarding->notes()->create([
            'admin_id' => $this->author->id,
            'note' => 'Draft observation.',
        ]);

        $this->actingAs($this->otherAdmin, 'admin')
            ->delete(route('admin.user-onboardings.notes.destroy', [$this->onboarding, $note]))
            ->assertStatus(403);
        $this->assertDatabaseHas('onboarding_notes', ['id' => $note->id]);

        $this->actingAs($this->author, 'admin')
            ->delete(route('admin.user-onboardings.notes.destroy', [$this->onboarding, $note]))
            ->assertRedirect();
        $this->assertDatabaseMissing('onboarding_notes', ['id' => $note->id]);
    }

    public function test_notes_never_reach_the_client_facing_pdf(): void
    {
        $service = app(OnboardingService::class);
        foreach ($this->onboarding->steps as $step) {
            $service->completeStep($this->onboarding->fresh(), $step);
        }

        $this->onboarding->notes()->create([
            'admin_id' => $this->author->id,
            'note' => 'CONFIDENTIAL-INTERNAL-MARKER do not disclose.',
        ]);

        Sanctum::actingAs($this->user);
        $response = $this->get('/api/onboarding/download-pdf')->assertOk();

        $text = (new PdfParser())->parseContent($response->getContent())->getText();
        $this->assertStringNotContainsString('CONFIDENTIAL-INTERNAL-MARKER', $text);
    }
}
