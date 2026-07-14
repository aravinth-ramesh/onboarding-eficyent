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

];
