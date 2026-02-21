<?php

namespace Database\Seeders;

use App\Models\OnboardingStep;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionTypeMapping;
use App\Models\User;
use App\Models\UserType;
use App\Models\UserTypeSubcategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        // --------------------------------------------------
        // User Types
        // --------------------------------------------------
        $fi = UserType::create([
            'name' => 'Financial Institution',
            'slug' => 'financial-institution',
            'description' => 'Banks, NBFCs, Insurance companies, etc.',
            'has_subcategories' => true,
            'order' => 1,
        ]);

        UserTypeSubcategory::create([
            'user_type_id' => $fi->id,
            'name' => 'Bank',
            'slug' => 'bank',
            'order' => 1,
        ]);
        UserTypeSubcategory::create([
            'user_type_id' => $fi->id,
            'name' => 'NBFC',
            'slug' => 'nbfc',
            'order' => 2,
        ]);
        UserTypeSubcategory::create([
            'user_type_id' => $fi->id,
            'name' => 'Insurance',
            'slug' => 'insurance',
            'order' => 3,
        ]);

        $corp = UserType::create([
            'name' => 'Corporate',
            'slug' => 'corporate',
            'description' => 'Corporate entities and businesses.',
            'has_subcategories' => false,
            'order' => 2,
        ]);

        // --------------------------------------------------
        // Question Groups
        // --------------------------------------------------
        $basicInfo = QuestionGroup::create([
            'name' => 'Basic Information',
            'slug' => 'basic-information',
            'order' => 1,
        ]);

        $companyDetails = QuestionGroup::create([
            'name' => 'Company Details',
            'slug' => 'company-details',
            'order' => 2,
        ]);

        $compliance = QuestionGroup::create([
            'name' => 'Compliance',
            'slug' => 'compliance',
            'order' => 3,
        ]);

        // --------------------------------------------------
        // Questions
        // --------------------------------------------------
        $q1 = Question::create([
            'question_group_id' => $basicInfo->id,
            'label' => 'Company Name',
            'type' => 'text',
            'is_required' => true,
            'order' => 1,
            'placeholder' => 'Enter your company name',
        ]);

        $q2 = Question::create([
            'question_group_id' => $basicInfo->id,
            'label' => 'Date of Incorporation',
            'type' => 'date',
            'is_required' => true,
            'order' => 2,
        ]);

        $q3 = Question::create([
            'question_group_id' => $basicInfo->id,
            'label' => 'Country of Incorporation',
            'type' => 'select',
            'is_required' => true,
            'order' => 3,
            'options' => [
                ['label' => 'India', 'value' => 'IN'],
                ['label' => 'United States', 'value' => 'US'],
                ['label' => 'United Kingdom', 'value' => 'UK'],
                ['label' => 'Singapore', 'value' => 'SG'],
                ['label' => 'Other', 'value' => 'other'],
            ],
        ]);

        $q4 = Question::create([
            'question_group_id' => $companyDetails->id,
            'label' => 'Number of Employees',
            'type' => 'radio',
            'is_required' => true,
            'order' => 1,
            'options' => [
                ['label' => '1-50', 'value' => '1-50'],
                ['label' => '51-200', 'value' => '51-200'],
                ['label' => '201-500', 'value' => '201-500'],
                ['label' => '500+', 'value' => '500+'],
            ],
        ]);

        $q5 = Question::create([
            'question_group_id' => $companyDetails->id,
            'label' => 'Annual Revenue (USD)',
            'type' => 'number',
            'is_required' => false,
            'order' => 2,
            'placeholder' => 'Enter approximate annual revenue',
        ]);

        $q6 = Question::create([
            'question_group_id' => $companyDetails->id,
            'label' => 'Services Required',
            'type' => 'multi_select',
            'is_required' => true,
            'order' => 3,
            'options' => [
                ['label' => 'Payment Processing', 'value' => 'payment'],
                ['label' => 'Lending', 'value' => 'lending'],
                ['label' => 'Investment', 'value' => 'investment'],
                ['label' => 'Insurance', 'value' => 'insurance'],
                ['label' => 'Advisory', 'value' => 'advisory'],
            ],
        ]);

        $q7 = Question::create([
            'question_group_id' => $compliance->id,
            'label' => 'Are you regulated by any financial authority?',
            'type' => 'radio',
            'is_required' => true,
            'order' => 1,
            'options' => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No', 'value' => 'no'],
            ],
        ]);

        $q8 = Question::create([
            'question_group_id' => $compliance->id,
            'label' => 'Name of Regulatory Authority',
            'type' => 'text',
            'is_required' => true,
            'order' => 2,
            'placeholder' => 'e.g., RBI, SEC, FCA',
            'help_text' => 'This field is required if you are regulated.',
        ]);

        // Map questions to user types (both FI and Corporate get basic + company questions)
        foreach ([$fi->id, $corp->id] as $typeId) {
            foreach ([$q1, $q2, $q3, $q4, $q5, $q6] as $question) {
                QuestionTypeMapping::create([
                    'question_id' => $question->id,
                    'user_type_id' => $typeId,
                    'order' => $question->order,
                ]);
            }
        }

        // Compliance questions only for Financial Institution
        foreach ([$q7, $q8] as $question) {
            QuestionTypeMapping::create([
                'question_id' => $question->id,
                'user_type_id' => $fi->id,
                'order' => $question->order,
            ]);
        }

        // Conditional rule: Show "Name of Regulatory Authority" only if "regulated" = "yes"
        $q8->conditionalRules()->create([
            'parent_question_id' => $q7->id,
            'comparison_type' => 'equals',
            'trigger_value' => 'yes',
            'action' => 'show',
        ]);

        // --------------------------------------------------
        // Onboarding Steps (Master Template)
        // --------------------------------------------------
        OnboardingStep::create([
            'name' => 'Select Type',
            'slug' => 'select-type',
            'description' => 'Choose your organization type.',
            'component_key' => 'select_type',
            'order' => 1,
        ]);

        OnboardingStep::create([
            'name' => 'Questions',
            'slug' => 'questions',
            'description' => 'Answer onboarding questions.',
            'component_key' => 'questions',
            'order' => 2,
        ]);

        OnboardingStep::create([
            'name' => 'KYC',
            'slug' => 'kyc',
            'description' => 'Upload KYC documents.',
            'component_key' => 'kyc',
            'order' => 3,
        ]);

        OnboardingStep::create([
            'name' => 'Review',
            'slug' => 'review',
            'description' => 'Review and submit your application.',
            'component_key' => 'review',
            'order' => 4,
        ]);
    }
}
