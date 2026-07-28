<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The review timeline gains two four-eyes events: 'submitted_for_approval'
 * (the maker's hand-off) and 'escalated' (referral to compliance).
 */
return new class extends Migration
{
    private string $with = "ENUM('submitted','resubmitted','approved','rejected','reopened','submitted_for_approval','escalated') NOT NULL";

    private string $without = "ENUM('submitted','resubmitted','approved','rejected','reopened') NOT NULL";

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE onboarding_review_logs MODIFY COLUMN event {$this->with}");
        } else {
            // Non-MySQL: the enum is a CHECK constraint; relax to a plain string.
            Schema::table('onboarding_review_logs', function (Blueprint $t) {
                $t->string('event')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE onboarding_review_logs MODIFY COLUMN event {$this->without}");
        }
    }
};
