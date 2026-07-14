<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Internal annotations for the admin team only — never exposed
        // through any client-facing endpoint or document.
        Schema::create('onboarding_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_onboarding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->index(['user_onboarding_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_notes');
    }
};
