<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Four-eyes approval: a submitted application (status 'completed') is first
 * handed off for approval by the reviewer who worked it (the "maker"), then
 * approved or rejected by a different manager/compliance officer (the
 * "checker"). `approval_state` tracks that hand-off within the 'completed'
 * status; null means it's still being reviewed and hasn't been submitted yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            // null = in review · 'pending_approval' = awaiting a second reviewer
            // · 'escalated' = referred to compliance.
            $table->string('approval_state')->nullable()->after('decision_comment');
            $table->foreignId('submitted_for_approval_by')->nullable()->after('approval_state')
                ->constrained('admins')->nullOnDelete();
            $table->timestamp('submitted_for_approval_at')->nullable()->after('submitted_for_approval_by');
        });
    }

    public function down(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_for_approval_by');
            $table->dropColumn(['approval_state', 'submitted_for_approval_at']);
        });
    }
};
