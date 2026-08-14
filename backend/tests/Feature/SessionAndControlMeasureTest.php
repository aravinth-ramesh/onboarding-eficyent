<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Support\ControlMeasureFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Sessions end after a spell of inactivity (item 38), and the AML/CFT control
 * measure fields have room for a real answer (item 30).
 */
class SessionAndControlMeasureTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        $user = User::create(['email' => 'client@test.com', 'name' => 'Client', 'position' => 'CFO']);
        UserOnboarding::create([
            'user_id' => $user->id, 'reference' => 'REF-S', 'status' => 'in_progress', 'started_at' => now(),
        ]);

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_a_session_idle_past_the_window_is_ended(): void
    {
        config(['sanctum.idle_timeout_minutes' => 30]);
        $user = $this->client();
        $token = $this->tokenFor($user);

        PersonalAccessToken::query()->update(['last_used_at' => now()->subMinutes(31)]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/onboarding/status')
            ->assertStatus(401)
            ->assertJsonPath('code', 'session_expired');

        $this->assertSame(0, PersonalAccessToken::count(), 'the idle token is revoked, not merely refused');
    }

    public function test_a_session_used_inside_the_window_continues(): void
    {
        config(['sanctum.idle_timeout_minutes' => 30]);
        $user = $this->client();
        $token = $this->tokenFor($user);

        PersonalAccessToken::query()->update(['last_used_at' => now()->subMinutes(29)]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/onboarding/status')
            ->assertOk();

        $this->assertSame(1, PersonalAccessToken::count());
    }

    public function test_activity_keeps_a_session_alive_indefinitely(): void
    {
        // The window measures time since the last request, so a client working
        // steadily is never signed out mid-form.
        config(['sanctum.idle_timeout_minutes' => 30]);
        $user = $this->client();
        $token = $this->tokenFor($user);

        // Age the token to just inside the window, then use it.
        PersonalAccessToken::query()->update(['last_used_at' => now()->subMinutes(29)]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/onboarding/status')
            ->assertOk();

        // The window slides only because the guard restamps last_used_at on a
        // successful request. Assert that rather than assuming it: without this
        // the token would still be 29 minutes stale and the next request would
        // expire it.
        $this->assertTrue(
            PersonalAccessToken::first()->last_used_at->gt(now()->subMinute()),
            'a successful request must refresh the idle clock',
        );

        $this->travel(29)->minutes();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/onboarding/status')
            ->assertOk('a client still working is never signed out');
    }

    public function test_the_timeout_can_be_switched_off(): void
    {
        config(['sanctum.idle_timeout_minutes' => 0]);
        $user = $this->client();
        $token = $this->tokenFor($user);

        PersonalAccessToken::query()->update(['last_used_at' => now()->subDays(3)]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/onboarding/status')
            ->assertOk();
    }

    public function test_control_measure_fields_are_widened_to_five_hundred(): void
    {
        $group = QuestionGroup::create(['name' => 'AML', 'slug' => 'aml-'.uniqid(), 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Crypto custody', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => [
                ['key' => 'yes/no', 'label' => 'Yes/NO', 'type' => 'select'],
                ['key' => 'describe', 'label' => 'If Yes, Describe Control Measures', 'type' => 'text',
                    'validation' => ['max_length' => 200]],
            ]],
        ]);

        ControlMeasureFields::apply();

        $columns = $question->fresh()->options['columns'];
        $this->assertSame(500, $columns[1]['validation']['max_length']);
    }

    public function test_a_larger_configured_limit_is_left_alone(): void
    {
        $group = QuestionGroup::create(['name' => 'AML', 'slug' => 'aml-'.uniqid(), 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Gambling', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => [
                ['key' => 'describe', 'label' => 'Describe Control Measures', 'type' => 'text',
                    'validation' => ['max_length' => 2000, 'requires_letter' => true]],
            ]],
        ]);

        ControlMeasureFields::apply();

        $validation = $question->fresh()->options['columns'][0]['validation'];
        $this->assertSame(2000, $validation['max_length'], 'a deliberately larger limit must survive');
        $this->assertTrue($validation['requires_letter'], 'other configured rules are preserved');
    }

    public function test_unrelated_table_columns_are_untouched(): void
    {
        $group = QuestionGroup::create(['name' => 'Bank', 'slug' => 'bank-'.uniqid(), 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Bank', 'type' => 'table',
            'is_required' => false, 'order' => 1, 'is_active' => true,
            'options' => ['columns' => [
                ['key' => 'bank_name', 'label' => 'Bank Name', 'type' => 'text', 'validation' => ['max_length' => 100]],
            ]],
        ]);

        ControlMeasureFields::apply();

        $this->assertSame(100, $question->fresh()->options['columns'][0]['validation']['max_length']);
    }
}
