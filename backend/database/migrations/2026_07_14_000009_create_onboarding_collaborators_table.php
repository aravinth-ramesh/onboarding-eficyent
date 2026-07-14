<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Team members invited by the application's owner. A user can be a
        // collaborator on at most one onboarding (unique user_id) — the same
        // exclusivity the owner side has via user_onboardings.user_id.
        Schema::create('onboarding_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_onboarding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_collaborators');
    }
};
