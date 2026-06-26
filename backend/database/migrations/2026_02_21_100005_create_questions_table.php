<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_group_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->enum('type', ['text', 'radio', 'date', 'select', 'multi_select', 'textarea', 'number', 'file']);
            $table->json('options')->nullable(); // For radio, select, multi_select
            $table->boolean('is_required')->default(false);
            $table->integer('order')->default(0);
            // Field-level validation metadata. Supported keys:
            //   text/textarea: pattern, pattern_message, min_length, max_length
            //   number:        min, max
            //   date:          allow_past, allow_future, allow_today, min_date, max_date
            $table->json('validation_rules')->nullable();
            $table->string('placeholder')->nullable();
            $table->string('help_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
