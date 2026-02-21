<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Maps questions to user types and optionally subcategories
        // This allows questions to be reused across types
        Schema::create('question_type_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_type_subcategory_id')->nullable()->constrained('user_type_subcategories')->cascadeOnDelete();
            $table->integer('order')->default(0); // Override order per type/subcategory
            $table->boolean('is_required')->nullable(); // Override required per type/subcategory
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['question_id', 'user_type_id', 'user_type_subcategory_id'], 'qtm_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_type_mappings');
    }
};
