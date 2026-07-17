<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create(['name' => 'Reviewer', 'email' => 'admin@test.com', 'password' => 'x', 'is_active' => true]);

        $corporate = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
        $fi = UserType::create(['name' => 'Financial Institution', 'slug' => 'fi', 'order' => 2, 'is_active' => true]);

        $this->makeOnboarding('alice@acme.com', 'Alice Smith', $corporate->id, 'approved');
        $this->makeOnboarding('bob@bank.com', 'Bob Jones', $fi->id, 'completed', reopened: true);
        $this->makeOnboarding('carol@corp.com', 'Carol White', $corporate->id, 'in_progress');
    }

    private function makeOnboarding(string $email, string $name, int $typeId, string $status, bool $reopened = false): UserOnboarding
    {
        $user = User::create(['email' => $email, 'name' => $name, 'position' => 'CFO']);

        return UserOnboarding::create([
            'user_id' => $user->id,
            'user_type_id' => $typeId,
            'status' => $status,
            'started_at' => now(),
            'reopened_at' => $reopened ? now() : null,
        ]);
    }

    private function index(array $params = [])
    {
        return $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.index', $params));
    }

    public function test_search_matches_name_email_and_reference(): void
    {
        $this->index(['search' => 'alice@acme'])->assertSee('Alice Smith')->assertDontSee('Bob Jones');
        $this->index(['search' => 'Carol'])->assertSee('Carol White')->assertDontSee('Alice Smith');

        $bob = UserOnboarding::whereHas('user', fn ($q) => $q->where('email', 'bob@bank.com'))->first();
        $reference = 'ONB-' . now()->format('Y') . '-' . str_pad((string) $bob->id, 4, '0', STR_PAD_LEFT);
        $this->index(['search' => $reference])->assertSee('Bob Jones')->assertDontSee('Alice Smith');
    }

    public function test_filters_by_status_and_type_and_resubmission(): void
    {
        $this->index(['status' => 'approved'])->assertSee('Alice Smith')->assertDontSee('Bob Jones');

        $fi = UserType::where('slug', 'fi')->first();
        $this->index(['user_type_id' => $fi->id])->assertSee('Bob Jones')->assertDontSee('Alice Smith');

        $this->index(['resubmitted' => 1])->assertSee('Bob Jones')->assertDontSee('Carol White');
    }

    public function test_csv_export_streams_the_filtered_list(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.export-csv', ['status' => 'approved']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('onboardings-', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Reference,Name,Email', $csv);
        $this->assertStringContainsString('alice@acme.com', $csv);
        $this->assertStringContainsString('approved', $csv);
        $this->assertStringNotContainsString('bob@bank.com', $csv);

        // Unfiltered export contains everyone.
        $all = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.export-csv'))
            ->streamedContent();
        $this->assertStringContainsString('bob@bank.com', $all);
        $this->assertStringContainsString('carol@corp.com', $all);
    }

    public function test_filters_combine(): void
    {
        $corporate = UserType::where('slug', 'corporate')->first();

        $this->index(['user_type_id' => $corporate->id, 'status' => 'in_progress'])
            ->assertSee('Carol White')
            ->assertDontSee('Alice Smith')
            ->assertDontSee('Bob Jones');
    }

    /** Three applications submitted on Aug 10, 12 and 20 respectively. */
    private function seedSubmissionDates(): void
    {
        $on = fn (string $email, string $date) => UserOnboarding::whereHas('user', fn ($q) => $q->where('email', $email))
            ->update(['completed_at' => $date]);

        $on('alice@acme.com', '2026-08-10 00:30:00');   // start-of-day edge
        $on('bob@bank.com', '2026-08-12 23:30:00');     // end-of-day edge
        $on('carol@corp.com', '2026-08-20 09:00:00');   // outside
    }

    public function test_date_range_filters_by_submitted_date_inclusive(): void
    {
        $this->seedSubmissionDates();

        // Both edges fall inside the range — start-of-day / end-of-day.
        $this->index(['from' => '2026-08-10', 'to' => '2026-08-12'])
            ->assertSee('Alice Smith')
            ->assertSee('Bob Jones')
            ->assertDontSee('Carol White');
    }

    public function test_date_range_defaults_to_submitted_when_no_field_given(): void
    {
        $this->seedSubmissionDates();

        $explicit = $this->index(['from' => '2026-08-19', 'date_field' => 'submitted']);
        $implicit = $this->index(['from' => '2026-08-19']);

        foreach ([$explicit, $implicit] as $response) {
            $response->assertSee('Carol White')->assertDontSee('Alice Smith');
        }
    }

    public function test_open_ended_ranges_and_combining_with_status(): void
    {
        $this->seedSubmissionDates();

        // from-only
        $this->index(['from' => '2026-08-19'])->assertSee('Carol White')->assertDontSee('Alice Smith');
        // to-only
        $this->index(['to' => '2026-08-11'])->assertSee('Alice Smith')->assertDontSee('Carol White');
        // combined with an existing filter
        $this->index(['from' => '2026-08-01', 'status' => 'approved'])
            ->assertSee('Alice Smith')
            ->assertDontSee('Bob Jones');
    }

    public function test_date_field_picks_which_date_is_ranged(): void
    {
        $this->seedSubmissionDates();

        // Alice started long before she submitted, and was decided later still.
        UserOnboarding::whereHas('user', fn ($q) => $q->where('email', 'alice@acme.com'))
            ->update(['started_at' => '2026-07-01 09:00:00', 'decided_at' => '2026-09-05 09:00:00']);

        // A July range finds her only when ranging on `started`.
        $this->index(['from' => '2026-07-01', 'to' => '2026-07-31', 'date_field' => 'started'])->assertSee('Alice Smith');
        $this->index(['from' => '2026-07-01', 'to' => '2026-07-31', 'date_field' => 'submitted'])->assertDontSee('Alice Smith');

        // A September range finds her only when ranging on `decided`.
        $this->index(['from' => '2026-09-01', 'date_field' => 'decided'])->assertSee('Alice Smith');
        $this->index(['from' => '2026-09-01', 'date_field' => 'submitted'])->assertDontSee('Alice Smith');
    }

    public function test_rows_with_no_date_in_the_ranged_column_drop_out(): void
    {
        $this->seedSubmissionDates();

        // Carol never got a decision, so a decided-range excludes her even
        // though her range would otherwise match on submitted.
        $this->index(['from' => '2026-01-01', 'date_field' => 'decided'])
            ->assertDontSee('Carol White')
            ->assertDontSee('Alice Smith');
    }

    public function test_unknown_date_field_falls_back_to_submitted(): void
    {
        $this->seedSubmissionDates();

        $this->index(['from' => '2026-08-19', 'date_field' => 'created_at; DROP TABLE'])
            ->assertOk()
            ->assertSee('Carol White')
            ->assertDontSee('Alice Smith');
    }

    public function test_malformed_dates_are_ignored_rather_than_erroring(): void
    {
        $this->seedSubmissionDates();

        $this->index(['from' => 'not-a-date', 'to' => ''])
            ->assertOk()
            ->assertSee('Alice Smith')
            ->assertSee('Carol White');
    }

    public function test_csv_export_follows_the_date_range(): void
    {
        $this->seedSubmissionDates();

        $csv = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.export-csv', ['from' => '2026-08-10', 'to' => '2026-08-12']))
            ->streamedContent();

        $this->assertStringContainsString('alice@acme.com', $csv);
        $this->assertStringContainsString('bob@bank.com', $csv);
        $this->assertStringNotContainsString('carol@corp.com', $csv);
    }
}
