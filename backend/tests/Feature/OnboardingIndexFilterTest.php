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
}
