<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permanent timeline of the review lifecycle. Unlike the decision
        // columns on user_onboardings (which reopening clears), these rows
        // are never mutated — past rejection reasons stay visible to admins
        // across resubmission rounds.
        Schema::create('onboarding_review_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_onboarding_id')->constrained()->cascadeOnDelete();
            $table->enum('event', ['submitted', 'resubmitted', 'approved', 'rejected', 'reopened']);
            // Null for client-driven events (submissions, reopening).
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['user_onboarding_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_review_logs');
    }
};
