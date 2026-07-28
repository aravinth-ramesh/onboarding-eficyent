<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserOnboarding extends Model
{
    protected $fillable = [
        'user_id',
        'user_type_id',
        'user_type_subcategory_id',
        'country_code',
        'registration_details',
        'status',
        'current_step_id',
        'template_version',
        'started_at',
        'completed_at',
        'decided_at',
        'decided_by',
        'decision_comment',
        'approval_state',
        'submitted_for_approval_by',
        'submitted_for_approval_at',
        'assigned_to',
        'reopened_at',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'registration_details' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'decided_at' => 'datetime',
            'submitted_for_approval_at' => 'datetime',
            'reopened_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** The reviewer (maker) who submitted this application for approval. */
    public function submittedForApprovalBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'submitted_for_approval_by');
    }

    /** Handed off by a reviewer and awaiting a second person's decision. */
    public function isAwaitingApproval(): bool
    {
        return in_array($this->approval_state, ['pending_approval', 'escalated'], true);
    }

    public function isEscalated(): bool
    {
        return $this->approval_state === 'escalated';
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'archived_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    /**
     * Restrict a query to the onboardings an admin may see. Analysts see only
     * companies assigned to them; every other role sees all.
     */
    public function scopeVisibleTo($query, Admin $admin)
    {
        return $query->when(
            $admin->seesOnlyAssignedOnboardings(),
            fn ($q) => $q->where('assigned_to', $admin->id),
        );
    }

    /** Whether the given admin is allowed to open this specific onboarding. */
    public function isVisibleTo(Admin $admin): bool
    {
        return ! $admin->seesOnlyAssignedOnboardings() || $this->assigned_to === $admin->id;
    }

    public function reviewLogs(): HasMany
    {
        return $this->hasMany(OnboardingReviewLog::class)->orderBy('created_at')->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OnboardingNote::class)->latest()->latest('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OnboardingMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(OnboardingCollaborator::class);
    }

    /**
     * Human-facing reference, e.g. ONB-2026-0042. Must stay in sync with
     * formatReference() in frontend/src/components/layout/AppLayout.js —
     * clients quote this number from both the portal and emails.
     */
    public function getReferenceAttribute(): string
    {
        $padded = str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
        $year = $this->started_at?->format('Y');

        return $year ? "ONB-{$year}-{$padded}" : "ONB-{$padded}";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(UserTypeSubcategory::class, 'user_type_subcategory_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(UserOnboardingStep::class)->orderBy('order');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(UserOnboardingStep::class, 'current_step_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }

    /** Reviewer-side per-section progress markers for this application. */
    public function sectionReviews(): HasMany
    {
        return $this->hasMany(OnboardingSectionReview::class);
    }

    /**
     * The sections (QuestionGroups) this application actually contains, in
     * display order, each paired with its saved review marker (or null). This
     * is the reviewer's checklist — built from the answers on record so it only
     * ever lists sections the client filled in.
     *
     * @return \Illuminate\Support\Collection<int, object{group: QuestionGroup, review: ?OnboardingSectionReview}>
     */
    public function reviewSections(): \Illuminate\Support\Collection
    {
        $reviews = $this->sectionReviews->keyBy('question_group_id');

        return $this->answers
            ->filter(fn ($a) => $a->question && $a->question->group)
            ->map(fn ($a) => $a->question->group)
            ->unique('id')
            ->sortBy('order')
            ->values()
            ->map(fn ($group) => (object) [
                'group' => $group,
                'review' => $reviews->get($group->id),
            ]);
    }

    /**
     * Review progress as {done, total, complete}. `complete` means every
     * section has been marked reviewed — the gate for a final decision.
     *
     * @return array{done: int, total: int, complete: bool}
     */
    public function sectionReviewProgress(): array
    {
        $sections = $this->reviewSections();
        $total = $sections->count();
        $done = $sections->filter(fn ($s) => $s->review?->status === 'completed')->count();

        return [
            'done' => $done,
            'total' => $total,
            'complete' => $total > 0 && $done === $total,
        ];
    }
}
