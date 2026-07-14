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

];
