<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where to send the client back to after an out-of-order edit.
 *
 * Editing one section from the Final Review page used to demote every later
 * step to pending, forcing a walk back through the whole form to reach Review
 * again (EOP-52). With this set, the edited step returns straight to Review and
 * the later steps keep their completed state.
 *
 * Nullable: the normal linear flow never sets it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->foreignId('return_to_step_id')
                ->nullable()
                ->after('current_step_id')
                ->constrained('user_onboarding_steps')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_to_step_id');
        });
    }
};
