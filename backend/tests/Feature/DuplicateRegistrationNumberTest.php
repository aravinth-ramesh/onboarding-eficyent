<?php

namespace Tests\Feature;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A registration identifier belongs to exactly one legal entity, so reusing a
 * Company Registration Number / VAT / tax id on another application is
 * rejected with a field-level error (bug report EOP-47).
 */
class DuplicateRegistrationNumberTest extends TestCase
{
    use RefreshDatabase;

    private UserType $type;

    protected function setUp(): void
    {
        parent::setUp();
        OnboardingStep::firstOrCreate(
            ['slug' => 'registration'],
            ['name' => 'Registration', 'component_key' => 'registration', 'order' => 1, 'is_active' => true],
        );
        $this->type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
    }

    private function client(string $email): User
    {
        $user = User::create(['email' => $email, 'name' => 'Co', 'position' => 'CFO']);
        $onboarding = app(OnboardingService::class)->initializeForUser($user);
        $onboarding->update(['user_type_id' => $this->type->id]);

        return $user;
    }

    /** Seed an application that already owns the identifier. */
    private function existingApplication(string $countryCode, array $details): UserOnboarding
    {
        $user = User::create(['email' => 'incumbent@t.com', 'name' => 'Incumbent', 'position' => 'CFO']);

        return UserOnboarding::create([
            'user_id' => $user->id, 'user_type_id' => $this->type->id,
            'status' => 'completed', 'started_at' => now(), 'completed_at' => now(),
            'country_code' => $countryCode,
            'registration_details' => $details,
        ]);
    }

    public function test_a_registration_number_already_used_elsewhere_is_rejected(): void
    {
        $this->existingApplication('GB', [
            'crn' => ['label' => 'Company Registration Number (CRN)', 'value' => 'AB123456'],
        ]);

        Sanctum::actingAs($this->client('newcomer@t.com'));

        $response = $this->postJson('/api/onboarding/registration', [
            'country_code' => 'GB',
            'values' => ['crn' => 'AB123456'],
        ])->assertStatus(422);

        // The error key is the literal string "values.crn", not a nested path.
        $this->assertSame(
            'This Company Registration Number (CRN) is already used by another onboarding application. If this is your company, please contact support.',
            $response->json('errors')['values.crn'][0],
        );
    }

    public function test_the_check_is_scoped_to_the_country_that_issued_the_number(): void
    {
        // The same string under a different jurisdiction is not the same entity.
        $this->existingApplication('AE', [
            'trade_license' => ['label' => 'Trade License Number', 'value' => 'AB123456'],
        ]);

        Sanctum::actingAs($this->client('newcomer@t.com'));

        $this->postJson('/api/onboarding/registration', [
            'country_code' => 'GB',
            'values' => ['crn' => 'AB123456'],
        ])->assertOk();
    }

    public function test_a_client_can_re_save_their_own_registration_details(): void
    {
        $user = $this->client('owner@t.com');
        Sanctum::actingAs($user);

        $payload = ['country_code' => 'GB', 'values' => ['crn' => 'AB123456']];

        $this->postJson('/api/onboarding/registration', $payload)->assertOk();
        // Saving the identical details again must not collide with itself.
        $this->postJson('/api/onboarding/registration', $payload)->assertOk();

        $this->assertSame(
            'AB123456',
            $user->fresh()->activeOnboarding()->registration_details['crn']['value'],
        );
    }

    public function test_a_different_number_in_the_same_country_is_accepted(): void
    {
        $this->existingApplication('GB', [
            'crn' => ['label' => 'Company Registration Number (CRN)', 'value' => 'AB123456'],
        ]);

        Sanctum::actingAs($this->client('newcomer@t.com'));

        $this->postJson('/api/onboarding/registration', [
            'country_code' => 'GB',
            'values' => ['crn' => 'ZZ999999'],
        ])->assertOk();
    }

    public function test_a_lower_case_duplicate_is_still_caught(): void
    {
        // Identifiers are normalised to upper case before comparison (EOP-45),
        // so case can't be used to slip a duplicate through.
        $this->existingApplication('GB', [
            'crn' => ['label' => 'Company Registration Number (CRN)', 'value' => 'AB123456'],
        ]);

        Sanctum::actingAs($this->client('newcomer@t.com'));

        $response = $this->postJson('/api/onboarding/registration', [
            'country_code' => 'GB',
            'values' => ['crn' => 'ab123456'],
        ])->assertStatus(422);

        $this->assertArrayHasKey('values.crn', $response->json('errors'));
    }
}
