<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_type_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_type_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_type_subcategories');
    }
};
