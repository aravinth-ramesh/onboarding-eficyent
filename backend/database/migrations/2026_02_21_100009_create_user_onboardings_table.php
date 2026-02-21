<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // User's overall onboarding record
        Schema::create('user_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_type_subcategory_id')->nullable()->constrained('user_type_subcategories')->nullOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'rejected'])->default('pending');
            $table->unsignedBigInteger('current_step_id')->nullable();
            $table->integer('template_version')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboardings');
    }
};
