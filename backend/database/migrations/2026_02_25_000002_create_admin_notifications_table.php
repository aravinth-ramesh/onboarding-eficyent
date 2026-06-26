<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['change_request', 'new_question']);
            $table->foreignId('user_answer_id')->nullable()->constrained('user_answers')->nullOnDelete();
            $table->foreignId('admin_question_id')->nullable()->constrained('admin_questions')->nullOnDelete();
            $table->text('message');
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
