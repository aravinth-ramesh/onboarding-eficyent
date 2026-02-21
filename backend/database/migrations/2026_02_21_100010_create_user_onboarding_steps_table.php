<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // User-specific copies of onboarding steps
        Schema::create('user_onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_onboarding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('onboarding_step_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('component_key');
            $table->integer('order')->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');
            $table->json('config')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_onboarding_id', 'onboarding_step_id'], 'uos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboarding_steps');
    }
};
