<?php

use App\Models\OnboardingStep;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionTypeMapping;
use App\Models\UserType;
use Illuminate\Database\Migrations\Migration;

/**
 * Upgrades an existing (already-seeded) database to the KYB section flow:
 * adds the Industry / Address / Signatories groups and reconfigures the
 * onboarding steps. Idempotent. No-op on a fresh database — the seeder builds
 * the KYB flow there. Existing in-progress onboardings keep their copied
 * steps; only new onboardings pick up the new flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fi = UserType::where('slug', 'financial-institution')->first();
        $corp = UserType::where('slug', 'corporate')->first();

        // Fresh DB (not yet seeded) — the seeder owns the KYB flow.
        if (!$fi || !$corp) {
            return;
        }

        $this->ensureGroup('industry-classification', 'Industry Classification', 'Tell us about your business sector.', 12, $fi, $corp, [
            ['label' => 'Industry Classification (MCC)', 'type' => 'mcc', 'is_required' => true, 'order' => 1, 'description' => 'Select the Merchant Category Code that best describes your primary business activity.'],
        ]);

        $this->ensureGroup('business-addresses', 'Business Addresses', 'Your registered and operating addresses.', 13, $fi, $corp, [
            ['label' => 'Registered Address', 'type' => 'address', 'is_required' => true, 'order' => 1, 'description' => 'The official registered address of the company.'],
            ['label' => 'Operating Address (if different)', 'type' => 'address', 'is_required' => false, 'order' => 2, 'description' => 'Leave blank if the same as the registered address.'],
        ]);

        $this->ensureGroup('signatories', 'Authorized Signatories', 'Individuals authorized to act on behalf of the company.', 14, $fi, $corp, [
            ['label' => 'Authorized Signatories', 'type' => 'table', 'is_required' => true, 'order' => 1, 'description' => 'List individuals authorized to sign on behalf of the company.', 'options' => ['columns' => [
                ['key' => 'full_name', 'label' => 'Full Name', 'type' => 'text', 'required' => true, 'placeholder' => ''],
                ['key' => 'position', 'label' => 'Position', 'type' => 'text', 'required' => true, 'placeholder' => ''],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => true, 'placeholder' => ''],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => false, 'placeholder' => ''],
                ['key' => 'id_number', 'label' => 'ID / Passport Number', 'type' => 'text', 'required' => true, 'placeholder' => ''],
            ], 'min_rows' => 1, 'max_rows' => 10, 'allow_add_rows' => true]],
        ]);

        // Add the beneficial-owner module to the existing ownership group.
        $ownership = QuestionGroup::where('slug', 'ownership-structure')->first();
        if ($ownership && !Question::where('question_group_id', $ownership->id)->where('type', 'ubo')->exists()) {
            $q = Question::create([
                'question_group_id' => $ownership->id, 'label' => 'Ultimate Beneficial Owners',
                'type' => 'ubo', 'is_required' => true, 'is_active' => true, 'order' => 0,
                'description' => 'List all individuals who ultimately own or control 25% or more of the company.',
            ]);
            QuestionTypeMapping::create(['question_id' => $q->id, 'user_type_id' => $fi->id, 'order' => 0]);
            QuestionTypeMapping::create(['question_id' => $q->id, 'user_type_id' => $corp->id, 'order' => 0]);
        }

        // Reconfigure steps only if not already on the KYB flow.
        if (!OnboardingStep::where('slug', 'basic-info')->exists()) {
            $this->rebuildSteps();
        }
    }

    private function ensureGroup(string $slug, string $name, string $desc, int $order, UserType $fi, UserType $corp, array $questions): void
    {
        if (QuestionGroup::where('slug', $slug)->exists()) {
            return;
        }

        $group = QuestionGroup::create(['name' => $name, 'slug' => $slug, 'description' => $desc, 'order' => $order, 'is_active' => true]);

        foreach ($questions as $data) {
            $question = Question::create(array_merge(['question_group_id' => $group->id, 'is_active' => true, 'is_required' => false], $data));
            QuestionTypeMapping::create(['question_id' => $question->id, 'user_type_id' => $fi->id, 'order' => $question->order]);
            QuestionTypeMapping::create(['question_id' => $question->id, 'user_type_id' => $corp->id, 'order' => $question->order]);
        }
    }

    private function rebuildSteps(): void
    {
        // The KYB sections replace the old generic "questions"/"kyc" steps.
        OnboardingStep::whereIn('slug', ['questions', 'kyc'])->get()->each->forceDelete();

        $sections = [
            ['name' => 'Basic Info', 'slug' => 'basic-info', 'groups' => ['company-information']],
            ['name' => 'Industry', 'slug' => 'industry', 'groups' => ['industry-classification', 'business-model-service-structure']],
            ['name' => 'Address', 'slug' => 'address', 'groups' => ['business-addresses']],
            ['name' => 'Financial', 'slug' => 'financial', 'groups' => ['transaction-profile', 'primary-bank-account-information', 'licensing-registration-regulatory-oversight']],
            ['name' => 'UBOs', 'slug' => 'ubos', 'groups' => ['ownership-structure']],
            ['name' => 'Signatories', 'slug' => 'signatories-step', 'groups' => ['signatories']],
            ['name' => 'Documents', 'slug' => 'documents', 'groups' => ['required-attachments-checklist']],
            ['name' => 'AML/CFT', 'slug' => 'aml-cft', 'groups' => ['amlctf-framework', 'high-risk-factors', 'legal-enforcement-litigation-history', 'third-parties-outsourced-functions']],
        ];

        $order = 3; // after select_type (1) and registration (2)
        foreach ($sections as $section) {
            OnboardingStep::updateOrCreate(
                ['slug' => $section['slug']],
                ['name' => $section['name'], 'description' => null, 'component_key' => 'questions', 'order' => $order++, 'config' => ['groups' => $section['groups']], 'is_active' => true]
            );
        }

        OnboardingStep::where('slug', 'review')->update(['order' => $order]);
    }

    public function down(): void
    {
        // Non-destructive: leave the KYB groups/steps in place.
    }
};
