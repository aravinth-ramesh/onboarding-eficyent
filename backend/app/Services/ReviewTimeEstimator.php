<?php

namespace App\Services;

use App\Models\OnboardingReviewLog;
use Illuminate\Support\Collection;

/**
 * Estimates how long a review takes, from the immutable review log: each
 * approve/reject event is paired with the submission (or resubmission) that
 * preceded it. The client-facing estimate uses the median of the last 30
 * days — robust against one slow outlier — and falls back to a configured
 * default until there are enough samples to be honest about.
 */
class ReviewTimeEstimator
{
    /**
     * Hours between each submission and its decision.
     *
     * @return Collection<int, float>
     */
    public function pairedDecisionHours(\DateTimeInterface $since): Collection
    {
        $decisions = OnboardingReviewLog::whereIn('event', ['approved', 'rejected'])
            ->where('created_at', '>=', $since)
            ->get();

        $submissions = OnboardingReviewLog::whereIn('event', ['submitted', 'resubmitted'])
            ->whereIn('user_onboarding_id', $decisions->pluck('user_onboarding_id'))
            ->get()
            ->groupBy('user_onboarding_id');

        return $decisions
            ->map(function ($decision) use ($submissions) {
                $submission = $submissions->get($decision->user_onboarding_id, collect())
                    ->where('created_at', '<=', $decision->created_at)
                    ->sortByDesc('created_at')
                    ->first();

                return $submission
                    ? $submission->created_at->diffInSeconds($decision->created_at) / 3600
                    : null;
            })
            ->filter(fn ($hours) => $hours !== null)
            ->values();
    }

    /** Median hours over the recent window, or the configured fallback. */
    public function estimateHours(): float
    {
        $samples = $this->pairedDecisionHours(now()->subDays(30))->sort()->values();

        if ($samples->count() < (int) config('onboarding.review_estimate.min_samples', 3)) {
            return (float) config('onboarding.review_estimate.fallback_hours', 48);
        }

        $mid = intdiv($samples->count(), 2);

        return $samples->count() % 2 === 1
            ? $samples[$mid]
            : ($samples[$mid - 1] + $samples[$mid]) / 2;
    }

    /** Client-friendly phrasing of the estimate. */
    public function estimateLabel(): string
    {
        $hours = $this->estimateHours();

        if ($hours <= 24) {
            return 'Typically decided within a day';
        }

        $days = max(1, (int) ceil($hours / 24));

        return "Typically decided within {$days} days";
    }
}
