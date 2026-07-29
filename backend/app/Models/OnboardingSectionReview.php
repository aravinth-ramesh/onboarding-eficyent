<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reviewer's progress marker for a single section (QuestionGroup) of an
 * application. See the create migration for why this is separate from the
 * client's step progress.
 */
class OnboardingSectionReview extends Model
{
    public const STATUSES = ['pending', 'in_progress', 'completed'];

    protected $fillable = [
        'user_onboarding_id',
        'question_group_id',
        'status',
        'reviewed_by',
        'note',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(UserOnboarding::class, 'user_onboarding_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
