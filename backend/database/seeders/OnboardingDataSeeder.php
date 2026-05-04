<?php

namespace Database\Seeders;

use App\Models\OnboardingStep;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionTypeMapping;
use App\Models\UserType;
use App\Models\UserTypeSubcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class OnboardingDataSeeder extends Seeder
{
    private UserType $fi;
    private UserType $corp;
    private UserTypeSubcategory $bank;
    private UserTypeSubcategory $nbfc;
    private UserTypeSubcategory $insurance;

    private const TYPE_MAP = [
        'text' => 'text',
        'date' => 'date',
        'radio' => 'radio',
        'checkbox' => 'multi_select',
        'file' => 'file',
        'table' => 'table',
        'select' => 'select',
        'multi_select' => 'multi_select',
        'textarea' => 'textarea',
        'number' => 'number',
    ];

    public function run(): void
    {
        $this->seedUserTypes();
        $this->seedQuestionGroups();
        $this->seedOnboardingSteps();
    }

    private function seedUserTypes(): void
    {
        $this->fi = UserType::create([
            'name' => 'Financial Institution',
            'slug' => 'financial-institution',
            'description' => 'Banks, NBFCs, Insurance companies, and other regulated financial entities.',
            'has_subcategories' => true,
            'order' => 1,
        ]);

        $this->bank = UserTypeSubcategory::create([
            'user_type_id' => $this->fi->id,
            'name' => 'Bank',
            'slug' => 'bank',
            'description' => 'Commercial and retail banking institutions.',
            'order' => 1,
        ]);

        $this->nbfc = UserTypeSubcategory::create([
            'user_type_id' => $this->fi->id,
            'name' => 'NBFC',
            'slug' => 'nbfc',
            'description' => 'Non-Banking Financial Companies.',
            'order' => 2,
        ]);

        $this->insurance = UserTypeSubcategory::create([
            'user_type_id' => $this->fi->id,
            'name' => 'Insurance',
            'slug' => 'insurance',
            'description' => 'Insurance companies and underwriters.',
            'order' => 3,
        ]);

        $this->corp = UserType::create([
            'name' => 'Corporate',
            'slug' => 'corporate',
            'description' => 'Corporate entities, startups, and businesses.',
            'has_subcategories' => false,
            'order' => 2,
        ]);
    }

    private function seedQuestionGroups(): void
    {
        $groupRows = $this->loadJsonRows(public_path('onboarding/question_groups.json'));
        $questionRows = $this->loadJsonRows(public_path('onboarding/questions.json'));
        $optionRows = $this->loadJsonRows(public_path('onboarding/question_options.json'));

        $groupMap = $this->seedGroupsFromRows($groupRows);
        $questionMap = $this->seedQuestionsFromRows($questionRows, $groupMap);
        $this->attachOptionsFromRows($optionRows, $questionMap);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadJsonRows(string $path): array
    {
        $payload = json_decode(File::get($path), true);

        foreach ($payload as $entry) {
            if (($entry['type'] ?? null) === 'table' && isset($entry['data'])) {
                return $entry['data'];
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, QuestionGroup>  legacyId => QuestionGroup
     */
    private function seedGroupsFromRows(array $rows): array
    {
        $usedSlugs = [];
        $map = [];

        foreach ($rows as $row) {
            $name = $row['title'];
            $slug = Str::slug($name);
            $base = $slug;
            $i = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = $base.'-'.$i++;
            }
            $usedSlugs[$slug] = true;

            $group = QuestionGroup::create([
                'name' => $name,
                'slug' => $slug,
                'description' => $row['description'] ?? null,
                'order' => (int) $row['order'],
                'is_active' => ($row['status'] ?? '1') === '1',
            ]);

            $map[(int) $row['id']] = $group;
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, QuestionGroup>  $groupMap
     * @return array<int, Question>  legacyId => Question
     */
    private function seedQuestionsFromRows(array $rows, array $groupMap): array
    {
        $map = [];

        foreach ($rows as $row) {
            $legacyGroupId = (int) $row['question_group_id'];
            if (! isset($groupMap[$legacyGroupId])) {
                continue;
            }

            $group = $groupMap[$legacyGroupId];
            $jsonType = $row['type'];
            $type = self::TYPE_MAP[$jsonType] ?? 'text';

            $validationRules = null;
            if (! empty($row['meta'])) {
                $decoded = json_decode($row['meta'], true);
                if (is_array($decoded)) {
                    $validationRules = $decoded;
                }
            }

            $question = $this->createQuestion($group, [
                'label' => $row['title'],
                'description' => $row['description'] ?? null,
                'type' => $type,
                'is_required' => ($row['required'] ?? '0') === '1',
                'order' => (int) $row['order'],
                'validation_rules' => $validationRules,
                'is_active' => ($row['status'] ?? '1') === '1',
            ]);

            $this->mapToAll($question);

            $map[(int) $row['id']] = $question;
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, Question>  $questionMap
     */
    private function attachOptionsFromRows(array $rows, array $questionMap): void
    {
        $grouped = [];
        foreach ($rows as $row) {
            $legacyQid = (int) $row['question_id'];
            if (! isset($questionMap[$legacyQid])) {
                continue;
            }
            $grouped[$legacyQid][] = $row;
        }

        foreach ($grouped as $legacyQid => $optionRows) {
            usort($optionRows, fn ($a, $b) => (int) $a['order'] <=> (int) $b['order']);

            $options = array_map(fn ($o) => [
                'label' => $o['label'],
                'value' => $o['value'],
            ], $optionRows);

            $questionMap[$legacyQid]->update(['options' => $options]);
        }
    }

    private function seedOnboardingSteps(): void
    {
        OnboardingStep::create(['name' => 'Select Type', 'slug' => 'select-type', 'description' => 'Choose your organization type.', 'component_key' => 'select_type', 'order' => 1]);
        OnboardingStep::create(['name' => 'Questions', 'slug' => 'questions', 'description' => 'Answer onboarding questions.', 'component_key' => 'questions', 'order' => 2]);
        OnboardingStep::create(['name' => 'KYC', 'slug' => 'kyc', 'description' => 'Upload KYC documents.', 'component_key' => 'kyc', 'order' => 3]);
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'description' => 'Review and submit your application.', 'component_key' => 'review', 'order' => 4]);
    }

    private function createQuestion(QuestionGroup $group, array $data): Question
    {
        return Question::create(array_merge([
            'question_group_id' => $group->id,
            'is_required' => false,
            'is_active' => true,
        ], $data));
    }

    private function mapToAll(Question $question): void
    {
        QuestionTypeMapping::create(['question_id' => $question->id, 'user_type_id' => $this->fi->id, 'order' => $question->order]);
        QuestionTypeMapping::create(['question_id' => $question->id, 'user_type_id' => $this->corp->id, 'order' => $question->order]);
    }
}
