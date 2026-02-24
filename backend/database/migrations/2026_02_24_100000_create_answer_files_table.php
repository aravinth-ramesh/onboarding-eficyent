<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_answer_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('s3_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->string('disk')->default('s3');
            $table->timestamps();

            $table->index('user_answer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_files');
    }
};
