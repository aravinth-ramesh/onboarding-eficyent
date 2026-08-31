<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The verification screen counts down to the moment the server will stop
 * accepting the code, so the deadline has to come from the issued row rather
 * than from a duration measured when the response happens to arrive (EOP-4),
 * and a refused resend has to say how much longer to wait (EOP-3).
 */
class OtpCountdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_the_expiry_returned_matches_the_code_that_was_issued(): void
    {
        $response = $this->postJson('/api/auth/send-otp', ['email' => 'client@test.com'])->assertOk();

        $otp = OtpCode::where('email', 'client@test.com')->latest()->firstOrFail();

        $this->assertSame(
            $otp->expires_at->toIso8601String(),
            $response->json('expires_at'),
            'the deadline must be the issued row, not a duration constant',
        );
        $this->assertEqualsWithDelta(600, $response->json('expires_in_seconds'), 5);
    }

    public function test_a_refused_resend_reports_how_much_longer_to_wait(): void
    {
        $this->postJson('/api/auth/send-otp', ['email' => 'client@test.com'])->assertOk();

        $response = $this->postJson('/api/auth/send-otp', ['email' => 'client@test.com'])
            ->assertStatus(429);

        $wait = $response->json('resend_available_in_seconds');
        $this->assertNotNull($wait, 'the refusal must carry the remaining cooldown');
        $this->assertGreaterThan(0, $wait);
        $this->assertLessThanOrEqual(60, $wait);
    }

    public function test_the_remaining_cooldown_shrinks_as_time_passes(): void
    {
        $this->postJson('/api/auth/send-otp', ['email' => 'client@test.com'])->assertOk();

        $service = app(OtpService::class);
        $before = $service->secondsUntilResendAllowed('client@test.com');

        $this->travel(30)->seconds();

        $this->assertLessThan($before, $service->secondsUntilResendAllowed('client@test.com'));
    }

    public function test_an_address_that_has_never_requested_a_code_waits_no_time(): void
    {
        $this->assertSame(0, app(OtpService::class)->secondsUntilResendAllowed('nobody@test.com'));
    }
}
