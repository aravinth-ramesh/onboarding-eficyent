<?php

namespace App\Services;

use App\Models\AnswerAuditLog;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;

class AnswerService
{
    /**
     * Save or update an answer, logging edits.
     */
    public function saveAnswer(
        User $user,
        UserOnboarding $onboarding,
        int $questionId,
        mixed $value,
        ?User $editedBy = null,
    ): UserAnswer {
        $editedBy = $editedBy ?? $user;

        // Normalize value for multi-select
        $normalizedValue = is_array($value) ? json_encode($value) : (string) $value;

        $existing = UserAnswer::where('user_id', $user->id)
            ->where('question_id', $questionId)
            ->where('user_onboarding_id', $onboarding->id)
            ->first();

        if ($existing) {
            $oldValue = $existing->value;

            // Only log if value actually changed
            if ($oldValue !== $normalizedValue) {
                AnswerAuditLog::create([
                    'user_answer_id' => $existing->id,
                    'question_id' => $questionId,
                    'user_id' => $user->id,
                    'edited_by' => $editedBy->id,
                    'old_value' => $oldValue,
                    'new_value' => $normalizedValue,
                    'edited_at' => now(),
                ]);

                $existing->update(['value' => $normalizedValue]);
            }

            return $existing;
        }

        return UserAnswer::create([
            'user_id' => $user->id,
            'question_id' => $questionId,
            'user_onboarding_id' => $onboarding->id,
            'value' => $normalizedValue,
        ]);
    }

    /**
     * Save multiple answers at once.
     */
    public function saveBulkAnswers(
        User $user,
        UserOnboarding $onboarding,
        array $answers,
        ?User $editedBy = null,
    ): array {
        $saved = [];

        foreach ($answers as $answer) {
            $saved[] = $this->saveAnswer(
                $user,
                $onboarding,
                $answer['question_id'],
                $answer['value'],
                $editedBy,
            );
        }

        return $saved;
    }
}
