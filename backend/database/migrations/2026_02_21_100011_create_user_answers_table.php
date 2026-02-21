<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_onboarding_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable(); // JSON for multi_select, plain text otherwise
            $table->timestamps();

            $table->unique(['user_id', 'question_id', 'user_onboarding_id'], 'ua_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_answers');
    }
};
