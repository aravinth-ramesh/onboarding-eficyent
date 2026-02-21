<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conditional_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete()->comment('The question to show/hide');
            $table->foreignId('parent_question_id')->constrained('questions')->cascadeOnDelete()->comment('The question whose answer triggers this rule');
            $table->enum('comparison_type', ['equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'in', 'not_in', 'is_empty', 'is_not_empty']);
            $table->text('trigger_value')->nullable(); // JSON for 'in'/'not_in', string otherwise
            $table->enum('action', ['show', 'hide'])->default('show');
            $table->enum('logical_operator', ['and', 'or'])->default('and'); // For combining multiple rules on same question
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conditional_rules');
    }
};
