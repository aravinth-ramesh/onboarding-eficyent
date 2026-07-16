<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A bulk email composed now to go out later. The recipient
        // onboarding ids are snapshotted at schedule time; placeholders are
        // resolved per-recipient when the send actually fires.
        Schema::create('scheduled_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('subject', 500);
            $table->text('body');
            $table->json('onboarding_ids');
            $table->timestamp('send_at');
            $table->enum('status', ['pending', 'sent', 'cancelled'])->default('pending');
            $table->unsignedInteger('sent_count')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_emails');
    }
};
