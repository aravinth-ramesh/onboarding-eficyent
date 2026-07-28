<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviewer-side progress for a single application, one row per section
 * (QuestionGroup). A large application carries many sections; the reviewer
 * marks each one as they go so a review can be paused and resumed across days
 * without losing where they were. This is deliberately separate from the
 * client's own step progress (user_onboarding_steps) — it tracks the analyst's
 * work, not the applicant's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_section_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_onboarding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_group_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_onboarding_id', 'question_group_id'], 'osr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_section_reviews');
    }
};
