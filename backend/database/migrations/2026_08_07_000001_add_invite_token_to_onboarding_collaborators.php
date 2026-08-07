<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The invite email previously linked to a bare /home, so following it in a
     * browser that already held a session landed the invitee in THAT account —
     * typically the owner's (EOP-53). Give every invitation its own token so
     * the link identifies the invitation and the portal can require the
     * invitee to authenticate as themselves.
     */
    public function up(): void
    {
        Schema::table('onboarding_collaborators', function (Blueprint $table) {
            $table->string('invite_token', 64)->nullable()->unique()->after('invited_by');
            $table->timestamp('accepted_at')->nullable()->after('invite_token');
        });

        // Existing invitations keep working: give them a token, and treat
        // members who already signed in (profile completed) as accepted.
        DB::table('onboarding_collaborators')->orderBy('id')->each(function ($row) {
            DB::table('onboarding_collaborators')
                ->where('id', $row->id)
                ->update(['invite_token' => Str::random(48)]);
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_collaborators', function (Blueprint $table) {
            $table->dropColumn(['invite_token', 'accepted_at']);
        });
    }
};
