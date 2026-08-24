<?php

namespace App\Enums;

/**
 * The named permissions in the admin panel, granted to roles via
 * AdminRole::abilities() and enforced by the RequireAbility middleware
 * (route alias `ability:<name>`) and Admin::hasAbility() checks in
 * controllers/views. Reference these constants everywhere instead of raw
 * strings so a typo is a hard error.
 */
final class Ability
{
    // Onboardings — visibility
    public const VIEW_ALL_ONBOARDINGS = 'onboardings.view-all';

    public const VIEW_ASSIGNED_ONBOARDINGS = 'onboardings.view-assigned';

    // Onboardings — actions
    public const ASSIGN_ONBOARDING = 'onboardings.assign';

    public const REVIEW_ONBOARDING = 'onboardings.review';

    public const SUBMIT_FOR_APPROVAL = 'onboardings.submit-for-approval';

    public const APPROVE_ONBOARDING = 'onboardings.approve';

    public const REJECT_ONBOARDING = 'onboardings.reject';

    public const ESCALATE_ONBOARDING = 'onboardings.escalate';

    public const MESSAGE_CLIENT = 'onboardings.message-client';

    public const VIEW_WORKLOAD = 'onboardings.view-workload';

    // Platform
    public const MANAGE_TEMPLATES = 'platform.manage-templates';

    public const MANAGE_EMAILS = 'platform.manage-emails';

    public const TUNE_DOCUMENT_POLICY = 'platform.tune-document-policy';

    public const VIEW_ACTIVITY_LOG = 'platform.view-activity-log';

    public const MANAGE_USERS = 'platform.manage-users';

    /** Every ability — used to grant Super Admin the full set. */
    public static function all(): array
    {
        return [
            self::VIEW_ALL_ONBOARDINGS,
            self::VIEW_ASSIGNED_ONBOARDINGS,
            self::ASSIGN_ONBOARDING,
            self::REVIEW_ONBOARDING,
            self::SUBMIT_FOR_APPROVAL,
            self::APPROVE_ONBOARDING,
            self::REJECT_ONBOARDING,
            self::ESCALATE_ONBOARDING,
            self::MESSAGE_CLIENT,
            self::VIEW_WORKLOAD,
            self::MANAGE_TEMPLATES,
            self::MANAGE_EMAILS,
            self::TUNE_DOCUMENT_POLICY,
            self::VIEW_ACTIVITY_LOG,
            self::MANAGE_USERS,
        ];
    }
}
