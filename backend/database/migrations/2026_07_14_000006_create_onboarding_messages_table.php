<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two-way message thread between a client and the admin team,
        // scoped to the client's onboarding.
        Schema::create('onboarding_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_onboarding_id')->constrained()->cascadeOnDelete();
            $table->enum('sender_type', ['client', 'admin']);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable(); // read by the other side
            $table->timestamps();

            $table->index(['user_onboarding_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_messages');
    }
};
