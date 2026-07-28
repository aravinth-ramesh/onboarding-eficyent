<?php

namespace App\Enums;

/**
 * A staff member's role in the review operation. Each role maps to a fixed set
 * of abilities (see self::abilities) — the single source of truth the Gate is
 * built from. Roles are ordered by seniority in self::LEVEL for "at least"
 * checks.
 */
enum AdminRole: string
{
    case Analyst = 'analyst';
    case Manager = 'manager';
    case Compliance = 'compliance';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    /** Seniority rank, for "at least this role" comparisons. */
    private const LEVEL = [
        'analyst' => 1,
        'compliance' => 2,
        'manager' => 3,
        'admin' => 4,
        'super_admin' => 5,
    ];

    public function label(): string
    {
        return match ($this) {
            self::Analyst => 'Onboarding Analyst',
            self::Manager => 'Manager',
            self::Compliance => 'Compliance',
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }

    public function level(): int
    {
        return self::LEVEL[$this->value];
    }

    /**
     * The abilities this role grants. Consumed by AuthServiceProvider to define
     * a Gate per ability, and by the UserOnboarding policy. Keep ability names
     * in sync with the Ability constants below.
     */
    public function abilities(): array
    {
        return match ($this) {
            // Front-line reviewer: works only their assigned companies.
            self::Analyst => [
                Ability::VIEW_ASSIGNED_ONBOARDINGS,
                Ability::REVIEW_ONBOARDING,
                Ability::SUBMIT_FOR_APPROVAL,
                Ability::ESCALATE_ONBOARDING,
                Ability::MESSAGE_CLIENT,
            ],

            // Oversees analysts: sees everything, assigns, and approves.
            self::Manager => [
                Ability::VIEW_ALL_ONBOARDINGS,
                Ability::ASSIGN_ONBOARDING,
                Ability::REVIEW_ONBOARDING,
                Ability::SUBMIT_FOR_APPROVAL,
                Ability::APPROVE_ONBOARDING,
                Ability::REJECT_ONBOARDING,
                Ability::ESCALATE_ONBOARDING,
                Ability::MESSAGE_CLIENT,
                Ability::MANAGE_EMAILS,
                Ability::VIEW_WORKLOAD,
            ],

            // Risk / AML gate: reviews and decides escalated cases; tunes rules.
            self::Compliance => [
                Ability::VIEW_ALL_ONBOARDINGS,
                Ability::REVIEW_ONBOARDING,
                Ability::APPROVE_ONBOARDING,
                Ability::REJECT_ONBOARDING,
                Ability::ESCALATE_ONBOARDING,
                Ability::MESSAGE_CLIENT,
                Ability::TUNE_DOCUMENT_POLICY,
                Ability::VIEW_ACTIVITY_LOG,
            ],

            // Platform operator: everything a manager does, plus configuration
            // and user management.
            self::Admin => [
                Ability::VIEW_ALL_ONBOARDINGS,
                Ability::ASSIGN_ONBOARDING,
                Ability::REVIEW_ONBOARDING,
                Ability::SUBMIT_FOR_APPROVAL,
                Ability::APPROVE_ONBOARDING,
                Ability::REJECT_ONBOARDING,
                Ability::ESCALATE_ONBOARDING,
                Ability::MESSAGE_CLIENT,
                Ability::MANAGE_EMAILS,
                Ability::VIEW_WORKLOAD,
                Ability::MANAGE_TEMPLATES,
                Ability::TUNE_DOCUMENT_POLICY,
                Ability::VIEW_ACTIVITY_LOG,
                Ability::MANAGE_USERS,
            ],

            // Root: every ability, including role management.
            self::SuperAdmin => Ability::all(),
        };
    }

    public function hasAbility(string $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }

    /** For <select> lists — value => label. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $r) => [$r->value => $r->label()])
            ->all();
    }
}
