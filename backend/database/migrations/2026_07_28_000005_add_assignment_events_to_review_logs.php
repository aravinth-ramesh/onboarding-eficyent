<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Assignment changes join the review timeline as 'assigned' / 'unassigned'
 * events, so an application carries a legible reassignment trail (who moved it
 * to whom, and when). On non-MySQL the event column is already a plain string
 * (relaxed in an earlier migration), so only MySQL needs the enum widened.
 */
return new class extends Migration
{
    private string $with = "ENUM('submitted','resubmitted','approved','rejected','reopened','submitted_for_approval','escalated','assigned','unassigned') NOT NULL";

    private string $without = "ENUM('submitted','resubmitted','approved','rejected','reopened','submitted_for_approval','escalated') NOT NULL";

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE onboarding_review_logs MODIFY COLUMN event {$this->with}");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE onboarding_review_logs MODIFY COLUMN event {$this->without}");
        }
    }
};
