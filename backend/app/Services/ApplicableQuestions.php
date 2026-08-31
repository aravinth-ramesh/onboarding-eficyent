<?php

namespace App\Services;

use App\Models\QuestionTypeMapping;
use App\Models\UserOnboarding;
use Illuminate\Support\Collection;

/**
 * The questions that apply to one application: mapped to its type (and
 * subcategory), still active, and belonging to an active group.
 *
 * Extracted so the client form and the submission check resolve the same set
 * from one place. Two copies of this query would drift, which is exactly how
 * the two conditional-rule engines came to disagree.
 */
class ApplicableQuestions
{
    /** @return Collection<int, \App\Models\Question> keyed by question id */
    public static function for(UserOnboarding $onboarding): Collection
    {
        if (! $onboarding->user_type_id) {
            return collect();
        }

        return QuestionTypeMapping::where('user_type_id', $onboarding->user_type_id)
            ->where(function ($query) use ($onboarding) {
                $query->whereNull('user_type_subcategory_id');
                if ($onboarding->user_type_subcategory_id) {
                    $query->orWhere('user_type_subcategory_id', $onboarding->user_type_subcategory_id);
                }
            })
            ->where('is_active', true)
            ->with(['question.group', 'question.conditionalRules' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('order')
            ->get()
            // One mapping per question; a subcategory-specific one wins.
            ->unique(fn ($m) => $m->question_id)
            ->filter(fn ($m) => $m->question && $m->question->is_active
                && $m->question->group && $m->question->group->is_active)
            ->mapWithKeys(fn ($m) => [$m->question_id => $m->question]);
    }

    /**
     * The answers keyed the way the rule engines expect: by question id, plus
     * the virtual fields a rule may key on instead.
     *
     * @return array<int|string, mixed>
     */
    public static function answerMap(UserOnboarding $onboarding): array
    {
        $answers = $onboarding->answers()->get()
            ->mapWithKeys(fn ($a) => [$a->question_id => $a->value])
            ->all();

        // Registration-step values a rule can key on via parent_field.
        $answers['country_code'] = $onboarding->country_code;

        return $answers;
    }
}
