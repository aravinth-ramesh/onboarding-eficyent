<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-assignment of new submissions
    |--------------------------------------------------------------------------
    |
    | When enabled, a freshly submitted application that has no assignee is
    | automatically assigned to the active admin with the fewest open
    | (awaiting-review) assignments — a stateless least-loaded balance.
    | Resubmissions keep their existing reviewer for continuity.
    |
    */
    'auto_assign_submissions' => env('AUTO_ASSIGN_SUBMISSIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Client-facing review time estimate
    |--------------------------------------------------------------------------
    |
    | The portal shows how long a decision typically takes: the median of
    | actual submission→decision times over the last 30 days. Until at least
    | min_samples reviews exist, the fallback (in hours) is used instead.
    |
    */
    'review_estimate' => [
        'fallback_hours' => env('REVIEW_ESTIMATE_FALLBACK_HOURS', 48),
        'min_samples' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Review SLA (aging) thresholds
    |--------------------------------------------------------------------------
    |
    | How many days an application may sit at each stage before it is flagged
    | as overdue in the admin queues. `review_days` covers the wait from
    | submission to a reviewer taking a decision; `approval_days` covers the
    | wait once it has been handed off for a second reviewer's sign-off.
    |
    */
    'sla' => [
        'review_days' => (int) env('REVIEW_SLA_DAYS', 3),
        'approval_days' => (int) env('APPROVAL_SLA_DAYS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Company-name questions
    |--------------------------------------------------------------------------
    |
    | The admin lists identify an application by the company/entity name, not
    | the person who registered. That name is an answer to one of the questions
    | below (matched case-insensitively by label, first match wins in this
    | priority order), denormalised onto user_onboardings.company_name.
    |
    */
    'company_name_labels' => [
        'Full Legal Entity Name',
        'Legal Entity Name',
        'Registered Company Name',
        'Company Name',
        'Registered Name',
        'Entity Name',
        'Legal Name',
        'Organisation Name',
        'Organization Name',
        'Trading Name',
    ],

];
