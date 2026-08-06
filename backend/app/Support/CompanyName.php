<?php

namespace App\Support;

use App\Models\UserAnswer;
use App\Models\UserOnboarding;

/**
 * Resolves the company/entity name for an application from its answers, and
 * keeps the denormalised user_onboardings.company_name column in sync. Admin
 * lists show this name rather than the person who registered the account.
 */
class CompanyName
{
    /** The company name from the application's answers, or null if none yet. */
    public static function resolve(UserOnboarding $onboarding): ?string
    {
        $patterns = array_map('strtolower', config('onboarding.company_name_labels', []));
        if ($patterns === []) {
            return null;
        }

        $answers = UserAnswer::where('user_onboarding_id', $onboarding->id)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->with('question:id,label,type')
            ->get();

        $best = null;
        $bestRank = PHP_INT_MAX;

        foreach ($answers as $answer) {
            $question = $answer->question;
            // Only plain text answers carry a name; skip files/tables/etc.
            if (! $question || $question->type !== 'text') {
                continue;
            }
            $value = trim((string) $answer->value);
            if ($value === '') {
                continue;
            }

            $label = strtolower(trim((string) $question->label));
            foreach ($patterns as $rank => $pattern) {
                if ($rank < $bestRank && str_contains($label, $pattern)) {
                    $bestRank = $rank;
                    $best = $value;
                    break;
                }
            }
        }

        return $best;
    }

    /**
     * Recompute and persist company_name if it changed. Best-effort: this is a
     * denormalisation convenience for admin lists, so it must never break the
     * client's ability to save answers or advance a step (e.g. if the column
     * is missing because a migration hasn't run, or a value is malformed).
     */
    public static function sync(UserOnboarding $onboarding): void
    {
        try {
            $name = self::resolve($onboarding);

            if ($name !== $onboarding->company_name) {
                $onboarding->forceFill(['company_name' => $name])->save();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
