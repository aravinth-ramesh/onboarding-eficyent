<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every state-changing admin-panel request, captured by middleware —
        // an append-only trail across ALL admin actions (decisions, config
        // changes, notes, emails, bulk operations, ...).
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action');           // route name, e.g. user-onboardings.approve
            $table->string('method', 10);       // POST / PUT / PATCH / DELETE
            $table->string('path', 500);
            $table->string('subject_type')->nullable(); // bound model basename
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload')->nullable(); // sanitized request input
            $table->unsignedSmallInteger('status');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['admin_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
