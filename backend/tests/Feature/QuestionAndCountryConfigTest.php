<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\CountryRegistration;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionTypeMapping;
use App\Models\UserType;
use App\Services\CountryRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requiredness must survive a round trip through the admin form (item 14), and
 * a field an admin adds to a country must not wipe that country's defaults
 * (item 15).
 */
class QuestionAndCountryConfigTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Ops', 'email' => 'ops@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::SuperAdmin,
        ]);
    }

    public function test_turning_mandatory_off_and_on_again_restores_it(): void
    {
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g', 'order' => 1, 'is_active' => true]);
        $type = UserType::create(['name' => 'Corporate', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Legal name', 'type' => 'text',
            'is_required' => true, 'order' => 1, 'is_active' => true,
        ]);
        // Seeded the way the data seeder does it: null = inherit the question.
        QuestionTypeMapping::create([
            'question_id' => $question->id, 'user_type_id' => $type->id,
            'is_required' => null, 'order' => 1, 'is_active' => true,
        ]);

        $payload = fn (bool $required) => [
            'question_group_id' => $group->id, 'label' => 'Legal name', 'type' => 'text',
            'order' => 1, 'is_active' => 1,
            'is_required' => $required ? 1 : 0,
            'mappings' => [['user_type_id' => $type->id, 'is_required' => $required ? 1 : 0]],
        ];

        $admin = $this->admin();

        // Off...
        $this->actingAs($admin, 'admin')
            ->put(route('admin.questions.update', $question), $payload(false));
        $this->assertFalse((bool) $question->fresh()->is_required);

        // ...and back on. This is where it used to stick: the mapping had been
        // frozen at 0, and `0 ?? true` is 0.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.questions.update', $question), $payload(true));

        $question->refresh();
        $mapping = QuestionTypeMapping::where('question_id', $question->id)->first();

        $this->assertTrue((bool) $question->is_required, 'the question is mandatory again');
        $this->assertNull($mapping->is_required, 'the mapping defers to it rather than pinning the old value');
        $this->assertTrue(
            (bool) ($mapping->is_required ?? $question->is_required),
            'so the client sees it as mandatory',
        );
    }

    public function test_a_genuine_per_type_override_is_still_stored(): void
    {
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g2', 'order' => 1, 'is_active' => true]);
        $type = UserType::create(['name' => 'FI', 'slug' => 'fi', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Licence', 'type' => 'text',
            'is_required' => false, 'order' => 1, 'is_active' => true,
        ]);

        // Question optional, but required for this one type.
        $this->actingAs($this->admin(), 'admin')->put(route('admin.questions.update', $question), [
            'question_group_id' => $group->id, 'label' => 'Licence', 'type' => 'text',
            'order' => 1, 'is_active' => 1, 'is_required' => 0,
            'mappings' => [['user_type_id' => $type->id, 'is_required' => 1]],
        ]);

        $mapping = QuestionTypeMapping::where('question_id', $question->id)->first();
        $this->assertTrue((bool) $mapping->is_required, 'the override differs from the question, so it is kept');
    }

    public function test_an_update_without_mappings_does_not_unmap_the_question(): void
    {
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g3', 'order' => 1, 'is_active' => true]);
        $type = UserType::create(['name' => 'Corporate', 'slug' => 'corp3', 'order' => 1, 'is_active' => true]);
        $question = Question::create([
            'question_group_id' => $group->id, 'label' => 'Legal name', 'type' => 'text',
            'is_required' => true, 'order' => 1, 'is_active' => true,
        ]);
        QuestionTypeMapping::create([
            'question_id' => $question->id, 'user_type_id' => $type->id,
            'is_required' => null, 'order' => 1, 'is_active' => true,
        ]);

        $this->actingAs($this->admin(), 'admin')->put(route('admin.questions.update', $question), [
            'question_group_id' => $group->id, 'label' => 'Renamed', 'type' => 'text',
            'order' => 1, 'is_active' => 1, 'is_required' => 1,
        ]);

        $this->assertSame(
            1,
            QuestionTypeMapping::where('question_id', $question->id)->count(),
            'an update that sends no mappings must not remove the question from every user type',
        );
    }

    public function test_a_field_added_to_an_unconfigured_country_adds_to_the_defaults(): void
    {
        $this->seed(\Database\Seeders\CountryRegistrationSeeder::class);

        $service = app(CountryRegistrationService::class);
        $before = collect($service->fieldsFor('FR', 'corporate'))->pluck('key');
        $this->assertGreaterThan(0, $before->count(), 'an unconfigured country starts on the generic defaults');

        CountryRegistration::create([
            'country_code' => 'FR', 'field_key' => 'siren', 'label' => 'SIREN',
            'is_required' => true, 'order' => 10, 'is_active' => true,
        ]);

        $after = collect($service->fieldsFor('FR', 'corporate'))->pluck('key');

        $this->assertTrue($after->contains('siren'), 'the added field shows');
        foreach ($before as $key) {
            $this->assertTrue($after->contains($key), "the default field {$key} must still show");
        }
    }

    public function test_a_configured_country_still_replaces_the_generic_defaults(): void
    {
        // GB's "crn" IS the company registration number — showing the generic
        // placeholder alongside it would ask for the same thing twice.
        $this->seed(\Database\Seeders\CountryRegistrationSeeder::class);

        $keys = collect(app(CountryRegistrationService::class)->fieldsFor('GB', 'corporate'))->pluck('key');

        $this->assertTrue($keys->contains('crn'));
        $this->assertFalse($keys->contains('company_reg_no'), 'the generic placeholder stays out');
    }
}
