<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_question_answer_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_question_answer_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('s3_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('disk')->default('s3');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_question_answer_files');
    }
};
