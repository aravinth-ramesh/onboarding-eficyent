<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Mail\OnboardingAssignedMail;
use App\Mail\OnboardingDecisionMail;
use App\Mail\OnboardingEscalatedMail;
use App\Mail\OnboardingSubmittedAdminMail;
use App\Mail\OnboardingSubmittedClientMail;
use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use Illuminate\Support\Facades\Mail;

class OnboardingService
{
    /**
     * Initialize onboarding for a user by copying master template steps.
     */
    public function initializeForUser(User $user): UserOnboarding
    {
        $onboarding = UserOnboarding::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'template_version' => $this->getCurrentTemplateVersion(),
            'started_at' => now(),
        ]);

        $masterSteps = OnboardingStep::where('is_active', true)
            ->orderBy('order')
            ->get();

        foreach ($masterSteps as $step) {
            UserOnboardingStep::create([
                'user_onboarding_id' => $onboarding->id,
                'onboarding_step_id' => $step->id,
                'name' => $step->name,
                'component_key' => $step->component_key,
                'order' => $step->order,
                'status' => 'pending',
                'config' => $step->config,
            ]);
        }

        // Set current step to first step
        $firstStep = $onboarding->steps()->orderBy('order')->first();
        if ($firstStep) {
            $onboarding->update(['current_step_id' => $firstStep->id]);
        }

        return $onboarding->load('steps');
    }

    /**
     * Set user type and optionally subcategory.
     */
    public function setUserType(UserOnboarding $onboarding, int $userTypeId, ?int $subcategoryId = null): UserOnboarding
    {
        $onboarding->update([
            'user_type_id' => $userTypeId,
            'user_type_subcategory_id' => $subcategoryId,
            'status' => 'in_progress',
        ]);

        return $onboarding->fresh();
    }

    /**
     * Complete a step and advance to the next one.
     */
    public function completeStep(UserOnboarding $onboarding, UserOnboardingStep $step): UserOnboarding
    {
        $step->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // An out-of-order edit started from the Final Review page returns
        // straight there rather than walking forward step by step — and must
        // not fall through to the "all steps complete" submit branch below,
        // since the later steps are already completed (EOP-52).
        $returnTo = $onboarding->return_to_step_id && $onboarding->return_to_step_id !== $step->id
            ? $onboarding->steps()->where('id', $onboarding->return_to_step_id)->first()
            : null;

        if ($returnTo) {
            $returnTo->update([
                'status' => 'in_progress',
                'completed_at' => null,
                'started_at' => $returnTo->started_at ?? now(),
            ]);
            $onboarding->update([
                'current_step_id' => $returnTo->id,
                'return_to_step_id' => null,
            ]);

            return $onboarding->fresh('steps');
        }

        // Find next pending step (skip over skipped steps)
        $nextStep = $onboarding->steps()
            ->where('order', '>', $step->order)
            ->whereNotIn('status', ['completed', 'skipped'])
            ->orderBy('order')
            ->first();

        if ($nextStep) {
            $nextStep->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
            $onboarding->update(['current_step_id' => $nextStep->id]);
        } else {
            // All steps completed
            $onboarding->update([
                'status' => 'completed',
                'completed_at' => now(),
                'current_step_id' => null,
                'return_to_step_id' => null,
            ]);

            // Lock in the company name at submission for the review queues.
            \App\Support\CompanyName::sync($onboarding->fresh());

            $onboarding->reviewLogs()->create([
                'event' => $onboarding->reopened_at ? 'resubmitted' : 'submitted',
            ]);

            $this->autoAssign($onboarding->fresh());

            $this->notifySubmission($onboarding->fresh());
        }

        return $onboarding->fresh('steps');
    }

    /**
     * Approve a submitted application. Only 'completed' (submitted, not yet
     * decided) onboardings can be approved.
     */
    public function approve(UserOnboarding $onboarding, Admin $admin, ?string $comment = null): UserOnboarding
    {
        return $this->decide($onboarding, $admin, 'approved', $comment);
    }

    /**
     * Reject a submitted application. A reason is mandatory — it is shown to
     * the client in the portal and the decision email.
     */
    public function reject(UserOnboarding $onboarding, Admin $admin, string $comment): UserOnboarding
    {
        return $this->decide($onboarding, $admin, 'rejected', $comment);
    }

    /**
     * Maker step of four-eyes: the reviewer who worked the application hands it
     * off for a second person to approve. Every section must be reviewed first
     * — the maker is attesting the review is complete.
     */
    public function submitForApproval(UserOnboarding $onboarding, Admin $admin): UserOnboarding
    {
        if ($onboarding->status !== 'completed') {
            throw new \DomainException('Only a submitted application awaiting review can be sent for approval.');
        }

        if ($onboarding->approval_state === 'pending_approval') {
            throw new \DomainException('This application has already been submitted for approval.');
        }

        $progress = $onboarding->sectionReviewProgress();
        if ($progress['total'] > 0 && ! $progress['complete']) {
            throw new \DomainException('Review every section before submitting for approval.');
        }

        $onboarding->update([
            'approval_state' => 'pending_approval',
            'submitted_for_approval_by' => $admin->id,
            'submitted_for_approval_at' => now(),
        ]);

        $onboarding->reviewLogs()->create([
            'event' => 'submitted_for_approval',
            'admin_id' => $admin->id,
        ]);

        return $onboarding->fresh();
    }

    /**
     * Refer an application to compliance for the decision. Keeps it in the
     * review queue but flags it so the compliance team can pick it up.
     */
    public function escalate(UserOnboarding $onboarding, Admin $admin, ?string $comment = null): UserOnboarding
    {
        if ($onboarding->status !== 'completed') {
            throw new \DomainException('Only a submitted application awaiting review can be escalated.');
        }

        $onboarding->update(['approval_state' => 'escalated']);

        $onboarding->reviewLogs()->create([
            'event' => 'escalated',
            'admin_id' => $admin->id,
            'comment' => $comment ?: null,
        ]);

        // Ping the compliance team — an escalation that reaches nobody is no
        // escalation. The one who escalated doesn't need their own email.
        try {
            $onboarding->loadMissing(['user', 'userType']);
            $recipients = Admin::where('is_active', true)
                ->where('role', AdminRole::Compliance->value)
                ->where('id', '!=', $admin->id)
                ->pluck('email')
                ->filter();

            foreach ($recipients as $email) {
                Mail::to($email)->queue(new OnboardingEscalatedMail($onboarding, $admin, $comment ?: null));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $onboarding->fresh();
    }

    /**
     * Discard a draft application so the owner can start over. Only drafts
     * (pending / in_progress) can be discarded — a submitted application is
     * a compliance record and stays. Deleting cascades to answers, steps,
     * messages, notes, review logs and collaborators; uploaded S3 objects
     * are retained per the existing storage policy.
     */
    public function discardDraft(User $user): void
    {
        $onboarding = $user->onboarding;

        if (! $onboarding) {
            throw new \DomainException('Only the application owner can start over.');
        }

        if (! in_array($onboarding->status, ['pending', 'in_progress'], true)) {
            throw new \DomainException('Only draft applications can be discarded — this one has already been submitted.');
        }

        $onboarding->delete();
    }

    /**
     * Reopen a rejected application so the client can fix it and resubmit.
     * All answers stay intact; the flow resumes at the review step, whose
     * Edit buttons jump back into any section. The next submission is
     * flagged as a resubmission (reopened_at) for the admin team.
     */
    public function reopen(UserOnboarding $onboarding): UserOnboarding
    {
        if ($onboarding->status !== 'rejected') {
            throw new \DomainException('Only rejected applications can be reopened for resubmission.');
        }

        // Resume at the last non-skipped step (the review step in the
        // standard flow) — everything before it stays completed. reorder()
        // clears the relation's default ascending order.
        $reviewStep = $onboarding->steps()
            ->where('status', '!=', 'skipped')
            ->reorder('order', 'desc')
            ->first();

        $reviewStep?->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'completed_at' => null,
        ]);

        $onboarding->update([
            'status' => 'in_progress',
            'completed_at' => null,
            'decided_at' => null,
            'decided_by' => null,
            'decision_comment' => null,
            'approval_state' => null,
            'submitted_for_approval_by' => null,
            'submitted_for_approval_at' => null,
            'reopened_at' => now(),
            'current_step_id' => $reviewStep?->id,
        ]);

        // Reset reviewer state so the resubmitted application is reviewed
        // fresh: prior section-review progress and per-document verdicts no
        // longer reflect the (now-being-edited) version (EOP-79, EOP-74).
        $onboarding->sectionReviews()->delete();
        \App\Models\AnswerFile::whereHas('answer', fn ($q) => $q->where('user_onboarding_id', $onboarding->id))
            ->update(['review_decision' => null, 'review_note' => null, 'reviewed_at' => null, 'reviewed_by' => null]);

        $onboarding->reviewLogs()->create(['event' => 'reopened']);

        return $onboarding->fresh('steps');
    }

    /**
     * Whether the client may no longer freely edit the application — it has
     * been submitted (completed / awaiting review) or decided (approved /
     * rejected). Editing resumes only through reopen/resubmission, which sets
     * the status back to in_progress.
     */
    private function isLockedForEditing(UserOnboarding $onboarding): bool
    {
        return in_array($onboarding->status, ['completed', 'approved', 'rejected'], true);
    }

    /**
     * May this admin take the approve/reject decision?
     *
     * Unassigned work is anyone's. Once assigned, the decision belongs to the
     * reviewer holding it — unless it has been handed off for approval or
     * escalated, which is precisely the act of inviting someone else to decide.
     * Admin / Super Admin can always step in, so absence or a stuck case is
     * never a deadlock (EOP-89).
     */
    public function canDecide(UserOnboarding $onboarding, Admin $admin): bool
    {
        if (! $onboarding->assigned_to || (int) $onboarding->assigned_to === (int) $admin->id) {
            return true;
        }

        if ($onboarding->approval_state !== null) {
            return true; // pending_approval or escalated — open to a checker
        }

        return $admin->isRole(AdminRole::Admin) || $admin->isRole(AdminRole::SuperAdmin);
    }

    private function decide(UserOnboarding $onboarding, Admin $admin, string $status, ?string $comment): UserOnboarding
    {
        if ($onboarding->status !== 'completed') {
            // Say what state it is actually in — "Only submitted applications
            // awaiting review..." left the admin guessing why (EOP-80, EOP-83).
            throw new \DomainException(sprintf(
                'This application is %s, so it cannot be %s. Only an application awaiting review can be decided.',
                strtolower($onboarding->statusLabel),
                $status === 'approved' ? 'approved' : 'rejected',
            ));
        }

        // Four-eyes: the reviewer who submitted an application for approval
        // cannot also sign it off — a second person must decide.
        if ($onboarding->submitted_for_approval_by
            && (int) $onboarding->submitted_for_approval_by === (int) $admin->id) {
            throw new \DomainException('A different reviewer must decide this — you submitted it for approval (four-eyes).');
        }

        // An assigned application belongs to its reviewer: an uninvolved admin
        // must not take a terminal, client-facing decision on someone else's
        // case. This gates approve and reject identically — rejection used to
        // be reachable when approval was not, which read as inconsistent
        // access control (EOP-89).
        if (! $this->canDecide($onboarding, $admin)) {
            throw new \DomainException('This application is assigned to another reviewer. Ask them to decide, or have it submitted for approval or escalated first.');
        }

        // An approval means the whole application checks out, so every section
        // must have been reviewed first. Rejections can happen at any point.
        if ($status === 'approved') {
            $progress = $onboarding->sectionReviewProgress();
            if ($progress['total'] > 0 && ! $progress['complete']) {
                throw new \DomainException('Every section must be reviewed before the application can be approved.');
            }
        }

        $onboarding->update([
            'status' => $status,
            'decided_at' => now(),
            'decided_by' => $admin->id,
            'decision_comment' => $comment ?: null,
            'approval_state' => null,
        ]);

        $onboarding->reviewLogs()->create([
            'event' => $status,
            'admin_id' => $admin->id,
            'comment' => $comment ?: null,
        ]);

        $onboarding = $onboarding->fresh();
        $onboarding->load(['user', 'userType']);

        try {
            if ($onboarding->user?->email && $onboarding->user->wantsEmail('decisions')) {
                Mail::to($onboarding->user->email)->queue(new OnboardingDecisionMail($onboarding));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $onboarding;
    }

    /**
     * Give an unassigned fresh submission to the active admin with the
     * fewest open (awaiting-review) assignments. Stateless least-loaded
     * balancing; resubmissions keep their existing reviewer for continuity.
     * Never blocks a submission — failures are reported and swallowed.
     */
    private function autoAssign(UserOnboarding $onboarding): void
    {
        if (! config('onboarding.auto_assign_submissions') || $onboarding->assigned_to !== null) {
            return;
        }

        try {
            // Route new work to the least-loaded active analyst (the front-line
            // review pool). If there are none, leave it for a manager to assign.
            $assignee = Admin::where('is_active', true)
                ->where('role', \App\Enums\AdminRole::Analyst->value)
                ->withCount(['assignedOnboardings as open_count' => fn ($q) => $q->where('status', 'completed')])
                ->orderBy('open_count')
                ->orderBy('id')
                ->first();

            if (! $assignee) {
                return;
            }

            $onboarding->update(['assigned_to' => $assignee->id]);

            if ($assignee->email) {
                Mail::to($assignee->email)->queue(
                    new OnboardingAssignedMail($onboarding->fresh(['user', 'userType']), null)
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Submission emails: confirmation to the client, heads-up to every
     * active admin. Queued, and a mail hiccup must never undo a submission —
     * hence the blanket catch.
     */
    private function notifySubmission(UserOnboarding $onboarding): void
    {
        $onboarding->load(['user', 'userType', 'subcategory', 'assignee']);

        try {
            if ($onboarding->user?->email && $onboarding->user->wantsEmail('submission')) {
                Mail::to($onboarding->user->email)->queue(new OnboardingSubmittedClientMail($onboarding));
            }

            // Notify the assigned reviewer if there is one; otherwise the
            // managers who distribute work — not every admin (EOP-87).
            foreach ($onboarding->notificationRecipientEmails() as $email) {
                Mail::to($email)->queue(new OnboardingSubmittedAdminMail($onboarding));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Go back to the previous step.
     */
    public function goToPreviousStep(UserOnboarding $onboarding, UserOnboardingStep $currentStep): UserOnboarding
    {
        // Locked once submitted/decided — no re-opening steps for editing.
        if ($this->isLockedForEditing($onboarding)) {
            return $onboarding->fresh('steps');
        }

        $previousStep = $onboarding->steps()
            ->where('order', '<', $currentStep->order)
            ->where('status', '!=', 'skipped')
            ->reorder()
            ->orderByDesc('order')
            ->first();

        if (! $previousStep) {
            return $onboarding->fresh('steps');
        }

        // Reset current step back to pending
        $currentStep->update([
            'status' => 'pending',
            'started_at' => null,
        ]);

        // Re-open the previous step
        $previousStep->update([
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        $onboarding->update(['current_step_id' => $previousStep->id]);

        return $onboarding->fresh('steps');
    }

    /**
     * Jump directly to an earlier step (e.g. from the sidebar tracker).
     *
     * Only allows navigating to a step at or before the current one. Every
     * non-skipped step after the target is demoted back to pending so the
     * user re-advances through them — this keeps later steps consistent when
     * an earlier answer (and its conditional logic) may have changed.
     */
    /**
     * Navigate back to an earlier step.
     *
     * Passing $returnTo marks this as an out-of-order edit (the "Edit" links on
     * the Final Review page): the later steps keep their completed state and
     * completing the edited step jumps straight back to $returnTo, instead of
     * demoting everything and making the client re-walk the whole form
     * (EOP-52).
     */
    public function goToStep(
        UserOnboarding $onboarding,
        UserOnboardingStep $targetStep,
        ?UserOnboardingStep $returnTo = null,
    ): UserOnboarding {
        // A submitted or decided application is locked: navigating steps must
        // not re-open it for editing or revert its status to in_progress
        // (EOP-44, EOP-77). Editing resumes only via reopen/resubmission.
        if ($this->isLockedForEditing($onboarding)) {
            return $onboarding->fresh('steps');
        }

        $current = $onboarding->steps()
            ->where('id', $onboarding->current_step_id)
            ->first();

        // Never allow skipping forward past the current step.
        if ($current && $targetStep->order > $current->order) {
            return $onboarding->fresh('steps');
        }

        // Only a later, already-completed step is a valid place to return to.
        $isEditAndReturn = $returnTo
            && $returnTo->order > $targetStep->order
            && $returnTo->status === 'completed';

        if (! $isEditAndReturn) {
            // Plain back-navigation: a change here may invalidate what follows,
            // so demote everything after the target back to pending.
            $onboarding->steps()
                ->where('order', '>', $targetStep->order)
                ->where('status', '!=', 'skipped')
                ->update(['status' => 'pending', 'started_at' => null, 'completed_at' => null]);
        }

        // Re-open the target step.
        $targetStep->update([
            'status' => 'in_progress',
            'completed_at' => null,
            'started_at' => $targetStep->started_at ?? now(),
        ]);

        $onboarding->update([
            'status' => 'in_progress',
            'completed_at' => null,
            'current_step_id' => $targetStep->id,
            'return_to_step_id' => $isEditAndReturn ? $returnTo->id : null,
        ]);

        return $onboarding->fresh('steps');
    }

    private function getCurrentTemplateVersion(): int
    {
        return OnboardingStep::max('version') ?? 1;
    }
}
