<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record that a user account exists only because someone invited them onto a
 * team.
 *
 * Removing a team member deletes their collaborator row, which correctly cuts
 * them off from the shared application. But the portal is self-service: the
 * status endpoint hands an application to any signed-in user who has none, so
 * the removed member logged back in and was made the owner of a brand-new one
 * (EOP-56). Knowing they arrived by invitation is what lets that endpoint tell
 * them apart from a genuine new sign-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('email');
        });

        // Backfill: anyone who holds a collaborator row but has never owned an
        // application got their account from an invitation.
        DB::table('users')
            ->whereIn('id', fn ($q) => $q->select('user_id')->from('onboarding_collaborators'))
            ->whereNotIn('id', fn ($q) => $q->select('user_id')->from('user_onboardings'))
            ->update(['invited_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('invited_at');
        });
    }
};
