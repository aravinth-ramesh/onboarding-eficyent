<?php

namespace Tests\Feature;

use App\Mail\TeamInviteMail;
use App\Models\OnboardingCollaborator;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The "Join the Application" link carries an invitation token and is bound to
 * the invited address, so following it from someone else's browser session
 * cannot join — or expose — that person's application (bug report EOP-53).
 */
class TeamInvitationTokenTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private $onboarding;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);

        $this->owner = User::create(['email' => 'owner@test.com', 'name' => 'Owner', 'position' => 'CFO']);
        $this->onboarding = app(OnboardingService::class)->initializeForUser($this->owner);
    }

    private function invite(string $email = 'colleague@test.com'): OnboardingCollaborator
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/onboarding/team/invite', ['email' => $email])->assertStatus(201);

        return OnboardingCollaborator::where('user_onboarding_id', $this->onboarding->id)->firstOrFail();
    }

    public function test_an_invitation_issues_a_token_carried_in_the_emailed_link(): void
    {
        $collaborator = $this->invite();

        $this->assertNotEmpty($collaborator->invite_token);
        $this->assertNull($collaborator->accepted_at);

        Mail::assertQueued(
            TeamInviteMail::class,
            fn ($mail) => $mail->inviteToken === $collaborator->invite_token
        );
    }

    public function test_the_public_lookup_names_the_invited_address_without_a_session(): void
    {
        $collaborator = $this->invite();

        // No authentication: the portal must be able to say who it is for.
        $this->getJson("/api/team/invitation/{$collaborator->invite_token}")
            ->assertOk()
            ->assertJsonPath('data.email', 'colleague@test.com')
            ->assertJsonPath('data.inviter', 'Owner')
            ->assertJsonPath('data.accepted', false);
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $this->getJson('/api/team/invitation/not-a-real-token')->assertNotFound();
    }

    public function test_the_invitee_accepts_their_own_invitation(): void
    {
        $collaborator = $this->invite();
        $colleague = User::where('email', 'colleague@test.com')->firstOrFail();

        Sanctum::actingAs($colleague);
        $this->postJson("/api/onboarding/team/invitation/{$collaborator->invite_token}/accept")
            ->assertOk()
            ->assertJsonPath('data.accepted', true);

        $this->assertNotNull($collaborator->fresh()->accepted_at);
    }

    public function test_following_the_link_in_the_owners_session_cannot_accept_it(): void
    {
        // The core EOP-53 regression: the owner's browser holds the session and
        // the invitee clicks the link on that machine. It must be refused and
        // name the address it belongs to, never silently join/expose the app.
        $collaborator = $this->invite();

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/onboarding/team/invitation/{$collaborator->invite_token}/accept")
            ->assertForbidden()
            ->assertJsonPath('message', 'This invitation was sent to colleague@test.com. Sign in with that address to join.');

        $this->assertNull($collaborator->fresh()->accepted_at);
    }

    public function test_an_unrelated_signed_in_user_cannot_accept_the_invitation(): void
    {
        $collaborator = $this->invite();
        $stranger = User::create(['email' => 'stranger@test.com', 'name' => 'Stranger', 'position' => 'CTO']);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/onboarding/team/invitation/{$collaborator->invite_token}/accept")
            ->assertForbidden();

        $this->assertNull($collaborator->fresh()->accepted_at);
    }
}
