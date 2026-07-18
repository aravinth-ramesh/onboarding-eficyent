<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FilterPreset;
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

    private function savePreset(string $name, array $filters)
    {
        return $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.store', array_merge(['context' => 'user-onboardings'], $filters)), ['name' => $name]);
    }

    public function test_saving_a_preset_stores_only_this_pages_filters(): void
    {
        $corporate = UserType::where('slug', 'corporate')->first();

        $this->savePreset('Corporate approved', [
            'status' => 'approved', 'user_type_id' => $corporate->id, 'assigned' => 'me',
            'search' => '', 'page' => '2', 'sort' => 'asc',
        ])->assertRedirect()->assertSessionHas('success');

        // `page`/`sort` belong to other pages, blanks are dropped.
        $this->assertSame(
            ['status' => 'approved', 'user_type_id' => (string) $corporate->id, 'assigned' => 'me'],
            FilterPreset::sole()->filters,
        );
        $this->assertSame('user-onboardings', FilterPreset::sole()->context);
    }

    public function test_date_field_is_only_saved_alongside_a_range(): void
    {
        // The filter bar submits date_field on every search — on its own it
        // narrows nothing, so it should not land in the preset.
        $this->savePreset('No range', ['status' => 'approved', 'date_field' => 'decided']);
        $this->assertSame(['status' => 'approved'], FilterPreset::sole()->filters);

        // With a range it is meaningful and must be kept.
        $this->savePreset('With range', ['date_field' => 'decided', 'from' => '2026-08-01']);
        $this->assertSame(
            ['from' => '2026-08-01', 'date_field' => 'decided'],
            FilterPreset::where('name', 'With range')->sole()->filters,
        );
    }

    public function test_applying_a_preset_filters_the_list_and_shows_as_active(): void
    {
        $preset = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Only approved', 'filters' => ['status' => 'approved'],
        ]);

        $this->index($preset->filters)
            ->assertSee('Alice Smith')
            ->assertDontSee('Bob Jones')
            ->assertSee('Only approved')     // the dropdown's current label
            ->assertDontSee('Save preset');  // already saved
    }

    public function test_a_plain_search_still_matches_a_preset_saved_with_a_date_field(): void
    {
        // Saved via the form, which always submits date_field...
        $this->savePreset('Approved', ['status' => 'approved', 'date_field' => 'submitted']);

        // ...so arriving from a link without it must still count as the same view.
        $this->index(['status' => 'approved'])
            ->assertSee('Approved')
            ->assertDontSee('Save preset');
    }

    public function test_presets_do_not_leak_across_pages(): void
    {
        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'scheduled-emails',
            'name' => 'An email preset', 'filters' => ['status' => 'pending'],
        ]);

        $this->index()->assertDontSee('An email preset');
    }

    public function test_presets_are_private_to_the_admin_who_saved_them(): void
    {
        $other = Admin::create(['name' => 'Other', 'email' => 'other@test.com', 'password' => 'x', 'is_active' => true]);

        $theirs = FilterPreset::create([
            'admin_id' => $other->id, 'context' => 'user-onboardings',
            'name' => 'Their private view', 'filters' => ['status' => 'rejected'],
        ]);

        $this->index()->assertDontSee('Their private view');

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.filter-presets.destroy', ['context' => 'user-onboardings', 'preset' => $theirs]))
            ->assertForbidden();

        $this->assertDatabaseCount('filter_presets', 1);
    }

    public function test_duplicating_a_preset_copies_its_filters_under_a_new_name(): void
    {
        $original = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Approved', 'filters' => ['status' => 'approved', 'from' => '2026-08-01', 'date_field' => 'decided'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->post(route('admin.filter-presets.duplicate', ['context' => 'user-onboardings', 'preset' => $original]),
                ['name' => '  Approved (copy)  '])
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        $copy = FilterPreset::where('name', 'Approved (copy)')->sole();

        $this->assertSame($original->filters, $copy->filters);
        $this->assertSame($this->admin->id, $copy->admin_id);
        $this->assertSame('user-onboardings', $copy->context);
        // The original is untouched.
        $this->assertDatabaseHas('filter_presets', ['id' => $original->id, 'name' => 'Approved']);
    }

    public function test_duplicating_refuses_an_existing_name_rather_than_overwriting(): void
    {
        $original = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Approved', 'filters' => ['status' => 'approved'],
        ]);
        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Taken', 'filters' => ['status' => 'rejected'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.duplicate', ['context' => 'user-onboardings', 'preset' => $original]),
                ['name' => 'Taken'])
            ->assertSessionHasErrors('name');

        // "Taken" still holds its own filters — nothing was clobbered.
        $this->assertSame(['status' => 'rejected'], FilterPreset::where('name', 'Taken')->sole()->filters);
        $this->assertDatabaseCount('filter_presets', 2);
    }

    public function test_the_same_name_is_free_on_a_different_page(): void
    {
        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'scheduled-emails',
            'name' => 'Shared name', 'filters' => ['status' => 'pending'],
        ]);
        $onboardingPreset = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Approved', 'filters' => ['status' => 'approved'],
        ]);

        // Uniqueness is scoped per page, so this must not collide.
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.duplicate', ['context' => 'user-onboardings', 'preset' => $onboardingPreset]),
                ['name' => 'Shared name'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('filter_presets', 3);
    }

    public function test_renaming_keeps_the_filters_and_changes_only_the_label(): void
    {
        $preset = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Old name', 'filters' => ['status' => 'approved', 'from' => '2026-08-01'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->patch(route('admin.filter-presets.rename', ['context' => 'user-onboardings', 'preset' => $preset]),
                ['name' => '  New name  '])
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        $preset->refresh();

        $this->assertSame('New name', $preset->name);
        $this->assertSame(['status' => 'approved', 'from' => '2026-08-01'], $preset->filters);
        $this->assertDatabaseCount('filter_presets', 1); // renamed in place, not copied
    }

    public function test_renaming_to_its_own_name_is_not_a_collision(): void
    {
        $preset = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Same', 'filters' => ['status' => 'approved'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.filter-presets.rename', ['context' => 'user-onboardings', 'preset' => $preset]),
                ['name' => 'Same'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Same', $preset->refresh()->name);
    }

    public function test_renaming_onto_another_preset_is_refused(): void
    {
        $preset = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Mine', 'filters' => ['status' => 'approved'],
        ]);
        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Taken', 'filters' => ['status' => 'rejected'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.filter-presets.rename', ['context' => 'user-onboardings', 'preset' => $preset]),
                ['name' => 'Taken'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Mine', $preset->refresh()->name);
        $this->assertSame(['status' => 'rejected'], FilterPreset::where('name', 'Taken')->sole()->filters);
    }

    public function test_cannot_rename_another_admins_preset(): void
    {
        $other = Admin::create(['name' => 'Other', 'email' => 'other3@test.com', 'password' => 'x', 'is_active' => true]);
        $theirs = FilterPreset::create([
            'admin_id' => $other->id, 'context' => 'user-onboardings',
            'name' => 'Theirs', 'filters' => ['status' => 'approved'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.filter-presets.rename', ['context' => 'user-onboardings', 'preset' => $theirs]),
                ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Theirs', $theirs->refresh()->name);
    }

    public function test_a_preset_cannot_be_touched_through_the_wrong_pages_context(): void
    {
        $preset = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Onboarding view', 'filters' => ['status' => 'approved'],
        ]);

        // Valid context, but not this preset's — the URL must not disagree
        // with the record.
        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.filter-presets.rename', ['context' => 'scheduled-emails', 'preset' => $preset]),
                ['name' => 'Sneaky'])
            ->assertNotFound();

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.filter-presets.destroy', ['context' => 'scheduled-emails', 'preset' => $preset]))
            ->assertNotFound();

        $this->assertSame('Onboarding view', $preset->refresh()->name);
        $this->assertDatabaseCount('filter_presets', 1);
    }

    public function test_cannot_duplicate_another_admins_preset(): void
    {
        $other = Admin::create(['name' => 'Other', 'email' => 'other2@test.com', 'password' => 'x', 'is_active' => true]);
        $theirs = FilterPreset::create([
            'admin_id' => $other->id, 'context' => 'user-onboardings',
            'name' => 'Theirs', 'filters' => ['status' => 'approved'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.duplicate', ['context' => 'user-onboardings', 'preset' => $theirs]),
                ['name' => 'Stolen'])
            ->assertForbidden();

        $this->assertDatabaseCount('filter_presets', 1);
    }

    public function test_exporting_returns_this_admins_presets_for_this_page_as_json(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-01 12:00:00'));

        $other = Admin::create(['name' => 'Other', 'email' => 'other4@test.com', 'password' => 'x', 'is_active' => true]);

        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Beta view', 'filters' => ['status' => 'rejected'],
        ]);
        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Alpha view', 'filters' => ['status' => 'approved', 'from' => '2026-08-01', 'date_field' => 'decided'],
        ]);
        // Must not appear: another page, and another admin.
        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'scheduled-emails',
            'name' => 'An email preset', 'filters' => ['status' => 'pending'],
        ]);
        FilterPreset::create([
            'admin_id' => $other->id, 'context' => 'user-onboardings',
            'name' => 'Their private view', 'filters' => ['status' => 'approved'],
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.filter-presets.export', ['context' => 'user-onboardings']));

        $response->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="filter-presets-user-onboardings-2026-09-01.json"');

        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));

        $payload = $response->json();

        $this->assertSame(1, $payload['version']);
        $this->assertSame('user-onboardings', $payload['context']);
        $this->assertNotEmpty($payload['exported_at']);

        // In the admin's saved order (here, creation order — Beta was saved
        // first), and only name+filters — no ids.
        $this->assertSame([
            ['name' => 'Beta view', 'filters' => ['status' => 'rejected']],
            ['name' => 'Alpha view', 'filters' => ['status' => 'approved', 'from' => '2026-08-01', 'date_field' => 'decided']],
        ], $payload['presets']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('An email preset', $body);
        $this->assertStringNotContainsString('Their private view', $body);
        $this->assertStringNotContainsString('admin_id', $body);
    }

    public function test_exporting_with_no_presets_returns_an_empty_set(): void
    {
        $payload = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.filter-presets.export', ['context' => 'user-onboardings']))
            ->assertOk()
            ->json();

        $this->assertSame([], $payload['presets']);
    }

    public function test_exporting_an_unknown_context_is_rejected(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.filter-presets.export', ['context' => 'audit-logs']))
            ->assertNotFound();
    }

    public function test_export_route_is_not_captured_by_the_preset_wildcards(): void
    {
        // `export` sits where a {preset} id would — the GET route must win.
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.filter-presets.export', ['context' => 'user-onboardings']))
            ->assertOk()
            ->assertJsonStructure(['version', 'context', 'exported_at', 'presets']);
    }

    /** Wrap a payload as an uploaded JSON file for the import endpoint. */
    private function presetFile(array $payload): \Illuminate\Http\UploadedFile
    {
        return \Illuminate\Http\UploadedFile::fake()->createWithContent('presets.json', json_encode($payload));
    }

    private function importPayload(array $presets, string $context = 'user-onboardings'): array
    {
        return ['version' => 1, 'context' => $context, 'exported_at' => '2026-09-01T00:00:00+00:00', 'presets' => $presets];
    }

    public function test_importing_creates_presets_from_a_valid_file(): void
    {
        $file = $this->presetFile($this->importPayload([
            ['name' => 'From file A', 'filters' => ['status' => 'approved']],
            ['name' => 'From file B', 'filters' => ['from' => '2026-08-01', 'date_field' => 'decided']],
        ]));

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->post(route('admin.filter-presets.import', ['context' => 'user-onboardings']), ['file' => $file])
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('filter_presets', 2);
        $this->assertSame(['status' => 'approved'], FilterPreset::where('name', 'From file A')->sole()->filters);
        $this->assertSame(['from' => '2026-08-01', 'date_field' => 'decided'], FilterPreset::where('name', 'From file B')->sole()->filters);
        $this->assertSame($this->admin->id, FilterPreset::where('name', 'From file A')->sole()->admin_id);
    }

    public function test_import_is_non_destructive_by_default(): void
    {
        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Mine', 'filters' => ['status' => 'rejected'],
        ]);

        $file = $this->presetFile($this->importPayload([
            ['name' => 'Mine', 'filters' => ['status' => 'approved']],   // collides
            ['name' => 'Fresh', 'filters' => ['status' => 'pending']],   // new
        ]));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.import', ['context' => 'user-onboardings']), ['file' => $file])
            ->assertSessionHas('success');

        // The existing preset keeps its own filters; only the new one is added.
        $this->assertSame(['status' => 'rejected'], FilterPreset::where('name', 'Mine')->sole()->filters);
        $this->assertDatabaseCount('filter_presets', 2);
    }

    public function test_import_overwrites_when_asked(): void
    {
        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Mine', 'filters' => ['status' => 'rejected'],
        ]);

        $file = $this->presetFile($this->importPayload([
            ['name' => 'Mine', 'filters' => ['status' => 'approved']],
        ]));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.import', ['context' => 'user-onboardings']), ['file' => $file, 'overwrite' => '1'])
            ->assertSessionHas('success');

        $this->assertSame(['status' => 'approved'], FilterPreset::where('name', 'Mine')->sole()->filters);
        $this->assertDatabaseCount('filter_presets', 1);
    }

    public function test_import_sanitises_untrusted_filters(): void
    {
        $file = $this->presetFile($this->importPayload([
            // Unknown keys and a date_field with no range must be stripped.
            ['name' => 'Crafted', 'filters' => ['status' => 'approved', 'evil' => 'DROP TABLE', 'date_field' => 'decided']],
            // Nothing valid left after sanitising -> not stored.
            ['name' => 'Empty after clean', 'filters' => ['nonsense' => 'x']],
            // Non-string name -> invalid.
            ['name' => ['array'], 'filters' => ['status' => 'pending']],
        ]));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.import', ['context' => 'user-onboardings']), ['file' => $file])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('filter_presets', 1);
        $this->assertSame(['status' => 'approved'], FilterPreset::where('name', 'Crafted')->sole()->filters);
    }

    public function test_import_rejects_a_file_from_another_page(): void
    {
        $file = $this->presetFile($this->importPayload(
            [['name' => 'Email preset', 'filters' => ['status' => 'pending']]],
            context: 'scheduled-emails',
        ));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.import', ['context' => 'user-onboardings']), ['file' => $file])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('filter_presets', 0);
    }

    public function test_import_rejects_a_non_preset_file(): void
    {
        $garbage = \Illuminate\Http\UploadedFile::fake()->createWithContent('notes.json', 'this is not json at all');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.import', ['context' => 'user-onboardings']), ['file' => $garbage])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('filter_presets', 0);
    }

    public function test_import_requires_a_file(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.import', ['context' => 'user-onboardings']), [])
            ->assertSessionHasErrors('file');
    }

    public function test_import_into_an_unknown_context_is_rejected(): void
    {
        $file = $this->presetFile($this->importPayload([['name' => 'X', 'filters' => ['status' => 'approved']]], context: 'audit-logs'));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.import', ['context' => 'audit-logs']), ['file' => $file])
            ->assertNotFound();
    }

    public function test_export_then_import_round_trips_into_another_admin(): void
    {
        $source = [
            ['name' => 'Alpha', 'filters' => ['status' => 'approved', 'user_type_id' => '1']],
            ['name' => 'Beta', 'filters' => ['from' => '2026-08-01', 'to' => '2026-08-31', 'date_field' => 'decided']],
        ];
        foreach ($source as $s) {
            FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings'] + $s);
        }

        // Export as this admin...
        $exported = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.filter-presets.export', ['context' => 'user-onboardings']))
            ->json();

        // ...and import the exact payload as a different admin.
        $other = Admin::create(['name' => 'Other', 'email' => 'other5@test.com', 'password' => 'x', 'is_active' => true]);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('presets.json', json_encode($exported));

        $this->actingAs($other, 'admin')
            ->post(route('admin.filter-presets.import', ['context' => 'user-onboardings']), ['file' => $file])
            ->assertSessionHas('success');

        $theirs = FilterPreset::where('admin_id', $other->id)->orderBy('name')->get();
        $this->assertSame(['Alpha', 'Beta'], $theirs->pluck('name')->all());
        $this->assertSame(['status' => 'approved', 'user_type_id' => '1'], $theirs->firstWhere('name', 'Alpha')->filters);
        $this->assertSame(['from' => '2026-08-01', 'to' => '2026-08-31', 'date_field' => 'decided'], $theirs->firstWhere('name', 'Beta')->filters);
    }

    public function test_preset_search_box_appears_only_once_the_list_is_long(): void
    {
        $make = fn (string $name) => FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => $name, 'filters' => ['status' => 'approved'],
        ]);

        // A short list needs no search box.
        collect(['One', 'Two', 'Three'])->each($make);
        $this->index()->assertDontSee('Search saved views');

        // Past the threshold it appears, and each row carries a lowercased key.
        collect(['Four', 'Five', 'SHOUTY Name'])->each($make);
        $this->index()
            ->assertSee('Search saved views')
            ->assertSee('preset-filter-input', false)
            ->assertSee('data-preset-name="shouty name"', false); // lowercased for matching
    }

    public function test_presets_render_in_saved_order_with_a_sort_toggle(): void
    {
        // Deliberately non-alphabetical: the list follows the saved (creation)
        // order now, not name — the sort toggle flips to name asc/desc client-side.
        $order = ['Zebra view', 'Alpha view', 'Mango view', 'Beta view', 'Yellow view', 'Cherry view'];
        foreach ($order as $n) {
            FilterPreset::create([
                'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
                'name' => $n, 'filters' => ['status' => 'approved'],
            ]);
        }

        $html = $this->index()
            ->assertSee('preset-sort-toggle', false)
            ->getContent();

        // Rows appear in the order they were saved, not sorted by name — each
        // name comes before the next one in the saved sequence.
        $prev = -1;
        foreach ($order as $name) {
            $at = strpos($html, $name);
            $this->assertGreaterThan($prev, $at, "\"{$name}\" out of saved order");
            $prev = $at;
        }
    }

    public function test_new_presets_append_to_the_end_of_the_saved_order(): void
    {
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'First', 'filters' => ['status' => 'approved']]);
        $b = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Second', 'filters' => ['status' => 'rejected']]);
        $c = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Third', 'filters' => ['status' => 'pending']]);

        $this->assertSame([1, 2, 3], [$a->position, $b->position, $c->position]);
    }

    public function test_reordering_persists_the_new_order(): void
    {
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);
        $b = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected']]);
        $c = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'C', 'filters' => ['status' => 'pending']]);

        // Move C to the front, B to the back.
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.filter-presets.reorder', ['context' => 'user-onboardings']), ['order' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, $c->refresh()->position);
        $this->assertSame(2, $a->refresh()->position);
        $this->assertSame(3, $b->refresh()->position);

        // The list now reads C, A, B.
        $ordered = FilterPreset::ownedBy($this->admin->id, 'user-onboardings')->pluck('name')->all();
        $this->assertSame(['C', 'A', 'B'], $ordered);
    }

    public function test_reorder_ignores_ids_that_are_not_the_callers_own(): void
    {
        $other = Admin::create(['name' => 'Other', 'email' => 'other7@test.com', 'password' => 'x', 'is_active' => true]);
        $mineA = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);
        $mineB = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected']]);
        $theirs = FilterPreset::create(['admin_id' => $other->id, 'context' => 'user-onboardings', 'name' => 'Theirs', 'filters' => ['status' => 'approved']]);
        $theirPos = $theirs->position;

        // A foreign id in the list is skipped; my own presets still reorder.
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.filter-presets.reorder', ['context' => 'user-onboardings']), ['order' => [$mineB->id, $theirs->id, $mineA->id]])
            ->assertOk();

        $this->assertSame(1, $mineB->refresh()->position);
        $this->assertSame(2, $mineA->refresh()->position);
        $this->assertSame($theirPos, $theirs->refresh()->position); // untouched
    }

    public function test_reorder_requires_an_order_array_and_a_known_context(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.filter-presets.reorder', ['context' => 'user-onboardings']), [])
            ->assertJsonValidationErrors('order');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.filter-presets.reorder', ['context' => 'audit-logs']), ['order' => [1]])
            ->assertNotFound();
    }

    public function test_active_preset_can_be_pinned_from_the_list_view(): void
    {
        $preset = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Approved', 'filters' => ['status' => 'approved'],
        ]);

        // Not applied: no list-view pin control. (Assert on the button's title,
        // not its class — the class also appears in the always-rendered JS.)
        $this->index()->assertDontSee('Pin this saved view to top');

        // Applied (filters match): the control appears, offering to pin.
        $this->index(['status' => 'approved'])
            ->assertSee('preset-active-pin', false)
            ->assertSee('Pin this saved view to top')
            ->assertSee(route('admin.filter-presets.pin', ['context' => 'user-onboardings', 'preset' => $preset]), false);

        // Once pinned, the same applied view offers to unpin.
        $preset->update(['pinned' => true]);
        $this->index(['status' => 'approved'])
            ->assertSee('Unpin this saved view')
            ->assertDontSee('Pin this saved view to top');
    }

    public function test_pinning_from_the_list_view_toggles_the_active_preset(): void
    {
        $preset = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Approved', 'filters' => ['status' => 'approved'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index', ['status' => 'approved']))
            ->post(route('admin.filter-presets.pin', ['context' => 'user-onboardings', 'preset' => $preset]))
            ->assertRedirect(route('admin.user-onboardings.index', ['status' => 'approved'])) // back to the filtered view
            ->assertSessionHas('success');

        $this->assertTrue($preset->refresh()->pinned);
    }

    public function test_reset_all_customizations_restores_defaults_but_keeps_presets(): void
    {
        // Custom shortcut, a pinned+reordered onboardings set, and a pinned
        // scheduled-emails preset on another page.
        $this->admin->update(['pin_shortcut' => 'alt+k']);

        $z = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Zulu', 'filters' => ['status' => 'approved'], 'pinned' => true, 'position' => 1]);
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Able', 'filters' => ['status' => 'rejected'], 'pinned' => false, 'position' => 2]);
        $email = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'scheduled-emails', 'name' => 'Blast', 'filters' => ['status' => 'pending'], 'pinned' => true, 'position' => 5]);

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->post(route('admin.settings.reset-preset-customizations'))
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        // Shortcut back to default.
        $this->assertNull($this->admin->refresh()->pin_shortcut);

        // Onboardings: nothing pinned, order alphabetical (Able before Zulu).
        $this->assertFalse($z->refresh()->pinned);
        $this->assertFalse($a->refresh()->pinned);
        $this->assertSame(['Able', 'Zulu'], FilterPreset::ownedBy($this->admin->id, 'user-onboardings')->pluck('name')->all());

        // The other page is reset too (spans every context).
        $this->assertFalse($email->refresh()->pinned);
        $this->assertSame(1, $email->position);

        // The saved views themselves survive.
        $this->assertDatabaseCount('filter_presets', 3);
    }

    public function test_reset_all_customizations_leaves_other_admins_alone(): void
    {
        $other = Admin::create(['name' => 'Other', 'email' => 'other13@test.com', 'password' => 'x', 'is_active' => true, 'pin_shortcut' => 'alt+j']);
        $theirs = FilterPreset::create(['admin_id' => $other->id, 'context' => 'user-onboardings', 'name' => 'Theirs', 'filters' => ['status' => 'approved'], 'pinned' => true, 'position' => 3]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.settings.reset-preset-customizations'))
            ->assertSessionHas('success');

        // Untouched.
        $this->assertSame('alt+j', $other->refresh()->pin_shortcut);
        $this->assertTrue($theirs->refresh()->pinned);
        $this->assertSame(3, $theirs->position);
    }

    public function test_admin_can_set_a_custom_pin_shortcut(): void
    {
        $this->assertNull($this->admin->pin_shortcut);
        $this->assertSame('shift+p', $this->admin->pinShortcut()); // default

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->patch(route('admin.settings.pin-shortcut'), ['pin_shortcut' => 'ALT+K'])
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        $this->assertSame('alt+k', $this->admin->refresh()->pin_shortcut); // normalised lower-case
    }

    public function test_custom_pin_shortcut_appears_in_the_list_view(): void
    {
        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Approved', 'filters' => ['status' => 'approved'],
        ]);
        $this->admin->update(['pin_shortcut' => 'ctrl+shift+p']);

        $this->index(['status' => 'approved'])
            ->assertSee('Ctrl+Shift+P')                        // human label on the button
            ->assertSee('data-pin-shortcut="ctrl+shift+p"', false); // combo the handler reads
    }

    public function test_pin_shortcut_requires_a_modifier(): void
    {
        // A bare key would fire from ordinary typing — rejected.
        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.settings.pin-shortcut'), ['pin_shortcut' => 'p'])
            ->assertSessionHasErrors('pin_shortcut');

        // Rubbish is rejected too.
        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.settings.pin-shortcut'), ['pin_shortcut' => 'shift+shift'])
            ->assertSessionHasErrors('pin_shortcut');

        $this->assertNull($this->admin->refresh()->pin_shortcut);
    }

    public function test_pin_shortcut_can_be_reset_to_default(): void
    {
        $this->admin->update(['pin_shortcut' => 'alt+k']);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.settings.pin-shortcut'), ['pin_shortcut' => ''])
            ->assertSessionHas('success');

        $this->assertNull($this->admin->refresh()->pin_shortcut);
        $this->assertSame('shift+p', $this->admin->pinShortcut());
    }

    public function test_applied_preset_pin_advertises_the_keyboard_shortcut(): void
    {
        FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Approved', 'filters' => ['status' => 'approved'],
        ]);

        // Applied: the pin button carries its shortcut hint (kbd).
        $this->index(['status' => 'approved'])->assertSee('preset-shortcut-kbd', false);
        // No preset applied: no pin button, so no button-level hint.
        $this->index()->assertDontSee('preset-shortcut-kbd', false);
    }

    public function test_unpinning_the_applied_preset_from_the_list_view(): void
    {
        // Already pinned and applied — the list-view control offers "Unpin".
        $preset = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Approved', 'filters' => ['status' => 'approved'], 'pinned' => true,
        ]);

        $this->index(['status' => 'approved'])
            ->assertSee('preset-active-pin', false)
            ->assertSee('Unpin this saved view'); // the pinned-state control's tooltip

        // Clicking it unpins and returns to the same filtered view.
        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index', ['status' => 'approved']))
            ->post(route('admin.filter-presets.pin', ['context' => 'user-onboardings', 'preset' => $preset]))
            ->assertRedirect(route('admin.user-onboardings.index', ['status' => 'approved']))
            ->assertSessionHas('success');

        $this->assertFalse($preset->refresh()->pinned);
    }

    public function test_pinned_first_sort_control_shows_only_when_something_is_pinned(): void
    {
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected']]);

        // Assert on the button's title (unique to the control) — the class
        // string also appears in the always-rendered JS selector.
        $this->index()->assertDontSee('Sort pinned first, then name');

        $a->update(['pinned' => true]);
        $this->index()->assertSee('Sort pinned first, then name');
    }

    public function test_pinned_count_badge_reflects_the_number_pinned(): void
    {
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);
        $b = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected']]);
        $c = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'C', 'filters' => ['status' => 'pending']]);

        // Nothing pinned: no count badge or pinned-only label.
        $this->index()->assertDontSee('preset-pinned-count', false)->assertDontSee('Pinned only');

        // Pin two: the badge shows 2, and only this admin's pins on this page count.
        $a->update(['pinned' => true]);
        $b->update(['pinned' => true]);
        // A pin belonging to someone else, and to another page, must not inflate it.
        $other = Admin::create(['name' => 'Other', 'email' => 'other12@test.com', 'password' => 'x', 'is_active' => true]);
        FilterPreset::create(['admin_id' => $other->id, 'context' => 'user-onboardings', 'name' => 'Theirs', 'filters' => ['status' => 'approved'], 'pinned' => true]);
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'scheduled-emails', 'name' => 'Email', 'filters' => ['status' => 'pending'], 'pinned' => true]);

        $this->index()
            ->assertSee('preset-pinned-count', false)
            ->assertSee('Pinned only (2)');
    }

    public function test_unpin_all_clears_every_pin_for_this_page(): void
    {
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved'], 'pinned' => true]);
        $b = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected'], 'pinned' => true]);
        $c = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'C', 'filters' => ['status' => 'pending']]); // not pinned

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->post(route('admin.filter-presets.unpin-all', ['context' => 'user-onboardings']))
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        $this->assertFalse($a->refresh()->pinned);
        $this->assertFalse($b->refresh()->pinned);
        $this->assertFalse($c->refresh()->pinned);
    }

    public function test_unpin_all_leaves_other_admins_and_pages_alone(): void
    {
        $other = Admin::create(['name' => 'Other', 'email' => 'other11@test.com', 'password' => 'x', 'is_active' => true]);
        $theirs = FilterPreset::create(['admin_id' => $other->id, 'context' => 'user-onboardings', 'name' => 'Theirs', 'filters' => ['status' => 'approved'], 'pinned' => true]);
        $myEmail = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'scheduled-emails', 'name' => 'Email', 'filters' => ['status' => 'pending'], 'pinned' => true]);
        $mine = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Mine', 'filters' => ['status' => 'approved'], 'pinned' => true]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.unpin-all', ['context' => 'user-onboardings']))
            ->assertSessionHas('success');

        $this->assertFalse($mine->refresh()->pinned);       // cleared
        $this->assertTrue($theirs->refresh()->pinned);      // other admin untouched
        $this->assertTrue($myEmail->refresh()->pinned);     // my other page untouched
    }

    public function test_unpin_all_with_no_pins_reports_it(): void
    {
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.unpin-all', ['context' => 'user-onboardings']))
            ->assertSessionHas('error');
    }

    public function test_unpin_all_rejects_an_unknown_context(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.unpin-all', ['context' => 'audit-logs']))
            ->assertNotFound();
    }

    public function test_unpin_all_control_shows_only_when_something_is_pinned(): void
    {
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected']]);

        $this->index()->assertDontSee('Unpin all');

        $a->update(['pinned' => true]);
        $this->index()->assertSee('Unpin all');
    }

    public function test_bulk_pin_pins_the_selected_presets(): void
    {
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);
        $b = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected']]);
        $c = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'C', 'filters' => ['status' => 'pending']]);

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->post(route('admin.filter-presets.bulk-pin', ['context' => 'user-onboardings']), ['ids' => [$a->id, $c->id], 'pinned' => '1'])
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        $this->assertTrue($a->refresh()->pinned);
        $this->assertTrue($c->refresh()->pinned);
        $this->assertFalse($b->refresh()->pinned);
        // Pinned pair floats to the top.
        $this->assertSame(['A', 'C', 'B'], FilterPreset::ownedBy($this->admin->id, 'user-onboardings')->pluck('name')->all());
    }

    public function test_bulk_pin_can_unpin_the_selected_presets(): void
    {
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved'], 'pinned' => true]);
        $b = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected'], 'pinned' => true]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.bulk-pin', ['context' => 'user-onboardings']), ['ids' => [$a->id, $b->id], 'pinned' => '0'])
            ->assertSessionHas('success');

        $this->assertFalse($a->refresh()->pinned);
        $this->assertFalse($b->refresh()->pinned);
    }

    public function test_bulk_pin_never_touches_another_admins_presets(): void
    {
        $other = Admin::create(['name' => 'Other', 'email' => 'other10@test.com', 'password' => 'x', 'is_active' => true]);
        $mine = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Mine', 'filters' => ['status' => 'approved']]);
        $theirs = FilterPreset::create(['admin_id' => $other->id, 'context' => 'user-onboardings', 'name' => 'Theirs', 'filters' => ['status' => 'approved']]);

        // A foreign id in the list matches nothing and is silently left alone.
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.bulk-pin', ['context' => 'user-onboardings']), ['ids' => [$mine->id, $theirs->id], 'pinned' => '1'])
            ->assertSessionHas('success');

        $this->assertTrue($mine->refresh()->pinned);
        $this->assertFalse($theirs->refresh()->pinned);
    }

    public function test_bulk_pin_requires_ids_and_a_pinned_flag(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.bulk-pin', ['context' => 'user-onboardings']), ['pinned' => '1'])
            ->assertSessionHasErrors('ids');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.bulk-pin', ['context' => 'user-onboardings']), ['ids' => [1]])
            ->assertSessionHasErrors('pinned');
    }

    public function test_bulk_pin_rejects_an_unknown_context(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.bulk-pin', ['context' => 'audit-logs']), ['ids' => [1], 'pinned' => '1'])
            ->assertNotFound();
    }

    public function test_bulk_select_controls_render_with_more_than_one_preset(): void
    {
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected']]);

        $this->index()
            ->assertSee('preset-check', false)
            ->assertSee('preset-bulk-pin', false)
            ->assertSee('presetBulkPinForm', false);
    }

    public function test_pinned_only_toggle_renders_only_when_a_pin_exists(): void
    {
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Alpha', 'filters' => ['status' => 'approved']]);
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Bravo', 'filters' => ['status' => 'rejected']]);

        // No pins yet: no "Pinned only" control, and rows are flagged unpinned.
        $this->index()
            ->assertDontSee('Pinned only')
            ->assertSee('data-preset-pinned="0"', false);

        // Pin one: the toggle appears, and that row is flagged pinned.
        $a->update(['pinned' => true]);
        $this->index()
            ->assertSee('Pinned only')
            ->assertSee('preset-pinned-toggle', false)
            ->assertSee('data-preset-pinned="1"', false);
    }

    public function test_pinning_floats_a_preset_to_the_top(): void
    {
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);
        $b = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected']]);
        $c = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'C', 'filters' => ['status' => 'pending']]);

        // Pin C — it jumps above A and B without changing anyone's position.
        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->post(route('admin.filter-presets.pin', ['context' => 'user-onboardings', 'preset' => $c]))
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        $this->assertTrue($c->refresh()->pinned);
        $this->assertSame(['C', 'A', 'B'], FilterPreset::ownedBy($this->admin->id, 'user-onboardings')->pluck('name')->all());
        $this->assertSame(3, $c->position); // position untouched; it floats via the flag
    }

    public function test_pinning_is_a_toggle(): void
    {
        $preset = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved'], 'pinned' => true]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.pin', ['context' => 'user-onboardings', 'preset' => $preset]))
            ->assertSessionHas('success');

        $this->assertFalse($preset->refresh()->pinned);
    }

    public function test_several_pins_cluster_at_top_in_their_own_order(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $n) {
            FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => $n, 'filters' => ['status' => 'approved']]);
        }
        // Pin D then B: pinned group keeps position order (B before D), not pin order.
        FilterPreset::where('name', 'D')->update(['pinned' => true]);
        FilterPreset::where('name', 'B')->update(['pinned' => true]);

        $this->assertSame(['B', 'D', 'A', 'C'], FilterPreset::ownedBy($this->admin->id, 'user-onboardings')->pluck('name')->all());
    }

    public function test_pinned_preset_stays_on_top_after_reset_order(): void
    {
        $z = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Zulu', 'filters' => ['status' => 'approved'], 'pinned' => true]);
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Able', 'filters' => ['status' => 'rejected']]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.reset-order', ['context' => 'user-onboardings']));

        // Reset sorts by name, but the pin still wins — Zulu stays first.
        $this->assertSame(['Zulu', 'Able'], FilterPreset::ownedBy($this->admin->id, 'user-onboardings')->pluck('name')->all());
    }

    public function test_cannot_pin_another_admins_preset(): void
    {
        $other = Admin::create(['name' => 'Other', 'email' => 'other9@test.com', 'password' => 'x', 'is_active' => true]);
        $theirs = FilterPreset::create(['admin_id' => $other->id, 'context' => 'user-onboardings', 'name' => 'Theirs', 'filters' => ['status' => 'approved']]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.pin', ['context' => 'user-onboardings', 'preset' => $theirs]))
            ->assertForbidden();

        $this->assertFalse($theirs->refresh()->pinned);
    }

    public function test_reset_order_restores_alphabetical_positions(): void
    {
        // Saved out of alphabetical order, then manually shuffled further.
        $c = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Charlie', 'filters' => ['status' => 'approved']]);
        $a = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Alpha', 'filters' => ['status' => 'rejected']]);
        $b = FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Bravo', 'filters' => ['status' => 'pending']]);

        // Currently in creation order: Charlie, Alpha, Bravo.
        $this->assertSame(['Charlie', 'Alpha', 'Bravo'], FilterPreset::ownedBy($this->admin->id, 'user-onboardings')->pluck('name')->all());

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->post(route('admin.filter-presets.reset-order', ['context' => 'user-onboardings']))
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        // Back to A→Z.
        $this->assertSame([1, 2, 3], [$a->refresh()->position, $b->refresh()->position, $c->refresh()->position]);
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], FilterPreset::ownedBy($this->admin->id, 'user-onboardings')->pluck('name')->all());
    }

    public function test_reset_order_only_touches_the_callers_own_presets(): void
    {
        $other = Admin::create(['name' => 'Other', 'email' => 'other8@test.com', 'password' => 'x', 'is_active' => true]);
        // Their list is out of order and must stay that way.
        $theirZ = FilterPreset::create(['admin_id' => $other->id, 'context' => 'user-onboardings', 'name' => 'Zulu', 'filters' => ['status' => 'approved']]);
        $theirA = FilterPreset::create(['admin_id' => $other->id, 'context' => 'user-onboardings', 'name' => 'Able', 'filters' => ['status' => 'rejected']]);
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'Mine', 'filters' => ['status' => 'approved']]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.reset-order', ['context' => 'user-onboardings']))
            ->assertSessionHas('success');

        // Other admin's Zulu-before-Able order is untouched.
        $this->assertSame(['Zulu', 'Able'], FilterPreset::ownedBy($other->id, 'user-onboardings')->pluck('name')->all());
        $this->assertLessThan($theirA->refresh()->position, $theirZ->refresh()->position);
    }

    public function test_reset_order_with_nothing_to_reset_reports_it(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.reset-order', ['context' => 'user-onboardings']))
            ->assertSessionHas('error');
    }

    public function test_reset_order_rejects_an_unknown_context(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.filter-presets.reset-order', ['context' => 'audit-logs']))
            ->assertNotFound();
    }

    public function test_delete_all_clears_only_this_admins_presets_for_this_page(): void
    {
        $other = Admin::create(['name' => 'Other', 'email' => 'other6@test.com', 'password' => 'x', 'is_active' => true]);

        // Two on this page for me, one on another page for me, one for someone else.
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'B', 'filters' => ['status' => 'rejected']]);
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'scheduled-emails', 'name' => 'Email', 'filters' => ['status' => 'pending']]);
        FilterPreset::create(['admin_id' => $other->id, 'context' => 'user-onboardings', 'name' => 'Theirs', 'filters' => ['status' => 'approved']]);

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->delete(route('admin.filter-presets.destroy-all', ['context' => 'user-onboardings']))
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        // My onboarding views are gone; my email view and their view remain.
        $this->assertDatabaseMissing('filter_presets', ['admin_id' => $this->admin->id, 'context' => 'user-onboardings']);
        $this->assertDatabaseHas('filter_presets', ['admin_id' => $this->admin->id, 'context' => 'scheduled-emails']);
        $this->assertDatabaseHas('filter_presets', ['admin_id' => $other->id, 'context' => 'user-onboardings']);
        $this->assertDatabaseCount('filter_presets', 2);
    }

    public function test_delete_all_with_nothing_to_delete_reports_it(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.filter-presets.destroy-all', ['context' => 'user-onboardings']))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('filter_presets', 0);
    }

    public function test_delete_all_rejects_an_unknown_context(): void
    {
        FilterPreset::create(['admin_id' => $this->admin->id, 'context' => 'user-onboardings', 'name' => 'A', 'filters' => ['status' => 'approved']]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.filter-presets.destroy-all', ['context' => 'audit-logs']))
            ->assertNotFound();

        $this->assertDatabaseCount('filter_presets', 1);
    }

    public function test_deleting_a_preset_removes_it(): void
    {
        $preset = FilterPreset::create([
            'admin_id' => $this->admin->id, 'context' => 'user-onboardings',
            'name' => 'Disposable', 'filters' => ['status' => 'approved'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.user-onboardings.index'))
            ->delete(route('admin.filter-presets.destroy', ['context' => 'user-onboardings', 'preset' => $preset]))
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('filter_presets', 0);
    }
}
